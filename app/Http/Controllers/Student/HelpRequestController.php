<?php

namespace App\Http\Controllers\Student;

use App\Domains\Tutoring\Models\HelpRequest;
use App\Domains\Tutoring\Services\HelpRequestService;
use App\Domains\Tutoring\Services\MatchingService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class HelpRequestController extends Controller
{
    public function __construct(
        private readonly HelpRequestService $requests,
        private readonly MatchingService $matching,
    ) {
    }

    public function index(Request $request): Response
    {
        $user = $request->user();

        $list = HelpRequest::where('student_id', $user->id)
            ->with(['subject:id,name', 'tutor:id,first_name,last_name'])
            ->when($request->string('status')->toString(), fn ($q, $status) => $q->where('status', $status))
            ->latest('last_activity_at')
            ->paginate(10)
            ->withQueryString()
            ->through(fn (HelpRequest $r) => $this->summary($r));

        return Inertia::render('Requests/Index', [
            'requests' => $list,
            'filters' => ['status' => $request->string('status')->toString()],
            'canRaise' => $user->canParticipate(),
        ]);
    }

    public function create(Request $request): Response
    {
        $user = $request->user();

        $subjects = $user->subjects()->get(['curriculum_items.id', 'name'])
            ->map(fn ($subject) => [
                'id' => $subject->id,
                'name' => $subject->name,
                'tutors' => $this->matching->coverage($subject),
                'typicalHours' => $this->matching->typicalAcceptanceHours($subject),
            ]);

        return Inertia::render('Requests/Create', [
            'subjects' => $subjects,
            'maxOpen' => (int) setting('max_open_requests_per_student', 3),
            'openCount' => HelpRequest::where('student_id', $user->id)
                ->whereIn('status', [HelpRequest::STATUS_OPEN, HelpRequest::STATUS_ESCALATED, HelpRequest::STATUS_ASSIGNED])
                ->count(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'subject_id' => [
                'required', 'integer',
                // The subject must be one the student actually selected (REQ-01).
                Rule::exists('academic_selections', 'curriculum_item_id')
                    ->where('user_id', $user->id)
                    ->where('role', 'subject'),
            ],
            'topic' => ['required', 'string', 'min:5', 'max:150'],
            'description' => ['required', 'string', 'min:20', 'max:2000'],
            'academic_honesty' => ['accepted'],
        ], [
            'subject_id.exists' => 'Choose a subject from your profile.',
            'academic_honesty.accepted' => 'Please confirm you are asking for help to understand the work.',
        ]);

        $helpRequest = $this->requests->create($user, $validated);

        return redirect()
            ->route('requests.show', $helpRequest)
            ->with('success', 'Your request has been sent to tutors who teach this subject.');
    }

    public function show(Request $request, HelpRequest $helpRequest): Response
    {
        abort_unless($helpRequest->student_id === $request->user()->id, 403);

        $helpRequest->load(['subject:id,name', 'tutor:id,first_name,last_name', 'tutor.tutorProfile']);

        return Inertia::render('Requests/Show', [
            'request' => $this->summary($helpRequest) + [
                'description' => $helpRequest->description,
                'tutorBio' => $helpRequest->tutor?->tutorProfile?->bio,
                'tutorRating' => $helpRequest->tutor?->tutorProfile?->average_rating,
                'offersSent' => $helpRequest->offers()->count(),
            ],
        ]);
    }

    public function cancel(Request $request, HelpRequest $helpRequest): RedirectResponse
    {
        abort_unless($helpRequest->student_id === $request->user()->id, 403);
        abort_unless($helpRequest->isActive(), 422);

        $this->requests->cancel($helpRequest, $request->user());

        return redirect()->route('requests.index')->with('success', 'Request cancelled.');
    }

    public function confirm(Request $request, HelpRequest $helpRequest): RedirectResponse
    {
        abort_unless($helpRequest->student_id === $request->user()->id, 403);
        abort_unless($helpRequest->status === HelpRequest::STATUS_RESOLVED, 422);

        $this->requests->close($helpRequest);

        return back()->with('success', 'Great. You can now rate your tutor.');
    }

    public function reopen(Request $request, HelpRequest $helpRequest): RedirectResponse
    {
        abort_unless($helpRequest->student_id === $request->user()->id, 403);
        abort_unless($helpRequest->status === HelpRequest::STATUS_RESOLVED, 422);

        $this->requests->reopen($helpRequest);

        return back()->with('success', 'Reopened. Your tutor has been notified.');
    }

    private function summary(HelpRequest $r): array
    {
        return [
            'id' => $r->id,
            'topic' => $r->topic,
            'subject' => $r->subject?->name,
            'status' => $r->status,
            'tutor' => $r->tutor?->name,
            'createdAt' => $r->created_at?->diffForHumans(),
            'updatedAt' => $r->last_activity_at?->diffForHumans(),
        ];
    }
}
