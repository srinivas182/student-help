<?php

namespace App\Domains\Notifications;

use App\Models\User;

/**
 * Which channels a user wants, per event (SRS: NOT-02, NOT-03).
 *
 * The channel list is deliberately open: SMS and WhatsApp drivers can be added
 * in a later phase without touching any notification class, because every
 * notification asks this class which channels to use.
 */
class NotificationPreferences
{
    public const EVENTS = [
        'request_accepted' => 'A tutor accepts your request',
        'request_escalated' => 'Your request is escalated to the DX team',
        'new_message' => 'You receive a new message',
        'request_resolved' => 'A tutor marks your request resolved',
        'rating_received' => 'A student rates your help',
        'tutor_verification' => 'Your tutor verification is reviewed',
        'announcement' => 'Important announcements',
        'class_session' => 'A class session is scheduled, moved or cancelled',
        'class_post' => 'Someone asks or answers in one of your classes',
    ];

    /** Channels that cannot be disabled — account, security and consent email. */
    public const ALWAYS_ON = ['consent', 'security', 'account'];

    private const DEFAULTS = ['database' => true, 'mail' => true];

    public function channelsFor(User $user, string $event): array
    {
        if (in_array($event, self::ALWAYS_ON, true)) {
            return ['mail'];
        }

        $preferences = $user->notification_preferences[$event] ?? self::DEFAULTS;

        $channels = [];

        if ($preferences['database'] ?? true) {
            $channels[] = 'database';
        }

        if (($preferences['mail'] ?? true) && $user->email_verified_at !== null) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    /** @return array<string, array<string, bool>> */
    public function all(User $user): array
    {
        $stored = $user->notification_preferences ?? [];

        return collect(self::EVENTS)
            ->mapWithKeys(fn ($label, $event) => [$event => [
                'label' => $label,
                'database' => $stored[$event]['database'] ?? true,
                'mail' => $stored[$event]['mail'] ?? true,
            ]])
            ->all();
    }
}
