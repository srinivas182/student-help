<?php

namespace App\Notifications;

use App\Models\User;

class TutorVerificationReviewed extends PlatformNotification
{
    public function __construct(
        private readonly bool $approved,
        private readonly ?string $reason = null,
    ) {
    }

    public function event(): string
    {
        return 'tutor_verification';
    }

    public function title(User $notifiable): string
    {
        return $this->approved
            ? 'You are now a verified DX tutor'
            : 'We need more information to verify you';
    }

    public function body(User $notifiable): string
    {
        return $this->approved
            ? 'Your documents have been approved. You will start receiving student requests in the subjects you teach.'
            : 'Your verification could not be completed: '.($this->reason ?? 'please review your documents and resubmit.');
    }

    public function url(User $notifiable): string
    {
        return route('tutor.queue');
    }
}
