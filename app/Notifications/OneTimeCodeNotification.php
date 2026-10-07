<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Sent immediately rather than queued — a code the user is waiting for. */
class OneTimeCodeNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $code,
        private readonly string $message,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Your DX Student Help code: {$this->code}")
            ->line($this->message)
            ->line('Never share this code with anyone. DX will never ask you for it.')
            ->salutation('DX Student Help');
    }
}
