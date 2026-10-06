<?php

namespace App\Notifications;

use App\Domains\Tutoring\Models\HelpRequest;
use App\Models\User;

/** Sent to administrators when nobody accepted a request in time (REQ-06). */
class RequestEscalated extends PlatformNotification
{
    public function __construct(private readonly HelpRequest $request)
    {
    }

    public function event(): string
    {
        return 'request_escalated';
    }

    public function title(User $notifiable): string
    {
        return 'A help request needs a tutor assigned';
    }

    public function body(User $notifiable): string
    {
        $subject = $this->request->subject?->name ?? 'Unknown subject';

        return "\"{$this->request->topic}\" ({$subject}) was not accepted in time. Please assign a tutor.";
    }

    public function url(User $notifiable): string
    {
        return route('requests.show', $this->request);
    }
}
