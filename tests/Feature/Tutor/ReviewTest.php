<?php

use App\Domains\Access\Models\ReviewerScope;
use App\Domains\Access\Models\Role;
use App\Domains\Curriculum\Models\CurriculumItem;
use App\Domains\Tutor\Models\Language;
use App\Domains\Tutor\Models\Topic;
use App\Domains\Tutor\Models\TopicQuestion;
use App\Domains\Tutor\Models\TopicVersion;
use App\Domains\Tutor\Services\ReviewService;
use App\Models\User;
use Database\Seeders\CurriculumSeeder;
use Database\Seeders\LanguageSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->seed(CurriculumSeeder::class);
    $this->seed(LanguageSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->seed(SettingsSeeder::class);

    $this->subject = CurriculumItem::ofType(CurriculumItem::TYPE_SUBJECT)->first();
    $this->otherSubject = CurriculumItem::ofType(CurriculumItem::TYPE_SUBJECT)
        ->where('id', '!=', $this->subject->id)->first();

    $this->english = Language::where('code', 'en')->firstOrFail();
    $this->zulu = Language::where('code', 'zu')->firstOrFail();

    $this->reviewer = User::factory()->create(['role' => User::ROLE_TUTOR, 'onboarding_completed_at' => now()]);
    $this->reviewer->roles()->attach(Role::where('slug', 'content-reviewer')->firstOrFail());

    $this->author = User::factory()->create(['role' => User::ROLE_ADMIN, 'onboarding_completed_at' => now()]);
});

function makeVersion(?CurriculumItem $subject = null, ?Language $language = null): TopicVersion
{
    $topic = Topic::create([
        'curriculum_item_id' => ($subject ?? test()->subject)->id,
        'created_by' => test()->author->id,
        'title' => 'Factorising trinomials',
        'slug' => 'factorising-trinomials-'.uniqid(),
        'summary' => 'How to factorise quadratics.',
    ]);

    $version = TopicVersion::create([
        'topic_id' => $topic->id,
        'language_id' => ($language ?? test()->english)->id,
        'status' => TopicVersion::STATUS_REVIEW,
        'lesson' => ['segments' => [
            ['title' => 'What a trinomial is', 'narration' => 'Three terms.', 'visual' => 'Terms highlighted'],
            ['title' => 'Finding factors', 'narration' => 'Multiply first and last.', 'visual' => 'Arrow'],
        ]],
        'notes' => '# Revision notes',
        'provider' => 'test',
        'model' => 'test-model',
        'generated_at' => now(),
    ]);

    TopicQuestion::create([
        'topic_version_id' => $version->id,
        'level' => 'basic',
        'question' => 'How many terms does a trinomial have?',
        'options' => [['text' => 'Two', 'misconception' => 'Confusing with binomial'], ['text' => 'Three']],
        'correct_index' => 1,
        'explanation' => 'Tri means three.',
    ]);

    return $version;
}

it('shows a reviewer only the lessons in their scope', function () {
    $mine = makeVersion($this->subject, $this->zulu);
    makeVersion($this->otherSubject, $this->english);

    ReviewerScope::create([
        'user_id' => $this->reviewer->id,
        'curriculum_item_id' => $this->subject->id,
        'language_id' => $this->zulu->id,
    ]);

    $this->actingAs($this->reviewer)
        ->get(route('review.index'))
        ->assertInertia(fn ($page) => $page
            ->component('Review/Index')
            ->has('queue', 1)
            ->where('queue.0.id', $mine->id));
});

it('blocks a reviewer from opening a lesson outside their scope', function () {
    $version = makeVersion($this->otherSubject, $this->english);

    ReviewerScope::create([
        'user_id' => $this->reviewer->id,
        'curriculum_item_id' => $this->subject->id,
        'language_id' => null,
    ]);

    $this->actingAs($this->reviewer)
        ->get(route('review.show', $version))
        ->assertSessionHasErrors('review');
});

it('keeps people without the review permission out entirely', function () {
    $version = makeVersion();
    $student = User::factory()->create(['role' => User::ROLE_STUDENT, 'onboarding_completed_at' => now()]);

    $this->actingAs($student)->get(route('review.index'))->assertForbidden();
    $this->actingAs($student)->get(route('review.show', $version))->assertForbidden();
});

