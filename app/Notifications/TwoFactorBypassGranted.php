<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Visibility is the point: a quiet bypass is the dangerous kind. */
class TwoFactorBypassGranted extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $subjectName,
        private readonly string $actorName,
        private readonly string $reason,
        private readonly string $until,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Two-factor bypass granted for {$this->subjectName}")
            ->line("{$this->actorName} granted a temporary two-factor bypass for {$this->subjectName}.")
            ->line("Reason given: {$this->reason}")
            ->line("It expires at {$this->until}, and they must set up two-factor again.")
            ->line('If you did not expect this, investigate immediately.')
            ->salutation('DX Student Help');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'event' => 'security',
            'title' => "Two-factor bypass granted for {$this->subjectName}",
            'body' => "By {$this->actorName}. Expires {$this->until}.",
            'url' => route('admin.security'),
        ];
    }
}
