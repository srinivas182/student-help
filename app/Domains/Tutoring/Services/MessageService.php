<?php

namespace App\Domains\Tutoring\Services;

use App\Domains\Tutoring\Models\HelpRequest;
use App\Domains\Tutoring\Models\Message;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class MessageService
{
    public function __construct(private readonly ContentFilter $filter)
    {
    }

    public function send(HelpRequest $request, User $sender, string $body): Message
    {
        $this->assertCanPost($request, $sender);

        $filtered = $this->filter->mask($body);

        $message = Message::create([
            'help_request_id' => $request->id,
            'sender_id' => $sender->id,
            'body' => $filtered['body'],
            'body_original' => $body,
            'is_flagged' => $this->filter->shouldFlag($body),
        ]);

        $request->update(['last_activity_at' => now()]);

        if ($message->is_flagged) {
            audit('message.flagged', $message, ['help_request_id' => $request->id]);
        }

        return $message;
    }

    /**
     * Only the student and the assigned tutor may post, only while the
     * conversation is open, and a minor needs guardian consent first.
     */
    public function assertCanPost(HelpRequest $request, User $user): void
    {
        $isParticipant = in_array($user->id, [$request->student_id, $request->tutor_id], true);

        if (! $isParticipant) {
            throw ValidationException::withMessages(['body' => 'You are not part of this conversation.']);
        }

        if (! $request->isConversationOpen()) {
            throw ValidationException::withMessages(['body' => 'This conversation is closed.']);
        }

        if (! $user->canParticipate()) {
            throw ValidationException::withMessages([
                'body' => 'A parent or guardian needs to approve your account before you can send messages.',
            ]);
        }
    }

    public function markRead(HelpRequest $request, User $reader): void
    {
        Message::where('help_request_id', $request->id)
            ->where('sender_id', '!=', $reader->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    /** MSG-06: moderators may read anything, and every read is recorded. */
    public function moderatorView(HelpRequest $request, User $moderator): array
    {
        audit('conversation.viewed_by_moderator', $request, ['moderator_id' => $moderator->id]);

        return $request->messages()->with('sender:id,first_name,last_name,role')->get()
            ->map(fn (Message $m) => [
                'id' => $m->id,
                'sender' => $m->sender?->name,
                'role' => $m->sender?->role,
                'body' => $m->body,
                'original' => $m->body_original,
                'wasMasked' => $m->body !== $m->body_original,
                'isFlagged' => $m->is_flagged,
                'sentAt' => $m->created_at?->toDayDateTimeString(),
            ])->all();
    }
}
