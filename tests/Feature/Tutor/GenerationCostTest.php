<?php

use App\Domains\Curriculum\Models\CurriculumItem;
use App\Domains\Security\Services\OneTimeCodeService;
use App\Domains\Tutor\Models\Language;
use App\Domains\Tutor\Models\Topic;
use App\Domains\Tutor\Models\TopicVersion;
use App\Domains\Tutor\Services\GenerationEstimator;
use App\Models\User;
use Database\Seeders\CurriculumSeeder;
use Database\Seeders\LanguageSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use App\Domains\Assistant\Services\AssistantProvider;

/** Local stubs: Pest helper functions are not shared between test files. */
function stubGenerator(): void
{
    app()->bind(AssistantProvider::class, fn () => new class implements AssistantProvider
    {
        public function ask(string $systemPrompt, string $question): array
        {
            return [
                'text' => json_encode([
                    'segments' => [['title' => 'Intro', 'narration' => 'Three terms.', 'visual' => 'Terms']],
                    'notes' => '# Notes',
                    'flashcards' => [],
                    'questions' => [[
                        'level' => 'basic',
                        'question' => 'How many terms?',
                        'options' => [['text' => 'Two'], ['text' => 'Three']],
                        'correct_index' => 1,
                        'explanation' => 'Tri means three.',
                    ]],
                ]),
                'input_tokens' => 2000,
                'output_tokens' => 1500,
                'cost_usd' => 0.02,
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



function tune(array $values): void
{
    foreach ($values as $key => $value) {
        DB::table('settings')->updateOrInsert(
            ['key' => $key],
            ['value' => json_encode($value), 'created_at' => now(), 'updated_at' => now()],
        );
    }

    Cache::forget('platform.settings');
}

beforeEach(function () {
    Notification::fake();

    $this->seed(CurriculumSeeder::class);
    $this->seed(LanguageSeeder::class);
    $this->seed(SettingsSeeder::class);

    stubGenerator();

    $this->admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'onboarding_completed_at' => now()]);
    $this->english = Language::where('code', 'en')->firstOrFail();
    $this->zulu = Language::where('code', 'zu')->firstOrFail();

    $this->topic = Topic::create([
        'curriculum_item_id' => CurriculumItem::ofType(CurriculumItem::TYPE_SUBJECT)->first()->id,
        'created_by' => $this->admin->id,
        'title' => 'Factorising trinomials',
        'slug' => 'factorising-cost',
    ]);

    $this->topic->sources()->create([
        'uploaded_by' => $this->admin->id,
        'kind' => 'text',
        'extracted_text' => str_repeat('Multiply the first and last coefficients carefully. ', 400),
        'rights_declared' => true,
        'extraction_status' => 'done',
    ]);
});

it('estimates tokens and cost per language before anything is spent', function () {
    $estimate = app(GenerationEstimator::class)->estimate($this->topic, [$this->english->id, $this->zulu->id]);

    expect($estimate['sourceWords'])->toBeGreaterThan(2000)
        ->and($estimate['languages'])->toHaveCount(2)
        ->and($estimate['totalUsd'])->toBeGreaterThan(0)
        ->and($estimate['totalZar'])->toBeGreaterThan($estimate['totalUsd'])
        // The non-English version costs a little more
        ->and($estimate['languages'][1]['outputTokens'])
        ->toBeGreaterThan($estimate['languages'][0]['outputTokens']);
});

it('returns the estimate to the admin screen without generating anything', function () {
    $this->actingAs($this->admin)
        ->postJson(route('admin.topics.estimate', $this->topic), [
            'language_ids' => [$this->english->id],
        ])
        ->assertOk()
        ->assertJsonStructure(['sourceWords', 'languages', 'totalUsd', 'totalZar', 'requiresOtp']);

    expect(TopicVersion::count())->toBe(0);
});

it('lets a routine generation through without a code', function () {
    tune(['ai_tutor_otp_cost_threshold' => 100, 'ai_tutor_otp_language_threshold' => 10]);

    $this->actingAs($this->admin)->post(route('admin.topics.generate', $this->topic), [
        'language_ids' => [$this->english->id],
    ])->assertRedirect();

    expect(TopicVersion::count())->toBe(1);
});

it('demands a code when the generation is expensive', function () {
    tune(['ai_tutor_otp_cost_threshold' => 0.0001]);

    $this->actingAs($this->admin)->post(route('admin.topics.generate', $this->topic), [
        'language_ids' => [$this->english->id],
    ])->assertSessionHasErrors('code');

    expect(TopicVersion::count())->toBe(0);
});

it('demands a code when many languages are generated at once', function () {
    tune(['ai_tutor_otp_cost_threshold' => 1000, 'ai_tutor_otp_language_threshold' => 2]);

    $this->actingAs($this->admin)->post(route('admin.topics.generate', $this->topic), [
        'language_ids' => [$this->english->id, $this->zulu->id],
    ])->assertSessionHasErrors('code');
});

it('generates once the emailed code is given', function () {
    tune([
        'ai_tutor_otp_cost_threshold' => 0.0001,
    ]);

    configureEmailGateway();

    $code = app(OneTimeCodeService::class)->issue(
        $this->admin,
        OneTimeCodeService::PURPOSE_GENERATION,
        'email',
        ['topic_id' => $this->topic->id],
    );

    $this->actingAs($this->admin)->post(route('admin.topics.generate', $this->topic), [
        'language_ids' => [$this->english->id],
        'code' => $code,
    ])->assertRedirect();

    expect(TopicVersion::count())->toBe(1)
        ->and(DB::table('audit_logs')->where('action', 'topic.generation_confirmed')->count())->toBe(1);
});

it('refuses a code that was issued for a different topic', function () {
    tune([
        'ai_tutor_otp_cost_threshold' => 0.0001,
    ]);

    configureEmailGateway();

    $other = Topic::create([
        'curriculum_item_id' => $this->topic->curriculum_item_id,
        'created_by' => $this->admin->id,
        'title' => 'Another topic',
        'slug' => 'another-topic',
    ]);
    $other->sources()->create([
        'uploaded_by' => $this->admin->id,
        'kind' => 'text',
        'extracted_text' => 'Source material for the other topic.',
        'rights_declared' => true,
        'extraction_status' => 'done',
    ]);

    $code = app(OneTimeCodeService::class)->issue(
        $this->admin,
        OneTimeCodeService::PURPOSE_GENERATION,
        'email',
        ['topic_id' => $this->topic->id],
    );

    $this->actingAs($this->admin)->post(route('admin.topics.generate', $other), [
        'language_ids' => [$this->english->id],
        'code' => $code,
    ])->assertStatus(422);

    expect(TopicVersion::count())->toBe(0);
});

it('skips the code entirely when confirmation is switched off', function () {
    tune(['ai_tutor_otp_enabled' => false, 'ai_tutor_otp_cost_threshold' => 0.0001]);

    $this->actingAs($this->admin)->post(route('admin.topics.generate', $this->topic), [
        'language_ids' => [$this->english->id],
    ])->assertRedirect();

    expect(TopicVersion::count())->toBe(1);
});
