<?php

use App\Domains\Assistant\Models\AiAnswer;
use App\Domains\Assistant\Services\AssistantProvider;
use App\Domains\Assistant\Services\AssistantService;
use App\Domains\Assistant\Services\AssistantSettings;
use App\Domains\Assistant\Services\QuotaService;
use App\Domains\Curriculum\Models\CurriculumItem;
use App\Domains\Tutoring\Models\HelpRequest;
use App\Models\User;
use Database\Seeders\CurriculumSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

function configureAssistant(array $overrides = []): void
{
    $defaults = [
        'ai_mode' => 'always',
        'ai_api_key' => 'test-key',
        'ai_monthly_quota_per_student' => 3,
        'ai_daily_quota_per_student' => 2,
        'ai_monthly_budget_usd' => 50,
        'ai_fallback_after_hours' => 2,
    ];

    foreach (array_merge($defaults, $overrides) as $key => $value) {
        DB::table('settings')->updateOrInsert(
            ['key' => $key],
            ['value' => json_encode($value), 'created_at' => now(), 'updated_at' => now()],
        );
    }

    Cache::forget('platform.settings');
}

/** A provider stub so tests never call a real API. */
function fakeProvider(float $cost = 0.004): void
{
    app()->bind(AssistantProvider::class, fn () => new class($cost) implements AssistantProvider
    {
        public function __construct(private readonly float $cost)
        {
        }

        public function ask(string $systemPrompt, string $question): array
        {
            return [
                'text' => 'Start by identifying what the question is asking, then work through it step by step.',
                'input_tokens' => 120,
                'output_tokens' => 80,
                'cost_usd' => $this->cost,
            ];
        }

        public function name(): string
        {
            return 'test';
        }

        public function model(): string
        {
            return 'test-model';
        }
    });
}

beforeEach(function () {
    $this->seed(CurriculumSeeder::class);
    $this->seed(SettingsSeeder::class);

    fakeProvider();
    configureAssistant();

    $this->subject = CurriculumItem::ofType(CurriculumItem::TYPE_SUBJECT)->first();

    $this->student = User::factory()->create([
        'role' => User::ROLE_STUDENT,
        'date_of_birth' => now()->subYears(19),
        'onboarding_completed_at' => now(),
    ]);
    $this->student->subjects()->attach($this->subject->id, ['role' => 'subject']);

    $this->admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'onboarding_completed_at' => now()]);
});

it('is off until a provider and key are configured', function () {
    configureAssistant(['ai_mode' => 'off']);

    expect(app(AssistantSettings::class)->isEnabled())->toBeFalse();

    $this->actingAs($this->student)
        ->post(route('assistant.ask'), ['question' => 'How do I factorise a trinomial properly?'])
        ->assertSessionHasErrors('question');

    expect(AiAnswer::count())->toBe(0);
});

it('answers a question and records the cost', function () {
    $this->actingAs($this->student)
        ->post(route('assistant.ask'), ['question' => 'How do I know which trig identity to use?'])
        ->assertRedirect();

    $answer = AiAnswer::firstOrFail();

    expect($answer->provider)->toBe('test')
        ->and((float) $answer->cost_usd)->toBe(0.004)
        ->and($answer->refused)->toBeFalse();
});

it('refuses to do an assessment and does not charge the student a credit', function () {
    $service = app(AssistantService::class);

    expect($service->looksLikeAssessmentRequest('Please do my homework for me'))->toBeTrue()
        ->and($service->looksLikeAssessmentRequest('give me the answers to question 3'))->toBeTrue()
        ->and($service->looksLikeAssessmentRequest('How do I approach this kind of question?'))->toBeFalse();

    $this->actingAs($this->student)
        ->post(route('assistant.ask'), ['question' => 'Please complete my assignment on photosynthesis'])
        ->assertRedirect();

    $answer = AiAnswer::firstOrFail();

    expect($answer->refused)->toBeTrue()
        ->and($answer->answer)->toContain("can't complete an assessment")
        ->and(app(QuotaService::class)->usedThisMonth($this->student))->toBe(0);
});

it('stops the student at their monthly quota and tells them when it resets', function () {
    $quota = app(QuotaService::class);
    configureAssistant(['ai_monthly_quota_per_student' => 2, 'ai_daily_quota_per_student' => 10]);

    foreach (range(1, 2) as $i) {
        app(AssistantService::class)->ask($this->student, "Question number {$i} about this topic");
    }

    expect($quota->remaining($this->student))->toBe(0);

    $check = $quota->check($this->student);

    expect($check['allowed'])->toBeFalse()
        ->and($check['reason'])->toBe('monthly')
        ->and($check['message'])->toContain('resets on')
        ->and($check['message'])->toContain('ask a tutor');
});

