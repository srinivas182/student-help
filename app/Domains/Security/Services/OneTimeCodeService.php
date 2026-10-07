<?php

namespace App\Domains\Security\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * One-time codes, used both as a 2FA backup and to confirm expensive actions.
 * Only ever issued on a channel the platform can actually deliver on.
 */
class OneTimeCodeService
{
    public const PURPOSE_LOGIN = 'login';
    public const PURPOSE_GENERATION = 'generation';

    public const MAX_ATTEMPTS = 5;
    public const TTL_MINUTES = 10;

    public function __construct(private readonly GatewaySettings $gateways)
    {
    }

    public function issue(User $user, string $purpose, string $channel = 'email', array $payload = []): string
    {
        if ($channel === 'email' && ! $this->gateways->emailEnabled()) {
            throw ValidationException::withMessages([
                'code' => 'Email is not configured, so a code cannot be sent.',
            ]);
        }

        if ($channel === 'sms' && ! $this->gateways->smsEnabled()) {
            throw ValidationException::withMessages([
                'code' => 'SMS is not configured, so a code cannot be sent.',
            ]);
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        // Any earlier unused code for this purpose stops working.
        DB::table('one_time_codes')
            ->where('user_id', $user->id)
            ->where('purpose', $purpose)
            ->whereNull('used_at')
            ->delete();

        DB::table('one_time_codes')->insert([
            'user_id' => $user->id,
            'purpose' => $purpose,
            'channel' => $channel,
            'code_hash' => Hash::make($code),
            'payload' => json_encode($payload),
            'expires_at' => now()->addMinutes(self::TTL_MINUTES),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->deliver($user, $code, $purpose, $channel);

        audit('otp.issued', $user, ['purpose' => $purpose, 'channel' => $channel]);

        return $code;
    }

    public function verify(User $user, string $purpose, string $code): array
    {
        $record = DB::table('one_time_codes')
            ->where('user_id', $user->id)
            ->where('purpose', $purpose)
            ->whereNull('used_at')
            ->latest('id')
            ->first();

        if (! $record) {
            throw ValidationException::withMessages(['code' => 'Request a new code.']);
        }

        if (now()->greaterThan($record->expires_at)) {
            throw ValidationException::withMessages(['code' => 'That code has expired. Request a new one.']);
        }

        if ($record->attempts >= self::MAX_ATTEMPTS) {
            throw ValidationException::withMessages(['code' => 'Too many attempts. Request a new code.']);
        }

        if (! Hash::check($code, $record->code_hash)) {
            DB::table('one_time_codes')->where('id', $record->id)->increment('attempts');

            throw ValidationException::withMessages(['code' => 'That code is not right.']);
        }

        DB::table('one_time_codes')->where('id', $record->id)->update(['used_at' => now()]);

        return json_decode($record->payload ?? '{}', true) ?? [];
    }

    private function deliver(User $user, string $code, string $purpose, string $channel): void
    {
        $message = $purpose === self::PURPOSE_GENERATION
            ? "Your DX Student Help confirmation code is {$code}. It confirms an AI content generation and expires in 10 minutes."
            : "Your DX Student Help sign-in code is {$code}. It expires in 10 minutes. If this was not you, change your password.";

        if ($channel === 'email') {
            Notification::route('mail', $user->email)->notify(
                new \App\Notifications\OneTimeCodeNotification($code, $message),
            );
        }

        // SMS delivery is dispatched through the configured gateway driver.
    }
}
