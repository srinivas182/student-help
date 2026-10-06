<?php

use App\Domains\Curriculum\Models\CurriculumItem;
use App\Domains\Tutoring\Models\HelpRequest;
use App\Domains\Tutoring\Models\TutorProfile;
use App\Domains\Tutoring\Services\TutorVerificationService;
use App\Models\User;
use App\Notifications\TutorVerificationReviewed;
use Database\Seeders\CurriculumSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    Notification::fake();

    $this->seed(CurriculumSeeder::class);
    $this->seed(SettingsSeeder::class);

    $this->subject = CurriculumItem::ofType(CurriculumItem::TYPE_SUBJECT)->first();

    $this->tutor = User::factory()->create([
        'role' => User::ROLE_TUTOR,
        'date_of_birth' => now()->subYears(28),
        'onboarding_completed_at' => now(),
    ]);

    $this->admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
});

function uploadAll(User $tutor): TutorProfile
{
    foreach (array_keys(TutorVerificationService::REQUIRED_DOCUMENTS) as $type) {
        test()->actingAs($tutor)->post(route('tutor.documents.upload'), [
            'document_type' => $type,
            'file' => UploadedFile::fake()->create("{$type}.pdf", 200, 'application/pdf'),
        ]);
    }

    return $tutor->tutorProfile()->first();
}

it('lets a tutor complete their profile and choose subjects', function () {
    $this->actingAs($this->tutor)->put(route('tutor.profile.update'), [
        'bio' => 'I have taught Grade 10 to 12 Mathematics for six years and focus on exam technique.',
        'highest_qualification' => 'BSc Mathematics, UKZN',
        'institution_name' => 'Durban High School',
        'languages' => ['English', 'isiZulu'],
        'subject_ids' => [$this->subject->id],
    ])->assertRedirect();

    $profile = $this->tutor->tutorProfile()->first();

    expect($profile->highest_qualification)->toBe('BSc Mathematics, UKZN')
        ->and($profile->subjects()->count())->toBe(1)
        ->and($this->tutor->subjects()->count())->toBe(1);
});

it('rejects a bio that is too short', function () {
    $this->actingAs($this->tutor)->put(route('tutor.profile.update'), [
        'bio' => 'I teach maths.',
        'highest_qualification' => 'BSc',
        'subject_ids' => [$this->subject->id],
    ])->assertSessionHasErrors('bio');
});

it('stores verification documents privately and replaces an existing one', function () {
    $this->actingAs($this->tutor)->post(route('tutor.documents.upload'), [
        'document_type' => 'id',
        'file' => UploadedFile::fake()->create('id.pdf', 100, 'application/pdf'),
    ])->assertRedirect();

    $profile = $this->tutor->tutorProfile()->first();
    expect($profile->documents()->count())->toBe(1);

    $this->actingAs($this->tutor)->post(route('tutor.documents.upload'), [
        'document_type' => 'id',
        'file' => UploadedFile::fake()->create('id-v2.pdf', 100, 'application/pdf'),
    ]);

    expect($profile->documents()->count())->toBe(1)
        ->and($profile->documents()->first()->original_name)->toBe('id-v2.pdf');
});

it('refuses an oversized or wrongly typed document', function () {
    $this->actingAs($this->tutor)->post(route('tutor.documents.upload'), [
        'document_type' => 'id',
        'file' => UploadedFile::fake()->create('huge.pdf', 11000, 'application/pdf'),
    ])->assertSessionHasErrors('file');

    $this->actingAs($this->tutor)->post(route('tutor.documents.upload'), [
        'document_type' => 'id',
        'file' => UploadedFile::fake()->create('script.exe', 10),
    ])->assertSessionHasErrors('file');
});

it('cannot be submitted for review until everything is complete', function () {
    $this->actingAs($this->tutor)->post(route('tutor.submit'))->assertStatus(422);

    $this->actingAs($this->tutor)->put(route('tutor.profile.update'), [
        'bio' => 'I have taught Grade 10 to 12 Mathematics for six years and focus on exam technique.',
        'highest_qualification' => 'BSc Mathematics, UKZN',
        'subject_ids' => [$this->subject->id],
    ]);
    uploadAll($this->tutor);

    $this->actingAs($this->tutor)->post(route('tutor.submit'))->assertRedirect();

    expect($this->tutor->tutorProfile()->first()->verification_status)->toBe(TutorProfile::STATUS_PENDING);
});

