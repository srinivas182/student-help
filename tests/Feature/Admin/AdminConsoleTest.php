<?php

use App\Domains\Curriculum\Models\CurriculumItem;
use App\Domains\Identity\Models\AdminInvitation;
use App\Domains\Tutoring\Models\HelpRequest;
use App\Models\User;
use App\Notifications\AdminInvited;
use Database\Seeders\CurriculumSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

beforeEach(function () {
    Notification::fake();

    $this->seed(CurriculumSeeder::class);
    $this->seed(SettingsSeeder::class);

    $this->admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'onboarding_completed_at' => now()]);
    $this->student = User::factory()->create(['role' => User::ROLE_STUDENT, 'onboarding_completed_at' => now()]);
});

it('shows the operational dashboard to staff only', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.dashboard'))
        ->assertInertia(fn ($page) => $page->component('Admin/Dashboard')->has('people')->has('service'));

    $this->actingAs($this->student)->get(route('admin.dashboard'))->assertForbidden();
});

it('surfaces escalated requests and pending work on the dashboard', function () {
    $subject = CurriculumItem::ofType(CurriculumItem::TYPE_SUBJECT)->first();

    HelpRequest::create([
        'student_id' => $this->student->id,
        'subject_id' => $subject->id,
        'topic' => 'Escalated item',
        'description' => 'Nobody picked this up.',
        'status' => HelpRequest::STATUS_ESCALATED,
        'escalated_at' => now(),
    ]);

    $this->actingAs($this->admin)
        ->get(route('admin.dashboard'))
        ->assertInertia(fn ($page) => $page->where('requests.escalated', 1));
});

it('filters users by role, status and search', function () {
    User::factory()->create(['role' => User::ROLE_TUTOR, 'first_name' => 'Kgomotso', 'last_name' => 'Sithole']);

    $this->actingAs($this->admin)
        ->get(route('admin.users.index', ['role' => User::ROLE_TUTOR]))
        ->assertInertia(fn ($page) => $page->has('users.data', 1));

    $this->actingAs($this->admin)
        ->get(route('admin.users.index', ['search' => 'Kgomotso']))
        ->assertInertia(fn ($page) => $page->has('users.data', 1));
});

it('suspends and reinstates a user with an audited reason', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.users.suspend', $this->student), ['reason' => 'Repeated abusive messages.'])
        ->assertRedirect();

    expect($this->student->fresh()->status)->toBe('suspended')
        ->and(DB::table('audit_logs')->where('action', 'user.suspended')->count())->toBe(1);

    $this->actingAs($this->admin)->post(route('admin.users.reinstate', $this->student));

    expect($this->student->fresh()->status)->toBe('active');
});

it('stops an administrator suspending their own account', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.users.suspend', $this->admin), ['reason' => 'Oops'])
        ->assertStatus(422);
});

it('invites staff by email and creates the account on acceptance', function () {
    $this->actingAs($this->admin)->post(route('admin.invitations.send'), [
        'name' => 'Nomsa Dlamini',
        'email' => 'nomsa@dxstudenthelp.co.za',
        'role' => User::ROLE_MODERATOR,
    ])->assertRedirect();

    Notification::assertSentOnDemand(AdminInvited::class);

    $invitation = AdminInvitation::firstOrFail();

    $this->post(route('invitations.accept', $invitation->token), [
        'first_name' => 'Nomsa',
        'last_name' => 'Dlamini',
        'password' => 'Str0ng!Staff#Passw0rd',
        'password_confirmation' => 'Str0ng!Staff#Passw0rd',
    ])->assertRedirect(route('admin.dashboard'));

    $user = User::where('email', 'nomsa@dxstudenthelp.co.za')->firstOrFail();

    expect($user->role)->toBe(User::ROLE_MODERATOR)
        ->and($invitation->fresh()->accepted_at)->not->toBeNull();
});

it('refuses an expired or reused invitation', function () {
    $invitation = AdminInvitation::create([
        'name' => 'Late Joiner',
        'email' => 'late@dxstudenthelp.co.za',
        'role' => User::ROLE_MODERATOR,
        'token' => Str::random(64),
        'invited_by' => $this->admin->id,
        'expires_at' => now()->subDay(),
    ]);

    $this->post(route('invitations.accept', $invitation->token), [
        'first_name' => 'Late',
        'last_name' => 'Joiner',
        'password' => 'Str0ng!Staff#Passw0rd',
        'password_confirmation' => 'Str0ng!Staff#Passw0rd',
    ])->assertStatus(410);
});

