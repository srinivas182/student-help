<?php

namespace App\Notifications;

use App\Domains\Identity\Models\AdminInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AdminInvited extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly AdminInvitation $invitation)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $role = str_replace('_', ' ', $this->invitation->role);

        return (new MailMessage)
            ->subject('You have been invited to DX Student Help')
            ->greeting("Hi {$this->invitation->name},")
            ->line("{$this->invitation->invitedBy?->name} has invited you to join DX Student Help as a {$role}.")
            ->line('Set your password using the link below. The invitation expires in 7 days.')
            ->action('Set up my account', route('invitations.show', ['token' => $this->invitation->token]))
            ->line('If you were not expecting this invitation, you can ignore this email.')
            ->salutation('DX Student Help');
    }
}
