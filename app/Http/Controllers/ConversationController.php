<?php

namespace App\Http\Controllers;

use App\Domains\Tutoring\Models\HelpRequest;
use App\Domains\Tutoring\Models\Message;
use App\Domains\Tutoring\Services\MessageService;
use App\Domains\Tutoring\Services\ModerationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ConversationController extends Controller
{
    public function __construct(
        private readonly MessageService $messages,
        private readonly ModerationService $moderation,
    ) {
    }

    public function show(Request $request, HelpRequest $helpRequest): Response
    {
        $user = $request->user();

        abort_unless(in_array($user->id, [$helpRequest->student_id, $helpRequest->tutor_id], true), 403);

        $helpRequest->load(['subject:id,name', 'student:id,first_name,last_name', 'tutor:id,first_name,last_name', 'rating']);
        $this->messages->markRead($helpRequest, $user);

        $counterpart = $user->id === $helpRequest->student_id ? $helpRequest->tutor : $helpRequest->student;

        return Inertia::render('Conversation/Show', [
            'request' => [
                'id' => $helpRequest->id,
                'topic' => $helpRequest->topic,
                'subject' => $helpRequest->subject?->name,
                'description' => $helpRequest->description,
                'status' => $helpRequest->status,
                'isOpen' => $helpRequest->isConversationOpen(),
            ],
            'counterpart' => $counterpart ? [
                'name' => $counterpart->name,
                'initial' => mb_substr($counterpart->first_name ?? $counterpart->name, 0, 1),
            ] : null,
            'messages' => $this->serialise($helpRequest, $user->id),
            'isTutor' => $user->id === $helpRequest->tutor_id,
            'canPost' => $user->canParticipate() && $helpRequest->isConversationOpen(),
            'rating' => $helpRequest->rating ? [
                'stars' => $helpRequest->rating->stars,
                'comment' => $helpRequest->rating->comment,
            ] : null,
        ]);
    }

    /** Polled by the client so messages appear without a page reload (MSG-02). */
    public function poll(Request $request, HelpRequest $helpRequest): JsonResponse
    {
        $user = $request->user();

        abort_unless(in_array($user->id, [$helpRequest->student_id, $helpRequest->tutor_id], true), 403);

        $this->messages->markRead($helpRequest, $user);

        return response()->json([
            'messages' => $this->serialise($helpRequest, $user->id),
            'status' => $helpRequest->status,
        ]);
    }

    public function store(Request $request, HelpRequest $helpRequest): RedirectResponse
    {
        $validated = $request->validate([
            'body' => ['required', 'string', 'min:1', 'max:2000'],
        ]);

        $message = $this->messages->send($helpRequest, $request->user(), $validated['body']);

        return back()->with(
            $message->body !== $message->body_original ? 'warning' : 'success',
            $message->body !== $message->body_original
                ? 'Sent. Contact details were removed — DX keeps conversations on the platform to keep learners safe.'
                : 'Message sent.',
        );
    }

    public function report(Request $request, HelpRequest $helpRequest, Message $message): RedirectResponse
    {
        abort_unless($message->help_request_id === $helpRequest->id, 404);
        abort_unless(in_array($request->user()->id, [$helpRequest->student_id, $helpRequest->tutor_id], true), 403);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'in:inappropriate,contact_details,academic_dishonesty,harassment,spam,other'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $this->moderation->report($request->user(), $message, $validated['reason'], $validated['notes'] ?? null);

        return back()->with('success', 'Thank you. A moderator will review this within 24 hours.');
    }

    private function serialise(HelpRequest $helpRequest, int $viewerId): array
    {
        return $helpRequest->messages()->with('sender:id,first_name,last_name')->get()
            ->map(fn (Message $m) => [
                'id' => $m->id,
                'body' => $m->body,
                'sender' => $m->sender?->first_name,
                'isMine' => $m->sender_id === $viewerId,
                'sentAt' => $m->created_at?->format('H:i'),
                'sentOn' => $m->created_at?->format('D j M'),
                'readAt' => $m->read_at?->format('H:i'),
            ])->all();
    }
}
