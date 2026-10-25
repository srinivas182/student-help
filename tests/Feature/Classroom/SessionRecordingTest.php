<?php

use App\Domains\Classroom\Models\ClassSession;
use App\Domains\Classroom\Models\Classroom;
use App\Domains\Classroom\Services\SessionService;
use App\Models\User;
use App\Notifications\ClassSessionChanged;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

/**
 * Live is the product; the recording is the safety net for whoever could not
 * be there. A link is preferred over an upload because an hour of video is
 * several hundred megabytes, which fills the server and costs a learner on
 * mobile data more than the lesson is worth.
 */
beforeEach(function () {
    Notification::fake();
    Storage::fake('local');

    $this->seed(DatabaseSeeder::class);

    $this->classroom = Classroom::has('students')->whereNotNull('teacher_id')->firstOrFail();
    $this->teacher = $this->classroom->teacher;
    $this->student = $this->classroom->students()->firstOrFail();

    ClassSession::query()->delete();

    $this->session = ClassSession::create([
        'classroom_id' => $this->classroom->id,
        'title' => 'Revision session',
        'mode' => 'online',
        'meeting_url' => 'https://meet.google.com/abc-defg-hij',
        'starts_at' => now()->subHours(2),
        'duration_minutes' => 60,
    ]);
});

it('accepts a link to the recording and tells the class', function () {
    $this->actingAs($this->teacher)
        ->post(route('classrooms.sessions.recording', $this->session), [
            'recording_url' => 'https://www.youtube.com/watch?v=abc123',
            'recording_note' => 'Starts at 2 minutes in.',
        ])->assertRedirect();

    $session = $this->session->fresh();

    expect($session->hasRecording())->toBeTrue()
        ->and($session->recording_url)->toContain('youtube.com')
        ->and($session->recording_added_at)->not->toBeNull();

    Notification::assertSentTo($this->student, ClassSessionChanged::class);
});

it('accepts an uploaded file when a tutor has no cloud option', function () {
    $this->actingAs($this->teacher)
        ->post(route('classrooms.sessions.recording', $this->session), [
            'recording_file' => UploadedFile::fake()->create('lesson.mp4', 2048, 'video/mp4'),
        ])->assertRedirect();

    $session = $this->session->fresh();

    expect($session->recording_path)->not->toBeNull()
        ->and($session->recording_url)->toBeNull();

    Storage::disk('local')->assertExists($session->recording_path);
});

it('refuses a file too large to be worth hosting', function () {
    $this->actingAs($this->teacher)
        ->post(route('classrooms.sessions.recording', $this->session), [
            // Beyond the 500 MB ceiling, a link is the only sensible option
            'recording_file' => UploadedFile::fake()->create('huge.mp4', 600000, 'video/mp4'),
        ])->assertSessionHasErrors('recording_file');
});

it('wants either a link or a file, not neither', function () {
    $this->actingAs($this->teacher)
        ->post(route('classrooms.sessions.recording', $this->session), ['recording_note' => 'nothing'])
        ->assertSessionHasErrors('recording');
});

it('replaces an old upload rather than leaving it on disk', function () {
    $service = app(SessionService::class);

    $service->attachRecording($this->session, $this->teacher, null,
        UploadedFile::fake()->create('first.mp4', 100, 'video/mp4'));

    $first = $this->session->fresh()->recording_path;

    $service->attachRecording($this->session->fresh(), $this->teacher, null,
        UploadedFile::fake()->create('second.mp4', 100, 'video/mp4'));

    Storage::disk('local')->assertMissing($first);
    Storage::disk('local')->assertExists($this->session->fresh()->recording_path);
});

it('records who watched the recording', function () {
    $this->session->update(['recording_url' => 'https://www.youtube.com/watch?v=abc123']);

    $this->actingAs($this->student)
        ->get(route('classrooms.sessions.watch', $this->session))
        ->assertRedirect('https://www.youtube.com/watch?v=abc123');

    expect($this->session->attendees()->whereKey($this->student->id)->exists())->toBeTrue();
});