it('does not allow a student to invite staff', function () {
    $this->actingAs($this->student)->post(route('admin.invitations.send'), [
        'name' => 'Sneaky',
        'email' => 'sneaky@example.co.za',
        'role' => User::ROLE_ADMIN,
    ])->assertForbidden();
});

it('browses and edits the curriculum tree', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.curriculum.index'))
        ->assertInertia(fn ($page) => $page->component('Admin/Curriculum/Index')->has('items', 3));

    $school = CurriculumItem::ofType(CurriculumItem::TYPE_PATHWAY)->where('name', 'School')->firstOrFail();

    $this->actingAs($this->admin)
        ->get(route('admin.curriculum.index', ['parent' => $school->id]))
        ->assertInertia(fn ($page) => $page->has('items', 2)->where('parent.name', 'School'));

    $this->actingAs($this->admin)->post(route('admin.curriculum.store'), [
        'parent_id' => $school->id,
        'type' => CurriculumItem::TYPE_INSTITUTION_TYPE,
        'name' => 'Home School',
    ])->assertRedirect();

    expect(CurriculumItem::where('name', 'Home School')->exists())->toBeTrue();
});

it('hides a curriculum item without removing it from existing students', function () {
    $subject = CurriculumItem::ofType(CurriculumItem::TYPE_SUBJECT)->first();
    $this->student->subjects()->attach($subject->id, ['role' => 'subject']);

    $this->actingAs($this->admin)->post(route('admin.curriculum.toggle', $subject))->assertRedirect();

    expect($subject->fresh()->is_active)->toBeFalse()
        ->and($this->student->subjects()->count())->toBe(1);
});

it('bulk imports subjects and skips duplicates', function () {
    $grade = CurriculumItem::ofType(CurriculumItem::TYPE_LEVEL)->where('name', 'Grade 11')->first();
    $before = $grade->children()->count();

    $this->actingAs($this->admin)->post(route('admin.curriculum.import'), [
        'parent_id' => $grade->id,
        'type' => CurriculumItem::TYPE_SUBJECT,
        'names' => "Mathematics\nDramatic Arts\nMusic\n\nDramatic Arts",
    ])->assertRedirect();

    expect($grade->children()->count())->toBe($before + 2);
});

it('saves platform settings and applies them immediately', function () {
    $this->actingAs($this->admin)->put(route('admin.settings.update'), [
        'settings' => [
            'minimum_registration_age' => 14,
            'request_escalation_hours' => 12,
            'max_open_requests_per_student' => 5,
            'auto_close_hours_after_resolved' => 48,
            'minimum_tutors_per_subject' => 2,
            'max_attachment_mb' => 8,
            'max_attachments_per_request' => 4,
            'prohibited_words' => ['badword'],
            'policy_version' => '1.1',
        ],
    ])->assertRedirect();

    Cache::forget('platform.settings');

    expect(setting('minimum_registration_age'))->toBe(14)
        ->and(setting('prohibited_words'))->toBe(['badword']);
});

it('rejects settings outside sensible bounds', function () {
    $this->actingAs($this->admin)->put(route('admin.settings.update'), [
        'settings' => [
            'minimum_registration_age' => 2,
            'request_escalation_hours' => 12,
            'max_open_requests_per_student' => 5,
            'auto_close_hours_after_resolved' => 48,
            'minimum_tutors_per_subject' => 2,
            'max_attachment_mb' => 8,
            'max_attachments_per_request' => 4,
            'policy_version' => '1.1',
        ],
    ])->assertSessionHasErrors('settings.minimum_registration_age');
});

it('shows the audit log to staff and filters by action', function () {
    $this->actingAs($this->admin)->post(route('admin.users.suspend', $this->student), [
        'reason' => 'Test suspension for audit.',
    ]);

    $this->actingAs($this->admin)
        ->get(route('admin.audit', ['action' => 'suspend']))
        ->assertInertia(fn ($page) => $page->component('Admin/AuditLog')->has('logs.data', 1));

    $this->actingAs($this->student)->get(route('admin.audit'))->assertForbidden();
});
