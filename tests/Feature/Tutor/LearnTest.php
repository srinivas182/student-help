<?php

use App\Domains\Curriculum\Models\CurriculumItem;
use App\Domains\Tutor\Models\Language;
use App\Domains\Tutor\Models\Topic;
use App\Domains\Tutor\Models\TopicProgress;
use App\Domains\Tutor\Models\TopicQuestion;
use App\Domains\Tutor\Models\TopicVersion;
use App\Models\User;
use Database\Seeders\CurriculumSeeder;
use Database\Seeders\LanguageSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->seed(CurriculumSeeder::class);
    $this->seed(LanguageSeeder::class);
    $this->seed(SettingsSeeder::class);

    $this->subject = CurriculumItem::ofType(CurriculumItem::TYPE_SUBJECT)->first();
    $this->otherSubject = CurriculumItem::ofType(CurriculumItem::TYPE_SUBJECT)
        ->where('id', '!=', $this->subject->id)->first();

    $this->english = Language::where('code', 'en')->firstOrFail();
    $this->zulu = Language::where('code', 'zu')->firstOrFail();

    $this->student = User::factory()->create([
        'role' => User::ROLE_STUDENT,
        'date_of_birth' => now()->subYears(18),
        'onboarding_completed_at' => now(),
    ]);
    $this->student->subjects()->attach($this->subject->id, ['role' => 'subject']);

    $this->reviewer = User::factory()->create([
        'role' => User::ROLE_TUTOR,
        'first_name' => 'Kgomotso',
        'last_name' => 'Sithole',
        'onboarding_completed_at' => now(),
    ]);
});

function publishTopic(?Language $language = null, ?CurriculumItem $subject = null): Topic
{
    $topic = Topic::create([
        'curriculum_item_id' => ($subject ?? test()->subject)->id,
        'created_by' => test()->reviewer->id,
        'title' => 'Factorising trinomials',
        'slug' => 'factorising-'.uniqid(),
        'summary' => 'How to factorise quadratics.',
        'estimated_minutes' => 20,
        'is_published' => true,
    ]);

    $version = TopicVersion::create([
        'topic_id' => $topic->id,
        'language_id' => ($language ?? test()->english)->id,
        'status' => TopicVersion::STATUS_PUBLISHED,
        'lesson' => ['segments' => [
            ['title' => 'What a trinomial is', 'narration' => 'Three terms.', 'visual' => 'Terms highlighted',
                'check' => ['question' => 'How many terms?', 'answer' => 'Three']],
            ['title' => 'Finding the factors', 'narration' => 'Multiply first and last.', 'visual' => 'Arrow'],
        ]],
        'notes' => '# Revision notes',
        'flashcards' => [['front' => 'Trinomial', 'back' => 'Three terms']],
        'provider' => 'test',
        'model' => 'test-model',
        'reviewed_by' => test()->reviewer->id,
        'reviewed_at' => now(),
        'generated_at' => now(),
    ]);

    TopicQuestion::create([
        'topic_version_id' => $version->id,
        'level' => 'basic',
        'question' => 'How many terms?',
        'options' => [['text' => 'Two'], ['text' => 'Three']],
        'correct_index' => 1,
        'explanation' => 'Tri means three.',
    ]);

    return $topic;
}

it('lists only published lessons for the student\'s own subjects', function () {
    publishTopic();
    publishTopic(null, $this->otherSubject);

    Topic::create([
        'curriculum_item_id' => $this->subject->id,
        'created_by' => $this->reviewer->id,
        'title' => 'Unpublished topic',
        'slug' => 'unpublished',
        'is_published' => false,
    ]);

    $this->actingAs($this->student)
        ->get(route('learn.index'))
        ->assertInertia(fn ($page) => $page->component('Learn/Index')->has('topics', 1));
});

it('blocks a lesson in a subject the student does not take', function () {
    $topic = publishTopic(null, $this->otherSubject);

    $this->actingAs($this->student)->get(route('learn.topic', $topic))->assertForbidden();
});

