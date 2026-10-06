<?php

namespace App\Notifications;

use App\Domains\Tutoring\Models\Rating;
use App\Models\User;

class RatingReceived extends PlatformNotification
{
    public function __construct(private readonly Rating $rating)
    {
    }

    public function event(): string
    {
        return 'rating_received';
    }

    public function title(User $notifiable): string
    {
        return "You received a {$this->rating->stars}-star rating";
    }

    public function body(User $notifiable): string
    {
        return $this->rating->comment
            ? "A student rated your help {$this->rating->stars} out of 5: \"{$this->rating->comment}\""
            : "A student rated your help {$this->rating->stars} out of 5.";
    }

    public function url(User $notifiable): string
    {
        return route('tutor.queue');
    }
}
