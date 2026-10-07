<?php

namespace App\Notifications;

use App\Domains\Identity\Models\GuardianConsent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * POPIA section 35: asks the parent or guardian of a learner under 18 to
 * approve the account (SRS: CON-02).
 *
 * Sent to the guardian's email, not to a platform user, so it is an on-demand
 * notification and cannot be switched off in preferences.
 */
class GuardianConsentRequest extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly GuardianConsent $consent)
    {
        $this->onQueue('urgent');
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $learner = $this->consent->user;

        return (new MailMessage)
            ->subject("Approval needed: {$learner->first_name} wants to join DX Student Help")
            ->greeting("Hi {$this->consent->guardian_name},")
            ->line("{$learner->name} has registered on DX Student Help, a platform that connects South African students with verified tutors for academic help.")
            ->line('Because they are under 18, South African law (POPIA) requires your approval before they can ask tutors for help, send messages, or post in the community.')
            ->line('What we collect: their name, email address, date of birth, and the subjects they study. What we do with it: match them to tutors who teach those subjects and show them relevant study material.')
            ->line('How we keep them safe: all tutors are verified by our team, all conversations stay inside the platform and are visible to our moderators, and contact details are automatically removed from messages.')
            ->action('Approve this account', route('consent.show', ['token' => $this->consent->token]))
            ->line('If you did not expect this email, you can decline using the same link. The link expires in 7 days.')
            ->salutation('DX Student Help');
    }
}
