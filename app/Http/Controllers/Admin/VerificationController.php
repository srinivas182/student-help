<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Tutoring\Models\TutorProfile;
use App\Domains\Tutoring\Models\VerificationDocument;
use App\Domains\Tutoring\Services\TutorVerificationService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VerificationController extends Controller
{
    public function __construct(private readonly TutorVerificationService $verification)
    {
    }

    /** TUT-03: the review queue, oldest first so nobody waits indefinitely. */
    public function index(Request $request): Response
    {
        $status = $request->string('status')->toString() ?: TutorProfile::STATUS_PENDING;

        $profiles = TutorProfile::query()
            ->where('verification_status', $status)
            ->with(['user:id,first_name,last_name,email,created_at', 'subjects:id,name'])
            ->withCount('documents')
            ->oldest()
            ->paginate(15)
            ->withQueryString()
            ->through(fn (TutorProfile $profile) => [
                'id' => $profile->id,
                'name' => $profile->user?->name,
                'email' => $profile->user?->email,
                'qualification' => $profile->highest_qualification,
                'subjects' => $profile->subjects->pluck('name')->take(5),
                'subjectCount' => $profile->subjects->count(),
                'documents' => $profile->documents_count,
                'waiting' => $profile->created_at?->diffForHumans(null, true),
                'status' => $profile->verification_status,
            ]);

        return Inertia::render('Admin/Verification/Index', [
            'profiles' => $profiles,
            'filters' => ['status' => $status],
            'counts' => [
                'pending' => TutorProfile::where('verification_status', TutorProfile::STATUS_PENDING)->count(),
                'approved' => TutorProfile::where('verification_status', TutorProfile::STATUS_APPROVED)->count(),
                'rejected' => TutorProfile::where('verification_status', TutorProfile::STATUS_REJECTED)->count(),
            ],
        ]);
    }

    public function show(TutorProfile $tutorProfile): Response
    {
        $tutorProfile->load(['user', 'subjects:id,name', 'documents', 'reviewedBy:id,first_name,last_name']);

        return Inertia::render('Admin/Verification/Show', [
            'profile' => [
                'id' => $tutorProfile->id,
                'name' => $tutorProfile->user?->name,
                'email' => $tutorProfile->user?->email,
                'registered' => $tutorProfile->user?->created_at?->toFormattedDateString(),
                'bio' => $tutorProfile->bio,
                'qualification' => $tutorProfile->highest_qualification,
                'institution' => $tutorProfile->institution_name,
                'languages' => $tutorProfile->languages ?? [],
                'availability' => $tutorProfile->availability ?? [],
                'status' => $tutorProfile->verification_status,
                'notes' => $tutorProfile->verification_notes,
                'reviewedBy' => $tutorProfile->reviewedBy?->name,
                'reviewedAt' => $tutorProfile->reviewed_at?->toDayDateTimeString(),
                'subjects' => $tutorProfile->subjects->pluck('name'),
            ],
            'documents' => $tutorProfile->documents->map(fn (VerificationDocument $d) => [
                'id' => $d->id,
                'type' => $d->document_type,
                'label' => TutorVerificationService::REQUIRED_DOCUMENTS[$d->document_type] ?? $d->document_type,
                'name' => $d->original_name,
                'sizeKb' => (int) round($d->size / 1024),
                'uploadedAt' => $d->created_at?->toFormattedDateString(),
            ]),
            'missing' => $this->verification->missingDocuments($tutorProfile),
        ]);
    }

    /** TUT-07: documents are streamed to staff only, never publicly linked. */
    public function document(Request $request, VerificationDocument $document): StreamedResponse
    {
        abort_unless(Storage::disk('local')->exists($document->path), 404);

        audit('tutor.document_viewed', $document, ['viewer_id' => $request->user()->id]);

        return Storage::disk('local')->response($document->path, $document->original_name);
    }

    public function approve(Request $request, TutorProfile $tutorProfile): RedirectResponse
    {
        abort_unless($this->verification->missingDocuments($tutorProfile) === [], 422,
            'This tutor has not uploaded all required documents.');

        $this->verification->approve($tutorProfile, $request->user());

        return redirect()->route('admin.verification.index')
            ->with('success', $tutorProfile->user->first_name.' is now a verified tutor.');
    }

    public function reject(Request $request, TutorProfile $tutorProfile): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:500'],
        ], [
            'reason.required' => 'Give the tutor a reason so they know what to fix.',
        ]);

        $this->verification->reject($tutorProfile, $request->user(), $validated['reason']);

        return redirect()->route('admin.verification.index')->with('success', 'Tutor notified.');
    }

    public function suspend(Request $request, TutorProfile $tutorProfile): RedirectResponse
    {
        $validated = $request->validate(['reason' => ['required', 'string', 'max:500']]);

        $this->verification->suspend($tutorProfile, $request->user(), $validated['reason']);

        return back()->with('success', 'Tutor suspended. Their open requests have been returned to the queue.');
    }
}
