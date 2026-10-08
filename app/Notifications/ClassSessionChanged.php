<?php

namespace App\Notifications;

use App\Domains\Classroom\Models\ClassSession;
use App\Models\User;

/**
 * A session was scheduled, moved or cancelled.
 *
 * Cancelling silently is worse than never scheduling: a learner turns up to an
 * empty room or waits on a call that never starts.
 */
class ClassSessionChanged extends PlatformNotification
{
    public function __construct(
        private readonly ClassSession $session,
        private readonly string $headline,
        private readonly ?string $detail = null,
    ) {
    }

    public function event(): string
    {
        return 'class_session';
    }

    public function title(User $notifiable): string
    {
        return $this->headline;
    }

    public function body(User $notifiable): string
    {
        $base = "{$this->session->title} · {$this->session->whenLabel()}";

        if ($this->session->mode === ClassSession::MODE_IN_PERSON && $this->session->location) {
            $base .= " · {$this->session->location}";
        }

        return $this->detail ? "{$base}. {$this->detail}" : $base;
    }

    public function url(User $notifiable): string
    {
        return route('classrooms.show', $this->session->classroom_id);
    }

    public function actionLabel(): string
    {
        return 'See the class';
    }
}
