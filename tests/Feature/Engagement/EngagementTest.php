<?php

use App\Domains\Curriculum\Models\CurriculumItem;
use App\Domains\Engagement\Services\NextActionService;
use App\Domains\Engagement\Services\SearchService;
use App\Domains\Engagement\Services\StreakService;
use App\Domains\Engagement\Services\SubjectProgressService;
use App\Domains\Tutor\Models\Topic;
use App\Domains\Tutor\Models\TopicProgress;
use App\Domains\Tutor\Models\TopicVersion;
use App\Domains\Tutor\Models\Language;
use App\Domains\Tutoring\Models\HelpRequest;
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

    $this->student = User::factory()->create([
        'role' => User::ROLE_STUDENT,
        'date_of_birth' => now()->subYears(17),
        'onboarding_completed_at' => now(),
    ]);

    $this->maths = CurriculumItem::ofType(CurriculumItem::TYPE_SUBJECT)
        ->where('name', 'Mathematics')->firstOrFail();

    $this->student->subjects()->syncWithoutDetaching([$this->maths->id => ['role' => 'subject']]);

    $this->topic = Topic::create([
        'curriculum_item_id' => $this->maths->id,
        'created_by' => $this->student->id,
        'title' => 'Factorising trinomials',
        'summary' => 'Splitting the middle term.',
        'slug' => 'engagement-trinomials',
        'is_published' => true,
    ]);

    $this->version = TopicVersion::create([
        'topic_id' => $this->topic->id,
        'language_id' => Language::where('code', 'en')->firstOrFail()->id,
        'status' => TopicVersion::STATUS_PUBLISHED,
        'lesson' => ['segments' => [['title' => 'One', 'narration' => 'x']]],
        'notes' => '# Notes',
    ]);
});

/** activity_days has no timestamps, so insert exactly what the table holds. */
function recordActiveDay(int $userId, $day): void
{
    DB::table('activity_days')->insert([
        'user_id' => $userId,
        'day' => $day->toDateString(),
        'actions' => 1,
    ]);
}

it('tells a brand-new student where to start', function () {
    $actions = app(NextActionService::class)->forStudent($this->student);

    expect($actions)->toHaveCount(2)
        ->and($actions[0]['kind'])->toBe('start')
        ->and($actions[1]['kind'])->toBe('ask');
});

it('puts an answered question above everything else', function () {
    HelpRequest::create([
        'student_id' => $this->student->id,
        'subject_id' => $this->maths->id,
        'topic' => 'Why does the sign flip?',
        'description' => 'Stuck on inequalities.',
        'status' => HelpRequest::STATUS_RESOLVED,
        'last_activity_at' => now(),
    ]);

    $actions = app(NextActionService::class)->forStudent($this->student);

    expect($actions[0]['kind'])->toBe('answered')
        ->and($actions[0]['tone'])->toBe('positive');
});

it('offers to carry on with a half-finished lesson', function () {
    TopicProgress::create([
        'user_id' => $this->student->id,
        'topic_id' => $this->topic->id,
        'topic_version_id' => $this->version->id,
        'segments_completed' => 3,
        'completed_at' => null,
    ]);

    $kinds = app(NextActionService::class)->forStudent($this->student)->pluck('kind');

    expect($kinds)->toContain('continue');
});

it('never shows more than three things to do', function () {
    HelpRequest::create([
        'student_id' => $this->student->id,
        'subject_id' => $this->maths->id,
        'topic' => 'Resolved one',
        'description' => 'x',
        'status' => HelpRequest::STATUS_RESOLVED,
        'last_activity_at' => now(),
    ]);

    HelpRequest::create([
        'student_id' => $this->student->id,
        'subject_id' => $this->maths->id,
        'topic' => 'Waiting one',
        'description' => 'y',
        'status' => HelpRequest::STATUS_OPEN,
        'last_activity_at' => now(),
    ]);

    TopicProgress::create([
        'user_id' => $this->student->id,
        'topic_id' => $this->topic->id,
        'topic_version_id' => $this->version->id,
        'segments_completed' => 1,
    ]);

    expect(app(NextActionService::class)->forStudent($this->student))->toHaveCount(3);
});

