<?php

use App\Domains\Assessment\Models\AssessmentAttempt;
use App\Domains\Assessment\Models\TopicMastery;
use App\Domains\Assessment\Services\AssessmentService;
use App\Domains\Curriculum\Models\CurriculumItem;
use App\Domains\Tutor\Models\Language;
use App\Domains\Tutor\Models\Topic;
use App\Domains\Tutor\Models\TopicQuestion;
use App\Domains\Tutor\Models\TopicVersion;
use App\Models\User;
use Database\Seeders\CurriculumSeeder;
use Database\Seeders\LanguageSeeder;
use Database\Seeders\SettingsSeeder;

beforeEach(function () {
    $this->seed(CurriculumSeeder::class);
    $this->seed(LanguageSeeder::class);
    $this->seed(SettingsSeeder::class);

    $this->subject = CurriculumItem::ofType(CurriculumItem::TYPE_SUBJECT)->first();
    $this->english = Language::where('code', 'en')->firstOrFail();

    $this->student = User::factory()->create([
        'role' => User::ROLE_STUDENT,
        'date_of_birth' => now()->subYears(18),
        'onboarding_completed_at' => now(),
    ]);
    $this->student->subjects()->attach($this->subject->id, ['role' => 'subject']);

    $this->topic = Topic::create([
        'curriculum_item_id' => $this->subject->id,
        'created_by' => $this->student->id,
        'title' => 'Factorising trinomials',
        'slug' => 'factorising',
        'is_published' => true,
    ]);

    $this->version = TopicVersion::create([
        'topic_id' => $this->topic->id,
        'language_id' => $this->english->id,
        'status' => TopicVersion::STATUS_PUBLISHED,
        'lesson' => ['segments' => [['title' => 'Intro', 'narration' => 'Three terms.']]],
    ]);

    // Five questions per level so 80% is reachable and meaningful
    foreach (AssessmentService::LEVEL_ORDER as $level) {
        foreach (range(1, 5) as $i) {
            TopicQuestion::create([
                'topic_version_id' => $this->version->id,
                'level' => $level,
                'question' => "{$level} question {$i}",
                'options' => [
                    ['text' => 'Wrong', 'misconception' => 'Forgetting to check the middle term'],
                    ['text' => 'Right', 'misconception' => ''],
                ],
                'correct_index' => 1,
                'explanation' => 'Expand to check.',
            ]);
        }
    }
});

function answersFor(TopicVersion $version, string $level, int $correctCount): array
{
    $questions = $version->questions()->where('level', $level)->get();
    $answers = [];

    foreach ($questions as $index => $question) {
        $answers[$question->id] = $index < $correctCount ? 1 : 0;
    }

    return $answers;
}

it('unlocks only the basic level to begin with', function () {
    $levels = collect(app(AssessmentService::class)->levelsFor($this->student, $this->topic, $this->version));

    expect($levels->firstWhere('level', 'basic')['unlocked'])->toBeTrue()
        ->and($levels->firstWhere('level', 'easy')['unlocked'])->toBeFalse()
        ->and($levels->firstWhere('level', 'extreme')['unlocked'])->toBeFalse();
});

it('refuses a locked level even if the student goes straight to the url', function () {
    $this->actingAs($this->student)
        ->get(route('assessment.start', [$this->topic, 'difficult']))
        ->assertSessionHasErrors('level');

    $this->actingAs($this->student)
        ->post(route('assessment.submit', [$this->topic, 'difficult']), [
            'answers' => answersFor($this->version, 'difficult', 5),
        ])->assertSessionHasErrors('level');

    expect(AssessmentAttempt::count())->toBe(0);
});

it('unlocks the next level once the student scores 80 percent', function () {
    $this->actingAs($this->student)->post(route('assessment.submit', [$this->topic, 'basic']), [
        'answers' => answersFor($this->version, 'basic', 4),
    ])->assertRedirect();

    $attempt = AssessmentAttempt::firstOrFail();

    expect($attempt->percent)->toBe(80)
        ->and($attempt->passed)->toBeTrue();

    $levels = collect(app(AssessmentService::class)->levelsFor($this->student, $this->topic, $this->version));

    expect($levels->firstWhere('level', 'easy')['unlocked'])->toBeTrue()
        ->and($levels->firstWhere('level', 'intermediate')['unlocked'])->toBeFalse();
});

it('keeps the next level locked below the pass mark', function () {
    app(AssessmentService::class)->submit(
        $this->student, $this->topic, $this->version, 'basic',
        answersFor($this->version, 'basic', 3),
    );

    $levels = collect(app(AssessmentService::class)->levelsFor($this->student, $this->topic, $this->version));

    expect($levels->firstWhere('level', 'basic')['best'])->toBe(60)
        ->and($levels->firstWhere('level', 'easy')['unlocked'])->toBeFalse();
});

