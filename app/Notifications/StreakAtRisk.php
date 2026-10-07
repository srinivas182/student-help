<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * A nudge, not a guilt trip. In-app only — nobody needs an email telling them
 * they missed a day of studying.
 */
class StreakAtRisk extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly int $days)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'event' => 'streak',
            'title' => "You are on a {$this->days}-day streak",
            'body' => 'A few minutes of revision today keeps it going.',
            'url' => route('learn.reviews'),
        ];
    }
}
