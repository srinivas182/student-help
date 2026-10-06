<?php

use App\Domains\Curriculum\Models\CurriculumItem;
use App\Domains\Tutoring\Models\HelpRequest;
use App\Domains\Tutoring\Models\Message;
use App\Domains\Tutoring\Models\Report;
use App\Domains\Tutoring\Models\TutorProfile;
use App\Domains\Tutoring\Services\ContentFilter;
use App\Domains\Tutoring\Services\HelpRequestService;
use App\Models\User;
use Database\Seeders\CurriculumSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->seed(CurriculumSeeder::class);
    $this->seed(SettingsSeeder::class);

    $this->subject = CurriculumItem::ofType(CurriculumItem::TYPE_SUBJECT)->first();

    $this->student = User::factory()->create([
        'role' => User::ROLE_STUDENT,
        'date_of_birth' => now()->subYears(20),
        'onboarding_completed_at' => now(),
    ]);
    $this->student->subjects()->attach($this->subject->id, ['role' => 'subject']);

    $this->tutor = User::factory()->create([
        'role' => User::ROLE_TUTOR,
        'date_of_birth' => now()->subYears(30),
    ]);
    $profile = TutorProfile::create([
        'user_id' => $this->tutor->id,
        'verification_status' => TutorProfile::STATUS_APPROVED,
        'is_available' => true,
    ]);
    $profile->subjects()->attach($this->subject->id);

    $service = app(HelpRequestService::class);
    $this->request = $service->create($this->student, [
        'subject_id' => $this->subject->id,
        'topic' => 'Conversation test',
        'description' => 'I would like to understand this topic properly before the test.',
    ]);
    $service->accept($this->request, $this->tutor);
    $this->request->refresh();
});

it('masks phone numbers, emails and links before storing a message', function () {
    $filter = app(ContentFilter::class);

    expect($filter->mask('Call me on 082 123 4567')['body'])->not->toContain('082 123 4567')
        ->and($filter->mask('email me at tutor@gmail.com')['body'])->not->toContain('tutor@gmail.com')
        ->and($filter->mask('see https://wa.me/27821234567')['body'])->not->toContain('wa.me')
        ->and($filter->mask('find me @mytutorhandle')['body'])->not->toContain('@mytutorhandle')
        ->and($filter->mask('Try factorising first')['masked'])->toBeFalse();
});

it('stores the original text for moderators but shows the masked version', function () {
    $this->actingAs($this->tutor)
        ->post(route('conversations.store', $this->request), [
            'body' => 'WhatsApp me on 0821234567 and we can carry on there',
        ])->assertRedirect();

    $message = Message::firstOrFail();

    expect($message->body)->not->toContain('0821234567')
        ->and($message->body_original)->toContain('0821234567')
        ->and($message->toArray())->not->toHaveKey('body_original');
});

it('blocks a minor without guardian consent from sending messages', function () {
    $minor = User::factory()->create([
        'role' => User::ROLE_STUDENT,
        'date_of_birth' => now()->subYears(15),
        'onboarding_completed_at' => now(),
    ]);

    DB::table('help_requests')->where('id', $this->request->id)->update(['student_id' => $minor->id]);

    $this->actingAs($minor)
        ->post(route('conversations.store', $this->request), ['body' => 'Hello, can you help?'])
        ->assertSessionHasErrors('body');

    expect(Message::count())->toBe(0);
});

it('stops anyone outside the conversation reading or posting', function () {
    $stranger = User::factory()->create(['role' => User::ROLE_STUDENT, 'onboarding_completed_at' => now()]);

    $this->actingAs($stranger)->get(route('conversations.show', $this->request))->assertForbidden();
    $this->actingAs($stranger)->get(route('conversations.poll', $this->request))->assertForbidden();
});

it('closes the conversation when the request closes', function () {
    app(HelpRequestService::class)->close($this->request);

    $this->actingAs($this->tutor)
        ->post(route('conversations.store', $this->request), ['body' => 'One more thing'])
        ->assertSessionHasErrors('body');
});

it('raises a high-priority report when a minor is involved', function () {
    $minor = User::factory()->create([
        'role' => User::ROLE_STUDENT,
        'date_of_birth' => now()->subYears(14),
        'onboarding_completed_at' => now(),
    ]);
    DB::table('help_requests')->where('id', $this->request->id)->update(['student_id' => $minor->id]);

    $message = Message::create([
        'help_request_id' => $this->request->id,
        'sender_id' => $this->tutor->id,
        'body' => 'Message under review',
        'body_original' => 'Message under review',
    ]);

    $this->actingAs($minor)
        ->post(route('conversations.report', [$this->request, $message]), ['reason' => 'inappropriate'])
        ->assertRedirect();

    expect(Report::firstOrFail()->severity)->toBe(Report::SEVERITY_HIGH);
});

it('lets a moderator read the unmasked conversation and logs the access', function () {
    $moderator = User::factory()->create(['role' => User::ROLE_MODERATOR]);

    $this->actingAs($this->tutor)->post(route('conversations.store', $this->request), [
        'body' => 'Reach me at 0831112222',
    ]);

    $this->actingAs($moderator)
        ->get(route('moderation.conversation', $this->request))
        ->assertInertia(fn ($page) => $page
            ->component('Moderation/Conversation')
            ->where('messages.0.wasMasked', true));

    expect(DB::table('audit_logs')->where('action', 'conversation.viewed_by_moderator')->count())->toBe(1);
});

it('keeps students and tutors out of the moderation queue', function () {
    $this->actingAs($this->student)->get(route('moderation.index'))->assertForbidden();
    $this->actingAs($this->tutor)->get(route('moderation.index'))->assertForbidden();
});

it('lets a moderator suspend a user and records the outcome', function () {
    $moderator = User::factory()->create(['role' => User::ROLE_MODERATOR]);

    $message = Message::create([
        'help_request_id' => $this->request->id,
        'sender_id' => $this->tutor->id,
        'body' => 'Problem message',
        'body_original' => 'Problem message',
    ]);

    $report = app(App\Domains\Tutoring\Services\ModerationService::class)
        ->report($this->student, $message, 'harassment');

    $this->actingAs($moderator)
        ->post(route('moderation.resolve', $report), [
            'action' => 'suspend_user',
            'outcome' => 'Repeated attempts to move the learner off platform.',
        ])->assertRedirect();

    expect($this->tutor->fresh()->status)->toBe('suspended')
        ->and($report->fresh()->status)->toBe(Report::STATUS_CLOSED);
});

it('records one rating per request and updates the tutor average', function () {
    app(HelpRequestService::class)->resolve($this->request, $this->tutor);

    $this->actingAs($this->student)
        ->post(route('ratings.store', $this->request), ['stars' => 5, 'comment' => 'Very clear explanation.'])
        ->assertRedirect();

    expect($this->tutor->tutorProfile->fresh()->average_rating)->toEqual('5.00')
        ->and($this->tutor->tutorProfile->fresh()->ratings_count)->toBe(1);

    $this->actingAs($this->student)
        ->post(route('ratings.store', $this->request), ['stars' => 1])
        ->assertStatus(422);
});

it('does not allow rating before the tutor resolves the request', function () {
    $this->actingAs($this->student)
        ->post(route('ratings.store', $this->request), ['stars' => 4])
        ->assertStatus(422);
});
