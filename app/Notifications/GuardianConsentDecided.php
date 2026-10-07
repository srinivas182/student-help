<?php

namespace App\Notifications;

use App\Domains\Identity\Models\GuardianConsent;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Tells the learner whether their guardian approved (CON-02, CON-06). */
class GuardianConsentDecided extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly GuardianConsent $consent)
    {
        $this->onQueue('urgent');
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        if ($this->consent->isApproved()) {
            return (new MailMessage)
                ->subject('Your DX Student Help account is now fully active')
                ->greeting("Hi {$notifiable->first_name},")
                ->line("{$this->consent->guardian_name} has approved your account. You can now ask tutors for help, send messages and join the community.")
                ->action('Ask your first question', route('requests.create'))
                ->salutation('DX Student Help');
        }

        return (new MailMessage)
            ->subject('Your DX Student Help account needs guardian approval')
            ->greeting("Hi {$notifiable->first_name},")
            ->line('Your parent or guardian has not approved your account yet, so you can browse study resources but cannot message tutors.')
            ->line('You can send the approval request again from your profile at any time.')
            ->action('Open your profile', route('profile.edit'))
            ->salutation('DX Student Help');
    }

    public function toArray(User $notifiable): array
    {
        return [
            'event' => 'consent',
            'title' => $this->consent->isApproved()
                ? 'Your guardian approved your account'
                : 'Your guardian declined the request',
            'body' => $this->consent->isApproved()
                ? 'You can now ask for help, message tutors and join the community.'
                : 'You can browse resources. Send the request again from your profile when ready.',
            'url' => route('dashboard'),
        ];
    }
}