it('keeps the recording away from people outside the class', function () {
    $this->session->update(['recording_url' => 'https://www.youtube.com/watch?v=abc123']);

    $outsider = User::factory()->create(['role' => User::ROLE_STUDENT, 'onboarding_completed_at' => now()]);

    $this->actingAs($outsider)
        ->get(route('classrooms.sessions.watch', $this->session))
        ->assertForbidden();
});

it('only lets the owning teacher attach a recording', function () {
    $other = User::factory()->create(['role' => User::ROLE_TUTOR, 'onboarding_completed_at' => now()]);

    expect(fn () => app(SessionService::class)->attachRecording(
        $this->session, $other, 'https://example.com/video', null,
    ))->toThrow(Illuminate\Validation\ValidationException::class);
});

it('reminds the class shortly before a session, once only', function () {
    ClassSession::query()->delete();

    $soon = ClassSession::create([
        'classroom_id' => $this->classroom->id,
        'title' => 'Starting within the hour',
        'mode' => 'online',
        'meeting_url' => 'https://meet.google.com/abc-defg-hij',
        'starts_at' => now()->addMinutes(30),
        'duration_minutes' => 60,
    ]);

    ClassSession::create([
        'classroom_id' => $this->classroom->id,
        'title' => 'Next week',
        'mode' => 'online',
        'meeting_url' => 'https://meet.google.com/abc-defg-hij',
        'starts_at' => now()->addWeek(),
        'duration_minutes' => 60,
    ]);

    $service = app(SessionService::class);

    // Only the imminent one, and being told three weeks ahead is not a reminder
    expect($service->sendDueReminders(60))->toBe(1)
        ->and($soon->fresh()->reminded_at)->not->toBeNull()
        // Running again sends nothing: a reminder that repeats is noise
        ->and($service->sendDueReminders(60))->toBe(0);
});

it('does not remind anyone about a cancelled session', function () {
    ClassSession::query()->delete();

    ClassSession::create([
        'classroom_id' => $this->classroom->id,
        'title' => 'Called off',
        'mode' => 'online',
        'meeting_url' => 'https://meet.google.com/abc-defg-hij',
        'starts_at' => now()->addMinutes(20),
        'duration_minutes' => 60,
        'status' => ClassSession::STATUS_CANCELLED,
    ]);

    expect(app(SessionService::class)->sendDueReminders(60))->toBe(0);
});

it('knows when a session is live', function () {
    $live = ClassSession::create([
        'classroom_id' => $this->classroom->id,
        'title' => 'Happening now',
        'mode' => 'online',
        'meeting_url' => 'https://meet.google.com/abc-defg-hij',
        'starts_at' => now()->subMinutes(10),
        'duration_minutes' => 60,
    ]);

    expect($live->isLive())->toBeTrue()
        ->and($this->session->isLive())->toBeFalse();
});

it('attaches slides to the session they belong to', function () {
    $this->actingAs($this->teacher)
        ->post(route('classrooms.sessions.materials', $this->session), [
            'title' => 'Slides from today',
            'file' => UploadedFile::fake()->create('slides.pdf', 500, 'application/pdf'),
        ])->assertRedirect();

    expect($this->session->materials()->count())->toBe(1)
        ->and($this->session->materials()->first()->title)->toBe('Slides from today');
});

it('shows the teacher who came and who caught up afterwards', function () {
    $this->session->update(['recording_url' => 'https://www.youtube.com/watch?v=abc123']);

    $service = app(SessionService::class);
    $service->markAttended($this->session, $this->student);

    $register = $service->register($this->session, $this->teacher);
    $entry = $register->firstWhere('id', $this->student->id);

    expect($entry['attended'])->toBeTrue()
        ->and($register->count())->toBe($this->classroom->students()->count());
});

it('keeps the register away from anyone but the teacher', function () {
    $this->actingAs($this->student)
        ->get(route('classrooms.sessions.register', $this->session))
        ->assertSessionHasErrors('session');
});
