<?php

namespace App\Notifications;

use App\Domains\Tutoring\Models\HelpRequest;
use App\Models\User;

class RequestOffered extends PlatformNotification
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
        return 'A student needs help with '.($this->request->subject?->name ?? 'a subject you teach');
    }

    public function body(User $notifiable): string
    {
        return "\"{$this->request->topic}\" is waiting for a tutor. First to accept takes it.";
    }

    public function url(User $notifiable): string
    {
        return route('tutor.queue');
    }

    public function actionLabel(): string
    {
        return 'View the request';
    }
}