it('lets a reviewer correct a segment and marks it as edited', function () {
    $version = makeVersion();

    $this->actingAs($this->reviewer)->put(route('review.segments.update', [$version, 0]), [
        'title' => 'What a trinomial actually is',
        'narration' => 'A trinomial has exactly three terms, such as x squared plus 5x plus 6.',
        'visual' => 'Each of the three terms highlighted in turn',
    ])->assertRedirect();

    $segment = $version->fresh()->segments()[0];

    expect($segment['title'])->toBe('What a trinomial actually is')
        ->and($segment['edited'])->toBeTrue()
        ->and(DB::table('audit_logs')->where('action', 'topic.segment_edited')->count())->toBe(1);
});

it('removes a segment the reviewer judges wrong', function () {
    $version = makeVersion();

    $this->actingAs($this->reviewer)
        ->delete(route('review.segments.remove', [$version, 1]))
        ->assertRedirect();

    expect(count($version->fresh()->segments()))->toBe(1);
});

it('lets a reviewer fix a question, including the misconception on a distractor', function () {
    $version = makeVersion();
    $question = $version->questions()->firstOrFail();

    $this->actingAs($this->reviewer)->put(route('review.questions.update', $question), [
        'question' => 'How many terms does a trinomial have?',
        'options' => [
            ['text' => 'Two', 'misconception' => 'Confusing a trinomial with a binomial'],
            ['text' => 'Three', 'misconception' => ''],
            ['text' => 'Four', 'misconception' => 'Counting the equals sign as a term'],
        ],
        'correct_index' => 1,
        'explanation' => 'Tri means three, so a trinomial has three terms.',
        'level' => 'basic',
    ])->assertRedirect();

    $question->refresh();

    expect($question->options)->toHaveCount(3)
        ->and($question->misconceptionFor(2))->toBe('Counting the equals sign as a term');
});

it('publishes with the reviewer named against it', function () {
    $version = makeVersion();

    $this->actingAs($this->reviewer)
        ->post(route('review.publish', $version), ['notes' => 'Checked against the CAPS document.'])
        ->assertRedirect(route('review.index'));

    $version->refresh();

    expect($version->status)->toBe(TopicVersion::STATUS_PUBLISHED)
        ->and($version->reviewed_by)->toBe($this->reviewer->id)
        ->and($version->topic->fresh()->is_published)->toBeTrue()
        ->and($version->provenance()['reviewer'])->toBe($this->reviewer->name);
});

it('refuses to publish a lesson with no questions or no content', function () {
    $version = makeVersion();
    $version->questions()->delete();

    $this->actingAs($this->reviewer)
        ->post(route('review.publish', $version))
        ->assertSessionHasErrors('review');

    expect($version->fresh()->status)->toBe(TopicVersion::STATUS_REVIEW);
});

it('requires a real reason when sending a lesson back', function () {
    $version = makeVersion();

    $this->actingAs($this->reviewer)
        ->post(route('review.reject', $version), ['reason' => 'nope'])
        ->assertSessionHasErrors('reason');

    $this->actingAs($this->reviewer)->post(route('review.reject', $version), [
        'reason' => 'The second worked example uses the wrong formula for the discriminant.',
    ])->assertRedirect();

    expect($version->fresh()->status)->toBe(TopicVersion::STATUS_REJECTED)
        ->and($version->fresh()->review_notes)->toContain('discriminant');
});

it('can pull a published lesson back when a mistake is spotted later', function () {
    $version = makeVersion();
    app(ReviewService::class)->publish($version, $this->reviewer);

    $this->actingAs($this->reviewer)->post(route('review.unpublish', $version), [
        'reason' => 'Formula error reported by a teacher.',
    ])->assertRedirect();

    $version->refresh();

    expect($version->status)->toBe(TopicVersion::STATUS_REVIEW)
        ->and($version->topic->fresh()->is_published)->toBeFalse();
});

it('reports how much of the AI output a human had to change', function () {
    $version = makeVersion();

    app(ReviewService::class)->updateSegment($version, 0, ['narration' => 'Corrected explanation.']);

    $stats = app(ReviewService::class)->editStats($version->fresh());

    expect($stats['segments'])->toBe(2)
        ->and($stats['segmentsEdited'])->toBe(1)
        ->and($stats['questions'])->toBe(1);
});
