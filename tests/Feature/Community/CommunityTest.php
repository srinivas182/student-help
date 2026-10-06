<?php

use App\Domains\Community\Models\CommunityPost;
use App\Domains\Community\Services\CommunityService;
use App\Domains\Curriculum\Models\CurriculumItem;
use App\Domains\Identity\Models\GuardianConsent;
use App\Domains\Progress\Services\ProgressService;
use App\Domains\Tutoring\Models\TutorProfile;
use App\Models\User;
use Database\Seeders\CurriculumSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->seed(CurriculumSeeder::class);
    $this->seed(SettingsSeeder::class);

    $this->subject = CurriculumItem::ofType(CurriculumItem::TYPE_SUBJECT)->first();
    $this->otherSubject = CurriculumItem::ofType(CurriculumItem::TYPE_SUBJECT)
        ->where('id', '!=', $this->subject->id)->first();

    $this->student = User::factory()->create([
        'role' => User::ROLE_STUDENT,
        'date_of_birth' => now()->subYears(19),
        'onboarding_completed_at' => now(),
    ]);
    $this->student->subjects()->attach($this->subject->id, ['role' => 'subject']);

    $this->classmate = User::factory()->create([
        'role' => User::ROLE_STUDENT,
        'date_of_birth' => now()->subYears(18),
        'onboarding_completed_at' => now(),
    ]);
    $this->classmate->subjects()->attach($this->subject->id, ['role' => 'subject']);

    $this->tutor = User::factory()->create(['role' => User::ROLE_TUTOR, 'onboarding_completed_at' => now()]);
    TutorProfile::create([
        'user_id' => $this->tutor->id,
        'verification_status' => TutorProfile::STATUS_APPROVED,
        'is_available' => true,
    ])->subjects()->attach($this->subject->id);
    $this->tutor->subjects()->attach($this->subject->id, ['role' => 'subject']);
});

function askCommunity(User $author, CurriculumItem $subject, string $title = 'How do I factorise trinomials?'): CommunityPost
{
    return app(CommunityService::class)->ask(
        $author,
        $subject,
        $title,
        'I get stuck when the leading coefficient is not one and I am not sure which method to use.',
    );
}

it('posts a question to a board the student actually studies', function () {
    $this->actingAs($this->student)->post(route('community.store'), [
        'curriculum_item_id' => $this->subject->id,
        'title' => 'How do I factorise trinomials?',
        'body' => 'I get stuck when the leading coefficient is not one. Which method should I use?',
    ])->assertRedirect();

    expect(CommunityPost::questions()->count())->toBe(1);

    $this->actingAs($this->student)->post(route('community.store'), [
        'curriculum_item_id' => $this->otherSubject->id,
        'title' => 'Not one of my subjects at all',
        'body' => 'This should not be allowed because I do not study this subject.',
    ])->assertSessionHasErrors('curriculum_item_id');
});

it('only shows boards for the student\'s own subjects', function () {
    askCommunity($this->student, $this->subject);

    $outsider = User::factory()->create(['role' => User::ROLE_STUDENT, 'onboarding_completed_at' => now()]);
    $outsider->subjects()->attach($this->otherSubject->id, ['role' => 'subject']);

    $this->actingAs($outsider)
        ->get(route('community.index'))
        ->assertInertia(fn ($page) => $page->has('questions.data', 0));

    $this->actingAs($this->student)
        ->get(route('community.index'))
        ->assertInertia(fn ($page) => $page->has('questions.data', 1));
});

it('masks contact details in questions and answers', function () {
    $question = app(CommunityService::class)->ask(
        $this->student,
        $this->subject,
        'Can someone help with this?',
        'I am struggling, email me on learner@example.co.za and I will send my working.',
    );

    expect($question->body)->not->toContain('learner@example.co.za')
        ->and($question->body_original)->toContain('learner@example.co.za')
        ->and($question->is_flagged)->toBeTrue();
});

it('blocks a minor without guardian consent from posting', function () {
    $minor = User::factory()->create([
        'role' => User::ROLE_STUDENT,
        'date_of_birth' => now()->subYears(15),
        'onboarding_completed_at' => now(),
    ]);
    GuardianConsent::create([
        'user_id' => $minor->id,
        'guardian_name' => 'Parent',
        'guardian_email' => 'p@example.co.za',
        'token' => Str::random(48),
        'status' => GuardianConsent::STATUS_PENDING,
        'requested_at' => now(),
    ]);
    $minor->subjects()->attach($this->subject->id, ['role' => 'subject']);

    $this->actingAs($minor)->post(route('community.store'), [
        'curriculum_item_id' => $this->subject->id,
        'title' => 'Please can somebody help me',
        'body' => 'I would really like some help with this topic before the test on Friday.',
    ])->assertSessionHasErrors('body');
});

