<?php

namespace App\Http\Controllers\Tutor;

use App\Domains\Tutoring\Models\HelpRequest;
use App\Domains\Tutoring\Models\HelpRequestOffer;
use App\Domains\Tutoring\Services\HelpRequestService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RequestQueueController extends Controller
{
    public function __construct(private readonly HelpRequestService $requests)
    {
    }

    public function index(Request $request): Response
    {
        $tutor = $request->user();

        $available = HelpRequest::query()
            ->whereNull('tutor_id')
            ->openForMatching()
            ->whereHas('offers', fn ($q) => $q
                ->where('tutor_id', $tutor->id)
                ->where('status', HelpRequestOffer::STATUS_OFFERED))
            ->with(['subject:id,name', 'student:id,first_name,last_name'])
            ->latest()
            ->get()
            ->map(fn (HelpRequest $r) => [
                'id' => $r->id,
                'topic' => $r->topic,
                'description' => str($r->description)->limit(180)->toString(),
                'subject' => $r->subject?->name,
                'student' => $r->student?->first_name,
                'waiting' => $r->created_at?->diffForHumans(null, true),
                'isEscalated' => $r->status === HelpRequest::STATUS_ESCALATED,
            ]);

        $active = HelpRequest::where('tutor_id', $tutor->id)
            ->whereIn('status', [HelpRequest::STATUS_ASSIGNED, HelpRequest::STATUS_RESOLVED])
            ->with(['subject:id,name', 'student:id,first_name,last_name'])
            ->latest('last_activity_at')
            ->get()
            ->map(fn (HelpRequest $r) => [
                'id' => $r->id,
                'topic' => $r->topic,
                'subject' => $r->subject?->name,
                'student' => $r->student?->first_name,
                'status' => $r->status,
                'updatedAt' => $r->last_activity_at?->diffForHumans(),
            ]);

        return Inertia::render('Tutor/Queue', [
            'available' => $available,
            'active' => $active,
            'isAvailable' => (bool) ($tutor->tutorProfile?->is_available ?? false),
            'isVerified' => $tutor->isVerifiedTutor(),
            'stats' => [
                'resolved' => $tutor->tutorProfile?->resolved_count ?? 0,
                'rating' => $tutor->tutorProfile?->average_rating,
            ],
        ]);
    }

    public function accept(Request $request, HelpRequest $helpRequest): RedirectResponse
    {
        $this->requests->accept($helpRequest, $request->user());

        return redirect()->route('tutor.queue')->with('success', 'Request accepted. You can now help the student.');
    }

    public function decline(Request $request, HelpRequest $helpRequest): RedirectResponse
    {
        $validated = $request->validate(['reason' => ['nullable', 'string', 'max:150']]);

        $this->requests->decline($helpRequest, $request->user(), $validated['reason'] ?? null);

        return back()->with('success', 'Request declined.');
    }

    public function resolve(Request $request, HelpRequest $helpRequest): RedirectResponse
    {
        $this->requests->resolve($helpRequest, $request->user());

        return back()->with('success', 'Marked as resolved. The student will confirm.');
    }

    public function toggleAvailability(Request $request): RedirectResponse
    {
        $profile = $request->user()->tutorProfile;

        abort_unless($profile !== null, 404);

        $profile->update(['is_available' => ! $profile->is_available]);

        return back();
    }
}
