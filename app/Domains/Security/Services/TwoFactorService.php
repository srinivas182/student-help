<?php

namespace App\Domains\Security\Services;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorService
{
    public const RECOVERY_CODE_COUNT = 8;

    public function __construct(
        private readonly Google2FA $google2fa,
        private readonly TwoFactorSettings $settings,
    ) {
    }

    /** @return array{secret: string, qr: string, recovery: array<int, string>} */
    public function beginEnrolment(User $user): array
    {
        $secret = $this->google2fa->generateSecretKey();
        $recovery = $this->freshRecoveryCodes();

        $user->forceFill([
            'two_factor_secret' => Crypt::encryptString($secret),
            'two_factor_recovery_codes' => Crypt::encryptString(json_encode($recovery)),
            'two_factor_confirmed_at' => null,
        ])->save();

        return [
            'secret' => $secret,
            'qr' => $this->google2fa->getQRCodeUrl(
                config('app.name', 'DX Student Help'),
                $user->email,
                $secret,
            ),
            'recovery' => $recovery,
        ];
    }

    public function confirm(User $user, string $code): void
    {
        if (! $this->verifyAppCode($user, $code)) {
            throw ValidationException::withMessages(['code' => 'That code did not work. Check your app and try again.']);
        }

        $user->forceFill(['two_factor_confirmed_at' => now()])->save();

        audit('2fa.enabled', $user);
    }

    public function isEnabled(User $user): bool
    {
        return $user->two_factor_secret !== null && $user->two_factor_confirmed_at !== null;
    }

    public function verifyAppCode(User $user, string $code): bool
    {
        if (! $user->two_factor_secret) {
            return false;
        }

        return $this->google2fa->verifyKey(Crypt::decryptString($user->two_factor_secret), $code);
    }

    /** Recovery codes are single use: each one is burned on success. */
    public function verifyRecoveryCode(User $user, string $code): bool
    {
        $codes = $this->recoveryCodes($user);

        $index = array_search(strtoupper(trim($code)), $codes, true);

        if ($index === false) {
            return false;
        }

        unset($codes[$index]);

        $user->forceFill([
            'two_factor_recovery_codes' => Crypt::encryptString(json_encode(array_values($codes))),
        ])->save();

        audit('2fa.recovery_code_used', $user, ['remaining' => count($codes)]);

        return true;
    }

    /** @return array<int, string> */
    public function recoveryCodes(User $user): array
    {
        if (! $user->two_factor_recovery_codes) {
            return [];
        }

        return json_decode(Crypt::decryptString($user->two_factor_recovery_codes), true) ?? [];
    }

    public function regenerateRecoveryCodes(User $user): array
    {
        $codes = $this->freshRecoveryCodes();

        $user->forceFill([
            'two_factor_recovery_codes' => Crypt::encryptString(json_encode($codes)),
        ])->save();

        audit('2fa.recovery_codes_regenerated', $user);

        return $codes;
    }

    /**
     * A time-limited bypass, not a permanent switch. The user must re-enrol, the
     * bypass expires by itself, and everyone who can see the audit log knows.
     */
    public function grantBypass(User $user, User $actor, string $reason): Carbon
    {
        $until = now()->addHours($this->settings->bypassHours());

        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
            'two_factor_bypass_until' => $until,
            'two_factor_bypass_reason' => $reason,
        ])->save();

        audit('2fa.bypass_granted', $user, [
            'actor_id' => $actor->id,
            'reason' => $reason,
            'until' => $until->toDateTimeString(),
        ]);

        return $until;
    }

    public function hasActiveBypass(User $user): bool
    {
        return $user->two_factor_bypass_until !== null
            && $user->two_factor_bypass_until->isFuture();
    }

    /**
     * Break-glass protection: the platform must never be left without a
     * protected super administrator.
     */
    public function assertNotLastProtectedAdmin(User $user): void
    {
        if ($user->role !== User::ROLE_SUPER_ADMIN) {
            return;
        }

        $others = User::where('role', User::ROLE_SUPER_ADMIN)
            ->where('id', '!=', $user->id)
            ->whereNotNull('two_factor_confirmed_at')
            ->count();

        if ($others === 0) {
            throw ValidationException::withMessages([
                'two_factor' => 'This is the last super administrator with two-factor authentication. Set it up on another account first.',
            ]);
        }
    }

    public function trustDevice(User $user, string $label, ?string $ip): string
    {
        $token = Str::random(64);

        DB::table('trusted_devices')->insert([
            'user_id' => $user->id,
            'token_hash' => hash('sha256', $token),
            'label' => $label,
            'ip' => $ip,
            'expires_at' => now()->addDays($this->settings->trustedDeviceDays()),
            'last_used_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $token;
    }

    public function isTrustedDevice(User $user, ?string $token): bool
    {
        if (! $token) {
            return false;
        }

        $device = DB::table('trusted_devices')
            ->where('user_id', $user->id)
            ->where('token_hash', hash('sha256', $token))
            ->where('expires_at', '>', now())
            ->first();

        if (! $device) {
            return false;
        }

        DB::table('trusted_devices')->where('id', $device->id)->update(['last_used_at' => now()]);

        return true;
    }

    public function forgetDevices(User $user): void
    {
        DB::table('trusted_devices')->where('user_id', $user->id)->delete();

        audit('2fa.devices_forgotten', $user);
    }

    /** @return array<int, string> */
    private function freshRecoveryCodes(): array
    {
        return collect(range(1, self::RECOVERY_CODE_COUNT))
            ->map(fn () => strtoupper(Str::random(5).'-'.Str::random(5)))
            ->all();
    }
}