it('names the misconception behind each wrong answer', function () {
    $attempt = app(AssessmentService::class)->submit(
        $this->student, $this->topic, $this->version, 'basic',
        answersFor($this->version, 'basic', 3),
    );

    $wrong = collect($attempt->answers)->firstWhere('correct', false);

    expect($wrong['misconception'])->toBe('Forgetting to check the middle term')
        ->and($wrong['explanation'])->toBe('Expand to check.');

    $right = collect($attempt->answers)->firstWhere('correct', true);

    expect($right['misconception'])->toBeNull();
});

it('never sends the correct answer to the browser before marking', function () {
    $this->actingAs($this->student)
        ->get(route('assessment.start', [$this->topic, 'basic']))
        ->assertInertia(fn ($page) => $page
            ->component('Assessment/Take')
            ->has('questions', 5)
            ->missing('questions.0.correct_index')
            ->missing('questions.0.options.0.misconception'));
});

it('schedules the first review three days out after a pass', function () {
    app(AssessmentService::class)->submit(
        $this->student, $this->topic, $this->version, 'basic',
        answersFor($this->version, 'basic', 5),
    );

    $mastery = TopicMastery::firstOrFail();

    expect($mastery->review_stage)->toBe(1)
        ->and($mastery->highest_level)->toBe('basic')
        ->and($mastery->next_review_at->isSameDay(now()->addDays(3)))->toBeTrue();
});

it('moves the spacing out as reviews are passed', function () {
    $service = app(AssessmentService::class);

    $service->submit($this->student, $this->topic, $this->version, 'basic', answersFor($this->version, 'basic', 5));
    $service->submit($this->student, $this->topic, $this->version, 'basic', answersFor($this->version, 'basic', 5), isReview: true);

    expect(TopicMastery::firstOrFail()->next_review_at->isSameDay(now()->addDays(10)))->toBeTrue();

    $service->submit($this->student, $this->topic, $this->version, 'basic', answersFor($this->version, 'basic', 5), isReview: true);

    expect(TopicMastery::firstOrFail()->next_review_at->isSameDay(now()->addDays(30)))->toBeTrue();
});

it('brings a topic back sooner when a review is failed', function () {
    $service = app(AssessmentService::class);

    $service->submit($this->student, $this->topic, $this->version, 'basic', answersFor($this->version, 'basic', 5));
    $service->submit($this->student, $this->topic, $this->version, 'basic', answersFor($this->version, 'basic', 2), isReview: true);

    $mastery = TopicMastery::firstOrFail();

    expect($mastery->review_stage)->toBe(0)
        ->and($mastery->next_review_at->isSameDay(now()->addDay()))->toBeTrue();
});

it('lists topics that are due for review', function () {
    app(AssessmentService::class)->submit(
        $this->student, $this->topic, $this->version, 'basic',
        answersFor($this->version, 'basic', 5),
    );

    $this->actingAs($this->student)
        ->get(route('learn.reviews'))
        ->assertInertia(fn ($page) => $page->has('due', 0));

    TopicMastery::firstOrFail()->update(['next_review_at' => now()->subDay()]);

    $this->actingAs($this->student)
        ->get(route('learn.reviews'))
        ->assertInertia(fn ($page) => $page->has('due', 1));
});

it('lets a review run on a level the student has already passed', function () {
    app(AssessmentService::class)->submit(
        $this->student, $this->topic, $this->version, 'basic',
        answersFor($this->version, 'basic', 5),
    );

    $this->actingAs($this->student)
        ->get(route('assessment.start', [$this->topic, 'basic', 'review' => 1]))
        ->assertInertia(fn ($page) => $page->where('isReview', true));
});

it('keeps one student out of another student\'s results', function () {
    $attempt = app(AssessmentService::class)->submit(
        $this->student, $this->topic, $this->version, 'basic',
        answersFor($this->version, 'basic', 5),
    );

    $other = User::factory()->create(['role' => User::ROLE_STUDENT, 'onboarding_completed_at' => now()]);

    $this->actingAs($other)
        ->get(route('assessment.result', [$this->topic, $attempt]))
        ->assertForbidden();
});

it('blocks assessments in subjects the student does not take', function () {
    $other = User::factory()->create(['role' => User::ROLE_STUDENT, 'onboarding_completed_at' => now()]);

    $this->actingAs($other)->get(route('assessment.index', $this->topic))->assertForbidden();
});
