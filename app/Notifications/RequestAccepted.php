<?php

namespace App\Notifications;

use App\Domains\Tutoring\Models\HelpRequest;
use App\Models\User;

class RequestAccepted extends PlatformNotification
{
    public function __construct(private readonly HelpRequest $request)
    {
    }

    public function event(): string
    {
        return 'request_accepted';
    }

    public function title(User $notifiable): string
    {
        return 'A tutor has accepted your request';
    }

    public function body(User $notifiable): string
    {
        $tutor = $this->request->tutor?->first_name ?? 'A tutor';

        return "{$tutor} has picked up your question on \"{$this->request->topic}\" and will help you shortly.";
    }

    public function url(User $notifiable): string
    {
        return route('conversations.show', $this->request);
    }

    public function actionLabel(): string
    {
        return 'Open the conversation';
    }
}
