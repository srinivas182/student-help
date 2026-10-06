<?php

use App\Domains\Curriculum\Models\CurriculumItem;
use App\Domains\Identity\Models\GuardianConsent;
use App\Domains\Identity\Services\ConsentService;
use App\Domains\Tutoring\Models\TutorProfile;
use App\Domains\Tutoring\Services\HelpRequestService;
use App\Models\User;
use App\Notifications\GuardianConsentDecided;
use App\Notifications\GuardianConsentRequest;
use App\Notifications\NewMessage;
use App\Notifications\RequestAccepted;
use App\Notifications\RequestEscalated;
use App\Notifications\RequestOffered;
use Database\Seeders\CurriculumSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->seed(CurriculumSeeder::class);
    $this->seed(SettingsSeeder::class);

    $this->subject = CurriculumItem::ofType(CurriculumItem::TYPE_SUBJECT)->first();
});

function makeStudent(int $age = 20): User
{
    $student = User::factory()->create([
        'role' => User::ROLE_STUDENT,
        'date_of_birth' => now()->subYears($age),
        'onboarding_completed_at' => now(),
    ]);

    return $student;
}

function makeTutor(CurriculumItem $subject): User
{
    $tutor = User::factory()->create(['role' => User::ROLE_TUTOR, 'date_of_birth' => now()->subYears(30)]);

    TutorProfile::create([
        'user_id' => $tutor->id,
        'verification_status' => TutorProfile::STATUS_APPROVED,
        'is_available' => true,
    ])->subjects()->attach($subject->id);

    return $tutor;
}

it('emails the guardian when a learner under 18 registers', function () {
    Notification::fake();

    $this->post('/register', [
        'first_name' => 'Sipho',
        'last_name' => 'Ndlovu',
        'email' => 'sipho@example.co.za',
        'date_of_birth' => now()->subYears(15)->format('Y-m-d'),
        'role' => User::ROLE_STUDENT,
        'password' => 'Str0ngPassw0rd!2026',
        'password_confirmation' => 'Str0ngPassw0rd!2026',
        'guardian_name' => 'Thandi Ndlovu',
        'guardian_email' => 'thandi@example.co.za',
        'guardian_mobile' => '+27820000000',
        'terms' => true,
    ])->assertRedirect();

    Notification::assertSentOnDemand(
        GuardianConsentRequest::class,
        fn ($notification, $channels, $notifiable) => $notifiable->routes['mail'] === 'thandi@example.co.za',
    );
});

it('lets a guardian approve from the emailed link and unlocks the account', function () {
    Notification::fake();

    $minor = makeStudent(15);
    $consent = app(ConsentService::class)->request($minor, [
        'guardian_name' => 'Thandi Ndlovu',
        'guardian_email' => 'thandi@example.co.za',
        'guardian_mobile' => '+27820000000',
    ]);

    expect($minor->fresh()->canParticipate())->toBeFalse();

    $this->get(route('consent.show', $consent->token))
        ->assertInertia(fn ($page) => $page->component('Consent/Decide')->where('found', true));

    $this->post(route('consent.decide', $consent->token), ['decision' => 'approve'])->assertRedirect();

    expect($consent->fresh()->status)->toBe(GuardianConsent::STATUS_APPROVED)
        ->and($consent->fresh()->decision_ip)->not->toBeNull()
        ->and($minor->fresh()->canParticipate())->toBeTrue();

    Notification::assertSentTo($minor, GuardianConsentDecided::class);
});

it('records a decline and keeps the account restricted', function () {
    Notification::fake();

    $minor = makeStudent(14);
    $consent = app(ConsentService::class)->request($minor, [
        'guardian_name' => 'Parent',
        'guardian_email' => 'parent@example.co.za',
    ]);

    $this->post(route('consent.decide', $consent->token), ['decision' => 'decline']);

    expect($consent->fresh()->status)->toBe(GuardianConsent::STATUS_DECLINED)
        ->and($minor->fresh()->canParticipate())->toBeFalse();
});

