<?php

namespace App\Domains\Identity\Services;

use App\Domains\Identity\Models\GuardianConsent;
use App\Models\User;
use App\Notifications\GuardianConsentDecided;
use App\Notifications\GuardianConsentRequest;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

class ConsentService
{
    public const LINK_DAYS = 7;

    public function request(User $minor, array $guardian): GuardianConsent
    {
        $consent = GuardianConsent::updateOrCreate(
            ['user_id' => $minor->id],
            [
                'guardian_name' => $guardian['guardian_name'],
                'guardian_email' => $guardian['guardian_email'],
                'guardian_mobile' => $guardian['guardian_mobile'] ?? null,
                'token' => Str::random(64),
                'status' => GuardianConsent::STATUS_PENDING,
                'requested_at' => now(),
                'decided_at' => null,
                'policy_version' => (string) setting('policy_version', '1.0'),
            ],
        );

        $this->send($consent);

        return $consent;
    }

    public function send(GuardianConsent $consent): void
    {
        Notification::route('mail', $consent->guardian_email)
            ->notify(new GuardianConsentRequest($consent));

        audit('consent.requested', $consent, ['user_id' => $consent->user_id]);
    }

    public function isLinkValid(GuardianConsent $consent): bool
    {
        return $consent->requested_at !== null
            && $consent->requested_at->gt(now()->subDays(self::LINK_DAYS));
    }

    public function decide(GuardianConsent $consent, bool $approved, ?string $ip = null): void
    {
        $consent->update([
            'status' => $approved ? GuardianConsent::STATUS_APPROVED : GuardianConsent::STATUS_DECLINED,
            'decided_at' => now(),
            'decision_ip' => $ip,
        ]);

        $consent->user->notify(new GuardianConsentDecided($consent));

        audit('consent.'.($approved ? 'approved' : 'declined'), $consent, [
            'user_id' => $consent->user_id,
            'policy_version' => $consent->policy_version,
        ]);
    }

    /** CON-05: a guardian can change their mind later. */
    public function withdraw(GuardianConsent $consent): void
    {
        $consent->update([
            'status' => GuardianConsent::STATUS_WITHDRAWN,
            'decided_at' => now(),
        ]);

        audit('consent.withdrawn', $consent, ['user_id' => $consent->user_id]);
    }
}
