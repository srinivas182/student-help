<?php

namespace App\Notifications;

use App\Domains\Tutoring\Models\Message;
use App\Models\User;

class NewMessage extends PlatformNotification
{
    public function __construct(private readonly Message $message)
    {
    }

    public function event(): string
    {
        return 'new_message';
    }

    public function title(User $notifiable): string
    {
        return 'New message from '.($this->message->sender?->first_name ?? 'DX Student Help');
    }

    public function body(User $notifiable): string
    {
        // The masked body only — never the original text.
        return str($this->message->body)->limit(120)->toString();
    }

    public function url(User $notifiable): string
    {
        return route('conversations.show', $this->message->help_request_id);
    }

    public function actionLabel(): string
    {
        return 'Reply';
    }
}