it('rejects an expired consent link', function () {
    Notification::fake();

    $minor = makeStudent(15);
    $consent = app(ConsentService::class)->request($minor, [
        'guardian_name' => 'Parent',
        'guardian_email' => 'parent@example.co.za',
    ]);

    $consent->forceFill(['requested_at' => now()->subDays(8)])->save();

    $this->get(route('consent.show', $consent->token))
        ->assertInertia(fn ($page) => $page->where('expired', true));

    $this->post(route('consent.decide', $consent->token), ['decision' => 'approve'])->assertStatus(410);
});

it('cannot be answered twice', function () {
    Notification::fake();

    $minor = makeStudent(15);
    $consent = app(ConsentService::class)->request($minor, [
        'guardian_name' => 'Parent',
        'guardian_email' => 'parent@example.co.za',
    ]);

    $this->post(route('consent.decide', $consent->token), ['decision' => 'approve']);
    $this->post(route('consent.decide', $consent->token), ['decision' => 'decline'])->assertStatus(409);

    expect($consent->fresh()->status)->toBe(GuardianConsent::STATUS_APPROVED);
});

it('notifies eligible tutors when a request is raised, and the student when it is accepted', function () {
    Notification::fake();

    $student = makeStudent();
    $student->subjects()->attach($this->subject->id, ['role' => 'subject']);
    $tutor = makeTutor($this->subject);

    $service = app(HelpRequestService::class);

    $request = $service->create($student, [
        'subject_id' => $this->subject->id,
        'topic' => 'Notification test',
        'description' => 'I would like to understand how this topic works before my test.',
    ]);

    Notification::assertSentTo($tutor, RequestOffered::class);

    $service->accept($request, $tutor);

    Notification::assertSentTo($student, RequestAccepted::class);
});

it('notifies the other party on a new message, never the sender', function () {
    Notification::fake();

    $student = makeStudent();
    $student->subjects()->attach($this->subject->id, ['role' => 'subject']);
    $tutor = makeTutor($this->subject);

    $service = app(HelpRequestService::class);
    $request = $service->create($student, [
        'subject_id' => $this->subject->id,
        'topic' => 'Message notification',
        'description' => 'Please explain the first step of this problem to me.',
    ]);
    $service->accept($request, $tutor);

    $this->actingAs($tutor)->post(route('conversations.store', $request), ['body' => 'Happy to help.']);

    Notification::assertSentTo($student, NewMessage::class);
    Notification::assertNotSentTo($tutor, NewMessage::class);
});

it('alerts administrators when a request is escalated', function () {
    Notification::fake();

    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $student = makeStudent();
    $student->subjects()->attach($this->subject->id, ['role' => 'subject']);
    makeTutor($this->subject);

    $request = app(HelpRequestService::class)->create($student, [
        'subject_id' => $this->subject->id,
        'topic' => 'Escalation test',
        'description' => 'Nobody has picked this up and I need help before Friday.',
    ]);

    $request->forceFill(['created_at' => now()->subHours(30)])->save();
    $this->artisan('requests:maintain');

    Notification::assertSentTo($admin, RequestEscalated::class);
});

it('respects notification preferences but always sends account email', function () {
    $student = makeStudent();
    $student->update(['notification_preferences' => ['new_message' => ['database' => true, 'mail' => false]]]);

    $preferences = app(App\Domains\Notifications\NotificationPreferences::class);

    expect($preferences->channelsFor($student, 'new_message'))->toBe(['database'])
        ->and($preferences->channelsFor($student, 'request_accepted'))->toContain('mail')
        ->and($preferences->channelsFor($student, 'consent'))->toBe(['mail']);
});

it('saves notification preferences from the settings screen', function () {
    $student = makeStudent();

    $this->actingAs($student)->put(route('notifications.preferences'), [
        'preferences' => [
            'new_message' => ['database' => true, 'mail' => false],
            'rating_received' => ['database' => false, 'mail' => false],
        ],
    ])->assertRedirect();

    expect($student->fresh()->notification_preferences['new_message']['mail'])->toBeFalse();
});
