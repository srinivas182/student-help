<?php

use App\Domains\Classroom\Models\Classroom;
use App\Domains\Classroom\Services\ClassroomService;
use App\Domains\Curriculum\Models\CurriculumItem;
use App\Domains\Tutoring\Models\HelpRequest;
use App\Domains\Tutoring\Models\TutorProfile;
use App\Domains\Tutoring\Services\HelpRequestService;
use App\Domains\Voice\Models\VoiceNote;
use App\Domains\Voice\Services\Transcriber;
use App\Domains\Voice\Services\VoiceNoteService;
use App\Models\User;
use Database\Seeders\CurriculumSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');

    $this->seed(CurriculumSeeder::class);
    $this->seed(SettingsSeeder::class);

    $this->subject = CurriculumItem::ofType(CurriculumItem::TYPE_SUBJECT)->first();

    $this->teacher = User::factory()->create(['role' => User::ROLE_TUTOR, 'onboarding_completed_at' => now()]);
    TutorProfile::create([
        'user_id' => $this->teacher->id,
        'verification_status' => TutorProfile::STATUS_APPROVED,
        'is_available' => true,
    ])->subjects()->attach($this->subject->id);
    $this->teacher->subjects()->attach($this->subject->id, ['role' => 'subject']);

    $this->student = User::factory()->create([
        'role' => User::ROLE_STUDENT,
        'date_of_birth' => now()->subYears(20),
        'onboarding_completed_at' => now(),
    ]);
    $this->student->subjects()->attach($this->subject->id, ['role' => 'subject']);

    $this->moderator = User::factory()->create(['role' => User::ROLE_MODERATOR, 'onboarding_completed_at' => now()]);

    $this->classroom = app(ClassroomService::class)->create($this->teacher, [
        'name' => 'Grade 11 Mathematics',
        'type' => Classroom::TYPE_PERSONAL,
    ]);
});

function audio(int $kb = 400): UploadedFile
{
    return UploadedFile::fake()->create('lesson.webm', $kb, 'audio/webm');
}

it('lets a teacher record a voice note for their class', function () {
    $this->actingAs($this->teacher)->post(route('voiceNotes.store'), [
        'audio' => audio(),
        'duration_seconds' => 95,
        'title' => 'The chain rule in two minutes',
        'context' => 'classroom',
        'context_id' => $this->classroom->id,
    ])->assertRedirect();

    $note = VoiceNote::firstOrFail();

    expect($note->attachable_type)->toBe(Classroom::class)
        ->and($note->attachable_id)->toBe($this->classroom->id)
        ->and($note->durationLabel())->toBe('1:35');
});

it('refuses a recording longer than ten minutes or a non-audio file', function () {
    $this->actingAs($this->teacher)->post(route('voiceNotes.store'), [
        'audio' => audio(),
        'duration_seconds' => 1200,
        'context' => 'classroom',
        'context_id' => $this->classroom->id,
    ])->assertSessionHasErrors('duration_seconds');

    $this->actingAs($this->teacher)->post(route('voiceNotes.store'), [
        'audio' => UploadedFile::fake()->create('notes.pdf', 100, 'application/pdf'),
        'duration_seconds' => 30,
        'context' => 'classroom',
        'context_id' => $this->classroom->id,
    ])->assertSessionHasErrors('audio');
});

it('stops a teacher posting a voice note to someone else\'s class', function () {
    $other = User::factory()->create(['role' => User::ROLE_TUTOR, 'onboarding_completed_at' => now()]);

    $this->actingAs($other)->post(route('voiceNotes.store'), [
        'audio' => audio(),
        'duration_seconds' => 20,
        'context' => 'classroom',
        'context_id' => $this->classroom->id,
    ])->assertForbidden();
});

it('only plays a class voice note to its members', function () {
    app(ClassroomService::class)->join($this->classroom, $this->student);

    $this->actingAs($this->teacher)->post(route('voiceNotes.store'), [
        'audio' => audio(),
        'duration_seconds' => 40,
        'context' => 'classroom',
        'context_id' => $this->classroom->id,
    ]);

    $note = VoiceNote::firstOrFail();

    $this->actingAs($this->student)->get(route('voiceNotes.play', $note))->assertOk();

    $stranger = User::factory()->create(['role' => User::ROLE_STUDENT, 'onboarding_completed_at' => now()]);
    $this->actingAs($stranger)->get(route('voiceNotes.play', $note))->assertForbidden();

    expect($note->fresh()->plays)->toBe(1);
});

