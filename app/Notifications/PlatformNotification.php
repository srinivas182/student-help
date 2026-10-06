<?php

namespace App\Notifications;

use App\Domains\Notifications\NotificationPreferences;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Base for every platform notification.
 *
 * Subclasses declare their event key, title, body and link; this class decides
 * the channels from the user's preferences, so adding SMS or WhatsApp later is
 * one new channel here rather than a change in every notification.
 */
abstract class PlatformNotification extends Notification implements ShouldQueue
{
    use Queueable;

    abstract public function event(): string;

    abstract public function title(User $notifiable): string;

    abstract public function body(User $notifiable): string;

    abstract public function url(User $notifiable): string;

    public function actionLabel(): string
    {
        return 'Open DX Student Help';
    }

    public function via(User $notifiable): array
    {
        return app(NotificationPreferences::class)->channelsFor($notifiable, $this->event());
    }

    public function toMail(User $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->title($notifiable))
            ->greeting('Hi '.($notifiable->first_name ?: $notifiable->name).',')
            ->line($this->body($notifiable))
            ->action($this->actionLabel(), $this->url($notifiable))
            ->salutation('DX Student Help');
    }

    public function toArray(User $notifiable): array
    {
        return [
            'event' => $this->event(),
            'title' => $this->title($notifiable),
            'body' => $this->body($notifiable),
            'url' => $this->url($notifiable),
        ];
    }
}
