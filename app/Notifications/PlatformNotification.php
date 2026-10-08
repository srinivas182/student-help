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

    public function __construct()
    {
        $this->onQueue('notifications');
    }

    abstract public function event(): string;

    /**
     * In-app notifications are written straight away rather than queued.
     *
     * They are a single database insert, so queuing buys nothing — and if the
     * queue worker is not running, a queued in-app notification simply never
     * arrives, silently. Email still queues, because sending it is slow and a
     * failed send should be retried.
     */
    public function viaConnections(): array
    {
        return ['database' => 'sync'];
    }

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
