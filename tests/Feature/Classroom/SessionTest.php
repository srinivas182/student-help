<?php

use App\Domains\Classroom\Models\ClassSession;
use App\Domains\Classroom\Models\Classroom;
use App\Domains\Classroom\Services\SessionService;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
    $this->seed(DatabaseSeeder::class);

    $this->classroom = Classroom::whereNotNull('teacher_id')->firstOrFail();
    $this->teacher = $this->classroom->teacher;
    $this->student = $this->classroom->students()->firstOrFail();

    // The seed ships demo sessions; these tests assert on their own
    ClassSession::query()->delete();
});

it('schedules a session and tells the class', function () {
    $this->actingAs($this->teacher)->post(route('classrooms.sessions.store', $this->classroom), [
        'title' => 'Revision: trinomials',
        'mode' => 'online',
        'meeting_url' => 'https://meet.google.com/abc-defg-hij',
        'starts_at' => now()->addDay()->format('Y-m-d H:i:s'),
        'duration_minutes' => 60,
    ])->assertRedirect();

    expect(ClassSession::count())->toBe(1);

    Notification::assertSentTo($this->student, App\Notifications\ClassSessionChanged::class);
});

it('creates every weekly repeat up to the end date', function () {
    app(SessionService::class)->schedule($this->classroom, $this->teacher, [
        'title' => 'Weekly maths',
        'mode' => 'in_person',
        'location' => 'Room 12',
        'starts_at' => now()->addDay(),
        'duration_minutes' => 45,
        'repeats' => 'weekly',
        'repeats_until' => now()->addWeeks(4),
    ]);

    // Starting tomorrow and repeating weekly until four weeks from today
    // gives three more that still fall inside the window
    expect(ClassSession::count())->toBe(4)
        ->and(ClassSession::whereNotNull('parent_session_id')->count())->toBe(3)
        ->and(ClassSession::orderByDesc('starts_at')->first()->starts_at)
        ->toBeLessThanOrEqual(now()->addWeeks(4)->endOfDay());
});

it('requires a meeting link for an online session and a place for one in person', function () {
    $this->actingAs($this->teacher)->post(route('classrooms.sessions.store', $this->classroom), [
        'title' => 'No link',
        'mode' => 'online',
        'starts_at' => now()->addDay()->format('Y-m-d H:i:s'),
        'duration_minutes' => 60,
    ])->assertSessionHasErrors('meeting_url');

    $this->actingAs($this->teacher)->post(route('classrooms.sessions.store', $this->classroom), [
        'title' => 'No room',
        'mode' => 'in_person',
        'starts_at' => now()->addDay()->format('Y-m-d H:i:s'),
        'duration_minutes' => 60,
    ])->assertSessionHasErrors('location');
});

it('hides the meeting link until the session is nearly due', function () {
    $later = ClassSession::create([
        'classroom_id' => $this->classroom->id,
        'title' => 'Tomorrow',
        'mode' => 'online',
        'meeting_url' => 'https://meet.google.com/abc-defg-hij',
        'starts_at' => now()->addDay(),
        'duration_minutes' => 60,
    ]);

    $soon = ClassSession::create([
        'classroom_id' => $this->classroom->id,
        'title' => 'Starting shortly',
        'mode' => 'online',
        'meeting_url' => 'https://meet.google.com/abc-defg-hij',
        'starts_at' => now()->addMinutes(5),
        'duration_minutes' => 60,
    ]);

    expect($later->isJoinable())->toBeFalse()
        ->and($soon->isJoinable())->toBeTrue();

    // And the link itself is not sent to the browser early
    $this->actingAs($this->student)
        ->get(route('classrooms.show', $this->classroom))
        ->assertInertia(function ($page) {
            $sessions = collect($page->toArray()['props']['sessions']);

            expect($sessions->firstWhere('title', 'Tomorrow')['meetingUrl'])->toBeNull()
                ->and($sessions->firstWhere('title', 'Starting shortly')['meetingUrl'])->not->toBeNull();
        });
});

