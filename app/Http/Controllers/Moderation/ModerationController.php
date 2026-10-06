<?php

namespace App\Http\Controllers\Moderation;

use App\Domains\Tutoring\Models\HelpRequest;
use App\Domains\Tutoring\Models\Message;
use App\Domains\Tutoring\Models\Report;
use App\Domains\Tutoring\Services\MessageService;
use App\Domains\Tutoring\Services\ModerationService;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ModerationController extends Controller
{
    public function __construct(
        private readonly ModerationService $moderation,
        private readonly MessageService $messages,
    ) {
    }

    /** MOD-01: queue ordered by severity, then age. */
    public function index(Request $request): Response
    {
        $reports = Report::query()
            ->when($request->string('status')->toString() !== 'closed',
                fn ($q) => $q->where('status', Report::STATUS_OPEN),
                fn ($q) => $q->where('status', Report::STATUS_CLOSED))
            ->with('reporter:id,first_name,last_name,role')
            ->orderByRaw("case when severity = 'high' then 0 else 1 end")
            ->oldest()
            ->paginate(15)
            ->through(fn (Report $report) => [
                'id' => $report->id,
                'reason' => $report->reason,
                'notes' => $report->notes,
                'severity' => $report->severity,
                'status' => $report->status,
                'reporter' => $report->reporter?->name,
                'type' => class_basename($report->reportable_type),
                'subjectId' => $report->reportable_id,
                'raised' => $report->created_at?->diffForHumans(),
                'outcome' => $report->outcome,
            ]);

        return Inertia::render('Moderation/Index', [
            'reports' => $reports,
            'filters' => ['status' => $request->string('status')->toString()],
            'openCount' => Report::where('status', Report::STATUS_OPEN)->count(),
            'highPriorityCount' => Report::where('status', Report::STATUS_OPEN)
                ->where('severity', Report::SEVERITY_HIGH)->count(),
        ]);
    }

    /** MSG-06: full, unmasked conversation for a moderator; the view is logged. */
    public function conversation(Request $request, HelpRequest $helpRequest): Response
    {
        return Inertia::render('Moderation/Conversation', [
            'request' => [
                'id' => $helpRequest->id,
                'topic' => $helpRequest->topic,
                'status' => $helpRequest->status,
                'student' => $helpRequest->student?->name,
                'studentIsMinor' => $helpRequest->student?->isMinor(),
                'tutor' => $helpRequest->tutor?->name,
            ],
            'messages' => $this->messages->moderatorView($helpRequest, $request->user()),
        ]);
    }

    public function resolve(Request $request, Report $report): RedirectResponse
    {
        $validated = $request->validate([
            'action' => ['required', 'in:dismiss,remove_content,warn_user,suspend_user'],
            'outcome' => ['required', 'string', 'max:500'],
        ]);

        if ($validated['action'] === 'remove_content' && $report->reportable_type === Message::class) {
            Message::whereKey($report->reportable_id)->update([
                'body' => '[removed by a moderator]',
                'is_flagged' => true,
            ]);
        }

        if ($validated['action'] === 'suspend_user') {
            $target = $report->reportable_type === Message::class
                ? Message::find($report->reportable_id)?->sender
                : User::find($report->reportable_id);

            if ($target) {
                $this->moderation->suspendUser($target, $request->user(), $validated['outcome']);
            }
        }

        $this->moderation->resolve($report, $request->user(), $validated['action'], $validated['outcome']);

        return back()->with('success', 'Report closed.');
    }
}