it('stops a burst with the daily limit before the monthly one runs out', function () {
    configureAssistant(['ai_monthly_quota_per_student' => 20, 'ai_daily_quota_per_student' => 2]);

    foreach (range(1, 2) as $i) {
        app(AssistantService::class)->ask($this->student, "Another question number {$i} please");
    }

    $check = app(QuotaService::class)->check($this->student);

    expect($check['allowed'])->toBeFalse()
        ->and($check['reason'])->toBe('daily');
});

it('switches itself off for everyone when the platform budget is reached', function () {
    configureAssistant(['ai_monthly_budget_usd' => 0.003]);

    app(AssistantService::class)->ask($this->student, 'A question that uses up the budget');

    $check = app(QuotaService::class)->check($this->student);

    expect($check['allowed'])->toBeFalse()
        ->and($check['reason'])->toBe('platform_budget');
});

it('gives unlimited answers to a student granted free for life', function () {
    configureAssistant(['ai_monthly_quota_per_student' => 1, 'ai_daily_quota_per_student' => 1]);

    $this->student->update(['free_for_life' => true]);

    foreach (range(1, 3) as $i) {
        app(AssistantService::class)->ask($this->student, "Bursary student question {$i} about the topic");
    }

    expect(AiAnswer::count())->toBe(3)
        ->and(app(QuotaService::class)->check($this->student)['allowed'])->toBeTrue();
});

it('respects a per-student override set by an administrator', function () {
    configureAssistant(['ai_monthly_quota_per_student' => 2]);

    $this->student->update(['monthly_ai_quota' => 5]);

    expect(app(QuotaService::class)->monthlyLimit($this->student))->toBe(5);
});

it('only offers the AI in fallback mode once a tutor has had their chance', function () {
    configureAssistant(['ai_mode' => 'fallback', 'ai_fallback_after_hours' => 2]);

    $request = HelpRequest::create([
        'student_id' => $this->student->id,
        'subject_id' => $this->subject->id,
        'topic' => 'Waiting for a tutor',
        'description' => 'Nobody has picked this up yet and I need help.',
        'status' => HelpRequest::STATUS_OPEN,
    ]);

    $service = app(AssistantService::class);

    expect($service->isOfferedFor($request))->toBeFalse();

    $request->forceFill(['created_at' => now()->subHours(3)])->save();

    expect($service->isOfferedFor($request->fresh()))->toBeTrue();
});

it('always offers the AI when the mode is always on', function () {
    configureAssistant(['ai_mode' => 'always']);

    $request = HelpRequest::create([
        'student_id' => $this->student->id,
        'subject_id' => $this->subject->id,
        'topic' => 'Just asked',
        'description' => 'I have only just posted this question right now.',
        'status' => HelpRequest::STATUS_OPEN,
    ]);

    expect(app(AssistantService::class)->isOfferedFor($request))->toBeTrue();
});

it('lets a student escalate an AI answer to a human', function () {
    app(AssistantService::class)->ask($this->student, 'Something I did not quite follow here');

    $answer = AiAnswer::firstOrFail();

    $this->actingAs($this->student)
        ->post(route('assistant.escalate', $answer))
        ->assertRedirect(route('requests.create'));

    expect($answer->fresh()->escalated)->toBeTrue();
});

it('keeps one student out of another student\'s answers', function () {
    app(AssistantService::class)->ask($this->student, 'My own private question about maths');

    $answer = AiAnswer::firstOrFail();
    $other = User::factory()->create(['role' => User::ROLE_STUDENT, 'onboarding_completed_at' => now()]);

    $this->actingAs($other)->post(route('assistant.escalate', $answer))->assertForbidden();
    $this->actingAs($other)->post(route('assistant.feedback', $answer), ['helpful' => 1])->assertForbidden();
});

it('lets administrators configure the assistant without wiping a saved key', function () {
    $this->actingAs($this->admin)->put(route('admin.assistant.update'), [
        'mode' => 'fallback',
        'provider' => 'openai',
        'model' => 'gpt-test',
        'api_key' => '',
        'fallback_after_hours' => 4,
        'monthly_quota_per_student' => 15,
        'daily_quota_per_student' => 4,
        'monthly_budget_usd' => 75,
    ])->assertRedirect();

    Cache::forget('platform.settings');

    expect(setting('ai_mode'))->toBe('fallback')
        ->and(setting('ai_monthly_quota_per_student'))->toBe(15)
        ->and(setting('ai_api_key'))->toBe('test-key');

    $this->actingAs($this->student)->get(route('admin.assistant'))->assertForbidden();
});