it('opens a lesson with segments, notes, flashcards and provenance', function () {
    $topic = publishTopic();

    $this->actingAs($this->student)
        ->get(route('learn.topic', $topic))
        ->assertInertia(fn ($page) => $page
            ->component('Learn/Topic')
            ->has('version.segments', 2)
            ->has('version.flashcards', 1)
            ->where('version.provenance.aiGenerated', true)
            ->where('version.provenance.reviewer', 'Kgomotso Sithole'));
});

it('saves the student\'s language and loads lessons in it', function () {
    $topic = publishTopic($this->zulu);

    $this->actingAs($this->student)
        ->post(route('learn.language'), ['language_id' => $this->zulu->id])
        ->assertRedirect();

    expect($this->student->fresh()->preferred_language_id)->toBe($this->zulu->id);

    $this->actingAs($this->student->fresh())
        ->get(route('learn.topic', $topic))
        ->assertInertia(fn ($page) => $page
            ->where('version.language.code', 'zu')
            ->where('requestedLanguageMissing', false));
});

it('falls back to English and says so when the language is missing', function () {
    $topic = publishTopic($this->english);

    $this->student->update(['preferred_language_id' => $this->zulu->id]);

    $this->actingAs($this->student->fresh())
        ->get(route('learn.topic', $topic))
        ->assertInertia(fn ($page) => $page
            ->where('version.language.code', 'en')
            ->where('requestedLanguageMissing', true)
            ->where('requestedLanguage', 'isiZulu'));
});

it('lists only the languages a topic actually exists in', function () {
    $topic = publishTopic($this->english);

    TopicVersion::create([
        'topic_id' => $topic->id,
        'language_id' => $this->zulu->id,
        'status' => TopicVersion::STATUS_REVIEW,
        'lesson' => ['segments' => []],
    ]);

    $this->actingAs($this->student)
        ->get(route('learn.topic', $topic))
        // The isiZulu version is still in review, so it is not offered
        ->assertInertia(fn ($page) => $page->has('availableLanguages', 1));
});

it('saves progress as the student moves through the lesson', function () {
    $topic = publishTopic();

    $this->actingAs($this->student)->get(route('learn.topic', $topic));

    $this->actingAs($this->student)->postJson(route('learn.progress', $topic), [
        'segment' => 0,
        'total_segments' => 2,
        'seconds' => 90,
    ])->assertOk()->assertJson(['percent' => 50, 'completed' => false]);

    $record = TopicProgress::firstOrFail();

    expect($record->segments_done)->toBe([0])
        ->and($record->seconds_spent)->toBe(90);
});

it('marks the lesson complete when every segment is done', function () {
    $topic = publishTopic();

    $this->actingAs($this->student)->get(route('learn.topic', $topic));

    foreach ([0, 1] as $segment) {
        $this->actingAs($this->student)->postJson(route('learn.progress', $topic), [
            'segment' => $segment,
            'total_segments' => 2,
        ]);
    }

    $record = TopicProgress::firstOrFail();

    expect($record->completed_at)->not->toBeNull()
        ->and($record->percent(2))->toBe(100)
        ->and(DB::table('audit_logs')->where('action', 'topic.completed')->count())->toBe(1);
});

it('does not count the same segment twice', function () {
    $topic = publishTopic();

    $this->actingAs($this->student)->get(route('learn.topic', $topic));

    foreach ([0, 0, 0] as $segment) {
        $this->actingAs($this->student)->postJson(route('learn.progress', $topic), [
            'segment' => $segment,
            'total_segments' => 2,
        ]);
    }

    expect(TopicProgress::firstOrFail()->segments_done)->toBe([0]);
});

it('shows progress back on the lesson list', function () {
    $topic = publishTopic();

    $this->actingAs($this->student)->get(route('learn.topic', $topic));
    $this->actingAs($this->student)->postJson(route('learn.progress', $topic), [
        'segment' => 0,
        'total_segments' => 2,
    ]);

    $this->actingAs($this->student)
        ->get(route('learn.index'))
        ->assertInertia(fn ($page) => $page->where('topics.0.percent', 50));
});

it('counts opening a lesson towards the student\'s activity streak', function () {
    $topic = publishTopic();

    $this->actingAs($this->student)->get(route('learn.topic', $topic));

    expect(DB::table('activity_days')->where('user_id', $this->student->id)->count())->toBe(1);
});
