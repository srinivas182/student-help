<?php

namespace App\Notifications;

use App\Domains\Tutoring\Models\HelpRequest;
use App\Models\User;

class RequestResolved extends PlatformNotification
{
    public function __construct(private readonly HelpRequest $request)
    {
    }

    public function event(): string
    {
        return 'request_resolved';
    }

    public function title(User $notifiable): string
    {
        return 'Your tutor has answered your question';
    }

    public function body(User $notifiable): string
    {
        $hours = (int) setting('auto_close_hours_after_resolved', 72);

        return "\"{$this->request->topic}\" has been marked as answered. Let us know if it helped, or reopen it if you are still stuck. It closes automatically after {$hours} hours.";
    }

    public function url(User $notifiable): string
    {
        return route('conversations.show', $this->request);
    }
}