it('will not approve a tutor with missing documents', function () {
    $profile = TutorProfile::create([
        'user_id' => $this->tutor->id,
        'verification_status' => TutorProfile::STATUS_PENDING,
    ]);

    $this->actingAs($this->admin)
        ->post(route('admin.verification.approve', $profile))
        ->assertStatus(422);

    expect($profile->fresh()->verification_status)->toBe(TutorProfile::STATUS_PENDING);
});

it('approves a complete application and notifies the tutor', function () {
    $profile = uploadAll($this->tutor);

    $this->actingAs($this->admin)
        ->post(route('admin.verification.approve', $profile))
        ->assertRedirect(route('admin.verification.index'));

    expect($profile->fresh()->verification_status)->toBe(TutorProfile::STATUS_APPROVED)
        ->and($profile->fresh()->reviewed_by)->toBe($this->admin->id);

    Notification::assertSentTo($this->tutor, TutorVerificationReviewed::class);
});

it('requires a reason when rejecting', function () {
    $profile = uploadAll($this->tutor);

    $this->actingAs($this->admin)
        ->post(route('admin.verification.reject', $profile), ['reason' => 'no'])
        ->assertSessionHasErrors('reason');

    $this->actingAs($this->admin)
        ->post(route('admin.verification.reject', $profile), [
            'reason' => 'The qualification certificate is not legible. Please upload a clearer scan.',
        ])->assertRedirect();

    expect($profile->fresh()->verification_status)->toBe(TutorProfile::STATUS_REJECTED)
        ->and($profile->fresh()->verification_notes)->toContain('legible');
});

it('keeps verification documents away from everyone except staff', function () {
    $profile = uploadAll($this->tutor);
    $document = $profile->documents()->first();

    $otherTutor = User::factory()->create(['role' => User::ROLE_TUTOR]);

    $this->actingAs($otherTutor)->get(route('admin.documents.show', $document))->assertForbidden();
    $this->actingAs($this->tutor)->get(route('admin.documents.show', $document))->assertForbidden();

    $this->actingAs($this->admin)->get(route('admin.documents.show', $document))->assertOk();

    expect(DB::table('audit_logs')->where('action', 'tutor.document_viewed')->count())->toBe(1);
});

it('returns open requests to the queue when a tutor is suspended', function () {
    $profile = uploadAll($this->tutor);
    $profile->update(['verification_status' => TutorProfile::STATUS_APPROVED]);
    $profile->subjects()->attach($this->subject->id);

    $student = User::factory()->create(['role' => User::ROLE_STUDENT, 'onboarding_completed_at' => now()]);

    $request = HelpRequest::create([
        'student_id' => $student->id,
        'tutor_id' => $this->tutor->id,
        'subject_id' => $this->subject->id,
        'topic' => 'In progress',
        'description' => 'Work in progress with this tutor.',
        'status' => HelpRequest::STATUS_ASSIGNED,
        'assigned_at' => now(),
    ]);

    $this->actingAs($this->admin)->post(route('admin.verification.suspend', $profile), [
        'reason' => 'Repeated attempts to contact learners off platform.',
    ])->assertRedirect();

    $request->refresh();

    expect($this->tutor->fresh()->status)->toBe('suspended')
        ->and($request->status)->toBe(HelpRequest::STATUS_ESCALATED)
        ->and($request->tutor_id)->toBeNull();
});

it('shows coverage gaps against the minimum tutors per subject', function () {
    $profile = uploadAll($this->tutor);
    $profile->update(['verification_status' => TutorProfile::STATUS_APPROVED]);
    $profile->subjects()->attach($this->subject->id);

    $this->actingAs($this->admin)
        ->get(route('admin.coverage'))
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Coverage')
            ->where('minimum', 3)
            ->where('subjects.0.tutors', 1)
            ->where('subjects.0.isLive', false)
            ->where('subjects.0.shortfall', 2));
});

it('keeps students out of the verification queue and coverage screen', function () {
    $student = User::factory()->create(['role' => User::ROLE_STUDENT, 'onboarding_completed_at' => now()]);

    $this->actingAs($student)->get(route('admin.verification.index'))->assertForbidden();
    $this->actingAs($student)->get(route('admin.coverage'))->assertForbidden();
});