it('refuses to join a session that has not opened', function () {
    $session = ClassSession::create([
        'classroom_id' => $this->classroom->id,
        'title' => 'Next week',
        'mode' => 'online',
        'meeting_url' => 'https://meet.google.com/abc-defg-hij',
        'starts_at' => now()->addWeek(),
        'duration_minutes' => 60,
    ]);

    $this->actingAs($this->student)
        ->get(route('classrooms.sessions.join', $session))
        ->assertStatus(422);
});

it('records attendance and sends the student to the meeting', function () {
    $session = ClassSession::create([
        'classroom_id' => $this->classroom->id,
        'title' => 'Now',
        'mode' => 'online',
        'meeting_url' => 'https://meet.google.com/abc-defg-hij',
        'starts_at' => now()->addMinutes(2),
        'duration_minutes' => 60,
    ]);

    $this->actingAs($this->student)
        ->get(route('classrooms.sessions.join', $session))
        ->assertRedirect('https://meet.google.com/abc-defg-hij');

    expect($session->attendees()->whereKey($this->student->id)->exists())->toBeTrue();
});

it('will not let someone outside the class join a session', function () {
    $outsider = User::factory()->create(['role' => User::ROLE_STUDENT, 'onboarding_completed_at' => now()]);

    $session = ClassSession::create([
        'classroom_id' => $this->classroom->id,
        'title' => 'Now',
        'mode' => 'online',
        'meeting_url' => 'https://meet.google.com/abc-defg-hij',
        'starts_at' => now()->addMinutes(2),
        'duration_minutes' => 60,
    ]);

    $this->actingAs($outsider)->get(route('classrooms.sessions.join', $session))->assertForbidden();
});

it('cancels a session with a reason and tells everyone', function () {
    $session = ClassSession::create([
        'classroom_id' => $this->classroom->id,
        'title' => 'Tomorrow',
        'mode' => 'in_person',
        'location' => 'Room 12',
        'starts_at' => now()->addDay(),
        'duration_minutes' => 60,
    ]);

    $this->actingAs($this->teacher)->post(route('classrooms.sessions.cancel', $session), [
        'reason' => 'The hall is double-booked.',
    ])->assertRedirect();

    expect($session->fresh()->status)->toBe(ClassSession::STATUS_CANCELLED);

    Notification::assertSentTo($this->student, App\Notifications\ClassSessionChanged::class);
});

it('will not cancel without telling learners why', function () {
    $session = ClassSession::create([
        'classroom_id' => $this->classroom->id,
        'title' => 'Tomorrow',
        'mode' => 'in_person',
        'location' => 'Room 12',
        'starts_at' => now()->addDay(),
        'duration_minutes' => 60,
    ]);

    $this->actingAs($this->teacher)
        ->post(route('classrooms.sessions.cancel', $session), ['reason' => ''])
        ->assertSessionHasErrors('reason');
});

it('stops anyone but the owning teacher changing the schedule', function () {
    $otherTeacher = User::factory()->create(['role' => User::ROLE_TUTOR, 'onboarding_completed_at' => now()]);

    expect(fn () => app(SessionService::class)->schedule($this->classroom, $otherTeacher, [
        'title' => 'Not mine',
        'mode' => 'online',
        'meeting_url' => 'https://meet.google.com/abc-defg-hij',
        'starts_at' => now()->addDay(),
    ]))->toThrow(Illuminate\Validation\ValidationException::class);
});

it('lets a student join an open class without a code', function () {
    $this->classroom->update(['is_discoverable' => true]);

    $newStudent = User::factory()->create([
        'role' => User::ROLE_STUDENT,
        'date_of_birth' => now()->subYears(19),
        'onboarding_completed_at' => now(),
    ]);

    $this->actingAs($newStudent)
        ->post(route('classrooms.requestJoin', $this->classroom))
        ->assertRedirect(route('classrooms.show', $this->classroom));

    expect($this->classroom->students()->whereKey($newStudent->id)->exists())->toBeTrue();
});

it('will not let a private class be joined without a code', function () {
    $this->classroom->update(['is_discoverable' => false]);

    $newStudent = User::factory()->create([
        'role' => User::ROLE_STUDENT,
        'date_of_birth' => now()->subYears(19),
        'onboarding_completed_at' => now(),
    ]);

    $this->actingAs($newStudent)
        ->post(route('classrooms.requestJoin', $this->classroom))
        ->assertNotFound();
});
