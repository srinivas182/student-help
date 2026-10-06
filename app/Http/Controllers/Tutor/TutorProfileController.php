<?php

namespace App\Http\Controllers\Tutor;

use App\Domains\Curriculum\Models\CurriculumItem;
use App\Domains\Tutoring\Models\TutorProfile;
use App\Domains\Tutoring\Models\VerificationDocument;
use App\Domains\Tutoring\Services\TutorVerificationService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class TutorProfileController extends Controller
{
    public function __construct(private readonly TutorVerificationService $verification)
    {
    }

    public function edit(Request $request): Response
    {
        $user = $request->user();
        $profile = $this->profileFor($request);

        return Inertia::render('Tutor/Profile', [
            'profile' => [
                'bio' => $profile->bio,
                'highestQualification' => $profile->highest_qualification,
                'institutionName' => $profile->institution_name,
                'languages' => $profile->languages ?? [],
                'availability' => $profile->availability ?? [],
                'status' => $profile->verification_status,
                'notes' => $profile->verification_notes,
                'isAvailable' => $profile->is_available,
            ],
            'subjects' => $user->subjects()->pluck('curriculum_items.id'),
            'subjectNames' => $user->subjects()->pluck('name'),
            'documents' => $profile->documents->map(fn (VerificationDocument $d) => [
                'id' => $d->id,
                'type' => $d->document_type,
                'label' => TutorVerificationService::REQUIRED_DOCUMENTS[$d->document_type] ?? $d->document_type,
                'name' => $d->original_name,
                'uploadedAt' => $d->created_at?->diffForHumans(),
            ]),
            'requiredDocuments' => TutorVerificationService::REQUIRED_DOCUMENTS,
            'missingDocuments' => $this->verification->missingDocuments($profile),
            'canSubmit' => $this->verification->canSubmit($profile),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $profile = $this->profileFor($request);

        $validated = $request->validate([
            'bio' => ['required', 'string', 'min:30', 'max:1000'],
            'highest_qualification' => ['required', 'string', 'max:150'],
            'institution_name' => ['nullable', 'string', 'max:150'],
            'languages' => ['array'],
            'languages.*' => ['string', 'max:40'],
            'availability' => ['array'],
            'subject_ids' => ['required', 'array', 'min:1'],
            'subject_ids.*' => [
                'integer',
                Rule::exists('curriculum_items', 'id')->where('type', CurriculumItem::TYPE_SUBJECT),
            ],
        ], [
            'bio.min' => 'Tell students a little more about how you help — at least 30 characters.',
            'subject_ids.required' => 'Choose at least one subject you can teach.',
        ]);

        $profile->update([
            'bio' => $validated['bio'],
            'highest_qualification' => $validated['highest_qualification'],
            'institution_name' => $validated['institution_name'] ?? null,
            'languages' => $validated['languages'] ?? [],
            'availability' => $validated['availability'] ?? [],
        ]);

        $profile->subjects()->sync($validated['subject_ids']);

        $request->user()->subjects()->sync(
            collect($validated['subject_ids'])->mapWithKeys(fn ($id) => [$id => ['role' => 'subject']]),
        );

        return back()->with('success', 'Profile saved.');
    }

    public function uploadDocument(Request $request): RedirectResponse
    {
        $profile = $this->profileFor($request);

        $validated = $request->validate([
            'document_type' => ['required', Rule::in(array_keys(TutorVerificationService::REQUIRED_DOCUMENTS))],
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ], [
            'file.max' => 'Documents must be 10 MB or smaller.',
        ]);

        $this->verification->storeDocument($profile, $validated['document_type'], $request->file('file'));

        return back()->with('success', 'Document uploaded. Our team will review it within 48 hours.');
    }

    /** Resubmit after a rejection (TUT-04). */
    public function submit(Request $request): RedirectResponse
    {
        $profile = $this->profileFor($request);

        abort_unless($this->verification->canSubmit($profile), 422);

        $profile->update([
            'verification_status' => TutorProfile::STATUS_PENDING,
            'verification_notes' => null,
        ]);

        audit('tutor.submitted_for_review', $profile);

        return back()->with('success', 'Submitted for review. We will email you once a reviewer has looked at it.');
    }

    private function profileFor(Request $request): TutorProfile
    {
        $user = $request->user();

        abort_unless($user->isTutor(), 403);

        return $user->tutorProfile()->firstOrCreate(
            ['user_id' => $user->id],
            ['verification_status' => TutorProfile::STATUS_PENDING, 'is_available' => true],
        )->load('documents');
    }
}
