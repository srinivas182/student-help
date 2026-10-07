<?php

namespace App\Domains\Security\Services;

use App\Models\User;

/**
 * Who needs 2FA and which methods are allowed — all admin-controlled.
 *
 * Students are never required: a locked-out learner the night before an exam is
 * a support burden with no upside, and their account holds homework questions
 * rather than money or other people's data.
 */
class TwoFactorSettings
{
    public const METHOD_APP = 'app';
    public const METHOD_EMAIL = 'email';
    public const METHOD_SMS = 'sms';

    public function primaryMethod(): string
    {
        return (string) setting('2fa_primary_method', self::METHOD_APP);
    }

    /** @return array<int, string> */
    public function backupMethods(): array
    {
        return (array) setting('2fa_backup_methods', [self::METHOD_EMAIL]);
    }

    /** @return array<int, string> */
    public function requiredRoles(): array
    {
        return (array) setting('2fa_required_roles', [
            User::ROLE_SUPER_ADMIN,
            User::ROLE_ADMIN,
            User::ROLE_MODERATOR,
        ]);
    }

    public function isRequiredFor(User $user): bool
    {
        if ($user->isStudent()) {
            return false;
        }

        return in_array($user->role, $this->requiredRoles(), true);
    }

    public function trustedDeviceDays(): int
    {
        return (int) setting('2fa_trusted_device_days', 30);
    }

    public function bypassHours(): int
    {
        return (int) setting('2fa_bypass_hours', 24);
    }

    public function isMethodAvailable(string $method, GatewaySettings $gateways): bool
    {
        return match ($method) {
            self::METHOD_APP => true,
            self::METHOD_EMAIL => $gateways->emailEnabled(),
            self::METHOD_SMS => $gateways->smsEnabled(),
            default => false,
        };
    }
}
