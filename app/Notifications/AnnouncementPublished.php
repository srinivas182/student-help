<?php

namespace App\Notifications;

use App\Domains\Content\Models\Announcement;
use App\Models\User;

class AnnouncementPublished extends PlatformNotification
{
    public function __construct(private readonly Announcement $announcement)
    {
    }

    public function event(): string
    {
        return 'announcement';
    }

    public function title(User $notifiable): string
    {
        return $this->announcement->title;
    }

    public function body(User $notifiable): string
    {
        return str($this->announcement->body)->limit(180)->toString();
    }

    public function url(User $notifiable): string
    {
        return route('announcements.index');
    }

    public function actionLabel(): string
    {
        return 'Read the announcement';
    }
}