it('allows voice notes inside an open help request, for both sides', function () {
    $service = app(HelpRequestService::class);

    $request = $service->create($this->student, [
        'subject_id' => $this->subject->id,
        'topic' => 'Explain this step',
        'description' => 'I do not follow the second line of the worked example at all.',
    ]);
    $service->accept($request, $this->teacher);

    foreach ([$this->teacher, $this->student] as $user) {
        $this->actingAs($user)->post(route('voiceNotes.store'), [
            'audio' => audio(),
            'duration_seconds' => 25,
            'context' => 'request',
            'context_id' => $request->id,
        ])->assertRedirect();
    }

    expect(VoiceNote::where('attachable_type', HelpRequest::class)->count())->toBe(2);
});

it('blocks voice notes in a closed conversation', function () {
    $service = app(HelpRequestService::class);

    $request = $service->create($this->student, [
        'subject_id' => $this->subject->id,
        'topic' => 'Closed thread',
        'description' => 'This question has already been answered and closed off.',
    ]);
    $service->accept($request, $this->teacher);
    $service->close($request->fresh());

    $this->actingAs($this->teacher)->post(route('voiceNotes.store'), [
        'audio' => audio(),
        'duration_seconds' => 15,
        'context' => 'request',
        'context_id' => $request->id,
    ])->assertStatus(422);
});

it('marks notes as skipped while transcription is switched off', function () {
    expect(app(Transcriber::class)->isEnabled())->toBeFalse();

    $this->actingAs($this->teacher)->post(route('voiceNotes.store'), [
        'audio' => audio(),
        'duration_seconds' => 30,
        'context' => 'classroom',
        'context_id' => $this->classroom->id,
    ]);

    expect(VoiceNote::firstOrFail()->transcription_status)->toBe(VoiceNote::STATUS_SKIPPED);
});

it('masks and flags contact details spoken aloud once transcribed', function () {
    $note = VoiceNote::create([
        'user_id' => $this->teacher->id,
        'path' => 'voice-notes/test.webm',
        'mime_type' => 'audio/webm',
        'size' => 1000,
        'duration_seconds' => 30,
        'transcription_status' => VoiceNote::STATUS_PENDING,
    ]);

    app(VoiceNoteService::class)->applyTranscript(
        $note,
        'Good question. Just call me on 082 123 4567 and we can carry on there.',
    );

    $note->refresh();

    expect($note->transcript)->not->toContain('082 123 4567')
        ->and($note->transcript_original)->toContain('082 123 4567')
        ->and($note->is_flagged)->toBeTrue()
        ->and($note->flag_reason)->toBe('contact_details_spoken')
        ->and($note->toArray())->not->toHaveKey('transcript_original');
});

it('does not flag an ordinary explanation', function () {
    $note = VoiceNote::create([
        'user_id' => $this->teacher->id,
        'path' => 'voice-notes/clean.webm',
        'mime_type' => 'audio/webm',
        'size' => 1000,
        'duration_seconds' => 45,
        'transcription_status' => VoiceNote::STATUS_PENDING,
    ]);

    app(VoiceNoteService::class)->applyTranscript(
        $note,
        'Start by differentiating the outer function, then multiply by the derivative of the inner one.',
    );

    expect($note->fresh()->is_flagged)->toBeFalse()
        ->and($note->fresh()->isTranscribed())->toBeTrue();
});

it('gives moderators a review queue and keeps everyone else out', function () {
    $this->actingAs($this->teacher)->post(route('voiceNotes.store'), [
        'audio' => audio(),
        'duration_seconds' => 30,
        'context' => 'classroom',
        'context_id' => $this->classroom->id,
    ]);

    $this->actingAs($this->moderator)
        ->get(route('moderation.voice', ['filter' => 'all']))
        ->assertInertia(fn ($page) => $page
            ->component('Moderation/VoiceNotes')
            ->has('notes.data', 1)
            ->where('transcriptionEnabled', false));

    $this->actingAs($this->student)->get(route('moderation.voice'))->assertForbidden();
});

it('lets a moderator remove a voice note and the author delete their own', function () {
    $this->actingAs($this->teacher)->post(route('voiceNotes.store'), [
        'audio' => audio(),
        'duration_seconds' => 30,
        'context' => 'classroom',
        'context_id' => $this->classroom->id,
    ]);

    $note = VoiceNote::firstOrFail();

    $this->actingAs($this->student)->delete(route('voiceNotes.destroy', $note))->assertForbidden();
    $this->actingAs($this->teacher)->delete(route('voiceNotes.destroy', $note))->assertRedirect();

    expect(VoiceNote::count())->toBe(0);
});
