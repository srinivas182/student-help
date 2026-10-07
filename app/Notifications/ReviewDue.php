<?php

namespace App\Notifications;

use App\Models\User;

class ReviewDue extends PlatformNotification
{
    public function __construct(
        private readonly array $topics,
        private readonly int $count,
    ) {
        parent::__construct();
    }

    public function event(): string
    {
        return 'announcement';
    }

    public function title(User $notifiable): string
    {
        return $this->count === 1
            ? 'One topic is ready for review'
            : "{$this->count} topics are ready for review";
    }

    public function body(User $notifiable): string
    {
        $list = implode(', ', $this->topics);

        return "A few minutes on {$list} today will do more than an hour of rereading before the exam.";
    }

    public function url(User $notifiable): string
    {
        return route('learn.reviews');
    }

    public function actionLabel(): string
    {
        return 'Start reviewing';
    }
}