it('counts a streak and stays encouraging about a missed day', function () {
    foreach ([4, 3, 2, 1] as $daysAgo) {
        recordActiveDay($this->student->id, today()->subDays($daysAgo));
    }

    $summary = app(StreakService::class)->summary($this->student);

    expect($summary['days'])->toBe(4)
        ->and($summary['activeToday'])->toBeFalse()
        ->and($summary['atRisk'])->toBeTrue()
        // No shaming language anywhere
        ->and($summary['message'])->not->toContain('lost')
        ->and($summary['message'])->not->toContain('broken');
});

it('does not nudge a streak too short to be worth protecting', function () {
    recordActiveDay($this->student->id, today()->subDay());

    expect(app(StreakService::class)->summary($this->student)['atRisk'])->toBeFalse()
        ->and(app(StreakService::class)->studentsToNudge())->toHaveCount(0);
});

it('reports subject progress against published lessons only', function () {
    Topic::create([
        'curriculum_item_id' => $this->maths->id,
        'created_by' => $this->student->id,
        'title' => 'Unpublished draft',
        'slug' => 'draft-topic',
        'is_published' => false,
    ]);

    TopicProgress::create([
        'user_id' => $this->student->id,
        'topic_id' => $this->topic->id,
        'topic_version_id' => $this->version->id,
        'segments_completed' => 8,
        'completed_at' => now(),
    ]);

    $maths = app(SubjectProgressService::class)->forStudent($this->student)
        ->firstWhere('name', 'Mathematics');

    expect($maths['total'])->toBe(1)
        ->and($maths['completed'])->toBe(1)
        ->and($maths['percent'])->toBe(100);
});

it('searches lessons across the student\'s own subjects', function () {
    $results = app(SearchService::class)->search($this->student, 'trinomial');

    expect($results['total'])->toBe(1)
        ->and($results['groups'][0]['label'])->toBe('Lessons');
});

it('ignores a search term that is too short to be useful', function () {
    expect(app(SearchService::class)->search($this->student, 'a')['groups'])->toBeEmpty();
});

it('never returns another student\'s questions', function () {
    $other = User::factory()->create(['role' => User::ROLE_STUDENT, 'onboarding_completed_at' => now()]);

    HelpRequest::create([
        'student_id' => $other->id,
        'subject_id' => $this->maths->id,
        'topic' => 'Secret trinomial question',
        'description' => 'private',
        'status' => HelpRequest::STATUS_OPEN,
        'last_activity_at' => now(),
    ]);

    $results = app(SearchService::class)->search($this->student, 'Secret trinomial');

    $titles = collect($results['groups'])->flatMap(fn ($g) => collect($g['items'])->pluck('title'));

    expect($titles)->not->toContain('Secret trinomial question');
});

it('lets a student waiting on guardian consent study on their own', function () {
    $minor = User::factory()->create([
        'role' => User::ROLE_STUDENT,
        'date_of_birth' => now()->subYears(14),
        'onboarding_completed_at' => now(),
    ]);

    expect($minor->canParticipate())->toBeFalse()
        ->and($minor->canSelfStudy())->toBeTrue();

    $minor->subjects()->syncWithoutDetaching([$this->maths->id => ['role' => 'subject']]);

    $this->actingAs($minor)->get(route('learn.index'))->assertOk();
    $this->actingAs($minor)->get(route('learn.topic', $this->topic))->assertOk();
});

it('shows the dashboard with next actions, streak and progress', function () {
    $this->actingAs($this->student)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->has('nextActions')
            ->has('streak.days')
            ->has('subjectProgress'));
});

it('returns quick search results as json for the header box', function () {
    $this->actingAs($this->student)
        ->getJson(route('search.quick', ['q' => 'trinomial']))
        ->assertOk()
        ->assertJsonStructure(['term', 'groups', 'total']);
});
