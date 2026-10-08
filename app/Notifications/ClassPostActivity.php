<?php

namespace App\Notifications;

use App\Domains\Classroom\Models\ClassroomPost;
use App\Models\User;

/**
 * A question was asked, or someone replied to one.
 *
 * Deliberately narrow: the teacher hears about new questions, and a thread's
 * participants hear about replies. Notifying the whole class every time anyone
 * says anything is how people turn notifications off, and then they miss the
 * ones that matter.
 */
class ClassPostActivity extends PlatformNotification
{
    public function __construct(
        private readonly ClassroomPost $post,
        private readonly string $headline,
        private readonly ?string $actor = null,
    ) {
    }

    public function event(): string
    {
        return 'class_post';
    }

    public function title(User $notifiable): string
    {
        return $this->headline;
    }

    public function body(User $notifiable): string
    {
        $subject = $this->post->title ?? str($this->post->body)->limit(80)->toString();

        return $this->actor ? "{$this->actor}: {$subject}" : $subject;
    }

    public function url(User $notifiable): string
    {
        return route('classrooms.show', $this->post->classroom_id);
    }

    public function actionLabel(): string
    {
        return 'Open the class';
    }
}