it('counts replies and lets the asker accept an answer', function () {
    $question = askCommunity($this->student, $this->subject);

    $this->actingAs($this->classmate)
        ->post(route('community.reply', $question), ['body' => 'Multiply the first and last coefficients first.'])
        ->assertRedirect();

    expect($question->fresh()->replies_count)->toBe(1);

    $reply = CommunityPost::whereNotNull('parent_id')->firstOrFail();

    $this->actingAs($this->student)->post(route('community.accept', $reply))->assertRedirect();

    expect($reply->fresh()->is_accepted)->toBeTrue();
});

it('lets a verified tutor accept an answer too, but not a random student', function () {
    $question = askCommunity($this->student, $this->subject);
    $reply = app(CommunityService::class)->reply($this->classmate, $question, 'Try completing the square.');

    $stranger = User::factory()->create(['role' => User::ROLE_STUDENT, 'onboarding_completed_at' => now()]);

    $this->actingAs($stranger)->post(route('community.accept', $reply))->assertSessionHasErrors('reply');

    $this->actingAs($this->tutor)->post(route('community.accept', $reply))->assertRedirect();

    expect($reply->fresh()->is_accepted)->toBeTrue();
});

it('allows one upvote per person and no self-voting', function () {
    $question = askCommunity($this->student, $this->subject);

    $this->actingAs($this->student)->post(route('community.vote', $question))
        ->assertSessionHasErrors('vote');

    $this->actingAs($this->classmate)->post(route('community.vote', $question));
    expect($question->fresh()->votes)->toBe(1);

    // Voting again removes it
    $this->actingAs($this->classmate)->post(route('community.vote', $question));
    expect($question->fresh()->votes)->toBe(0)
        ->and(DB::table('community_votes')->count())->toBe(0);
});

it('records a report against a community post', function () {
    $question = askCommunity($this->student, $this->subject);

    $this->actingAs($this->classmate)
        ->post(route('community.report', $question), ['reason' => 'academic_dishonesty'])
        ->assertRedirect();

    expect(DB::table('reports')->count())->toBe(1);
});

it('builds a student progress picture from real activity', function () {
    askCommunity($this->student, $this->subject);
    $question = askCommunity($this->classmate, $this->subject, 'Another question about this topic');
    app(CommunityService::class)->reply($this->student, $question, 'Here is how I would approach it.');

    $stats = app(ProgressService::class)->forStudent($this->student);

    expect($stats['questionsAsked'])->toBe(1)
        ->and($stats['answersGiven'])->toBe(1)
        ->and($stats['streak'])->toBe(1);
});

it('counts a streak across consecutive days and breaks it on a gap', function () {
    $progress = app(ProgressService::class);

    DB::table('activity_days')->insert([
        ['user_id' => $this->student->id, 'day' => now()->toDateString(), 'actions' => 1],
        ['user_id' => $this->student->id, 'day' => now()->subDay()->toDateString(), 'actions' => 1],
        ['user_id' => $this->student->id, 'day' => now()->subDays(2)->toDateString(), 'actions' => 1],
        // gap on day 3
        ['user_id' => $this->student->id, 'day' => now()->subDays(4)->toDateString(), 'actions' => 1],
    ]);

    expect($progress->currentStreak($this->student))->toBe(3);
});

it('shows a tutor their impact and the monthly leaderboard', function () {
    $this->actingAs($this->tutor)
        ->get(route('progress'))
        ->assertInertia(fn ($page) => $page->component('Progress/Tutor')->has('stats')->has('leaderboard'));

    $this->actingAs($this->student)
        ->get(route('progress'))
        ->assertInertia(fn ($page) => $page->component('Progress/Student'));
});

it('issues a contribution certificate to verified tutors only', function () {
    $this->actingAs($this->tutor)
        ->get(route('progress.certificate'))
        ->assertInertia(fn ($page) => $page->component('Progress/Certificate')->has('reference'));

    $this->actingAs($this->student)->get(route('progress.certificate'))->assertForbidden();
});
