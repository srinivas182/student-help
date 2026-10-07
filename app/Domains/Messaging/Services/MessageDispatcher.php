<?php

namespace App\Domains\Messaging\Services;

use App\Domains\Messaging\Drivers\HttpSmsDriver;
use App\Domains\Messaging\Drivers\WhatsAppDriver;
use App\Domains\Messaging\Models\GatewayCredential;
use App\Domains\Messaging\Providers;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

/**
 * Sends on whichever gateway DX has configured, and records every attempt.
 *
 * The delivery log exists because of one question that always comes up with a
 * consent flow: "did the guardian actually get the email?"
 */
class MessageDispatcher
{
    public function activeCredential(string $channel): ?GatewayCredential
    {
        return GatewayCredential::where('channel', $channel)->where('is_active', true)->first();
    }

    public function isChannelReady(string $channel): bool
    {
        $credential = $this->activeCredential($channel);

        if (! $credential) {
            return false;
        }

        $required = array_keys(Providers::fields($channel, $credential->provider));
        $secrets = $credential->secrets();

        foreach ($required as $field) {
            if (blank($secrets[$field] ?? null)) {
                return false;
            }
        }

        return true;
    }

    /** Applies the configured email provider to Laravel's mailer at runtime. */
    public function applyMailConfig(): void
    {
        $credential = $this->activeCredential('email');

        if (! $credential) {
            return;
        }

        $secrets = $credential->secrets();

        match ($credential->provider) {
            'smtp' => Config::set([
                'mail.default' => 'smtp',
                'mail.mailers.smtp.host' => $secrets['host'] ?? '',
                'mail.mailers.smtp.port' => (int) ($secrets['port'] ?? 587),
                'mail.mailers.smtp.username' => $secrets['username'] ?? '',
                'mail.mailers.smtp.password' => $secrets['password'] ?? '',
                'mail.mailers.smtp.encryption' => $secrets['encryption'] ?? 'tls',
            ]),
            'postmark' => Config::set([
                'mail.default' => 'postmark',
                'services.postmark.token' => $secrets['token'] ?? '',
            ]),
            'ses' => Config::set([
                'mail.default' => 'ses',
                'services.ses.key' => $secrets['key'] ?? '',
                'services.ses.secret' => $secrets['secret'] ?? '',
                'services.ses.region' => $secrets['region'] ?? 'eu-west-1',
            ]),
            // Resend and Brevo both speak SMTP, which avoids a package per provider
            'resend' => Config::set([
                'mail.default' => 'smtp',
                'mail.mailers.smtp.host' => 'smtp.resend.com',
                'mail.mailers.smtp.port' => 587,
                'mail.mailers.smtp.username' => 'resend',
                'mail.mailers.smtp.password' => $secrets['token'] ?? '',
                'mail.mailers.smtp.encryption' => 'tls',
            ]),
            'brevo' => Config::set([
                'mail.default' => 'smtp',
                'mail.mailers.smtp.host' => 'smtp-relay.brevo.com',
                'mail.mailers.smtp.port' => 587,
                'mail.mailers.smtp.username' => $secrets['username'] ?? ($secrets['token'] ?? ''),
                'mail.mailers.smtp.password' => $secrets['token'] ?? '',
                'mail.mailers.smtp.encryption' => 'tls',
            ]),
            default => null,
        };

        Config::set([
            'mail.from.address' => (string) setting('email_from_address', 'noreply@dxstudenthelp.co.za'),
            'mail.from.name' => (string) setting('email_from_name', 'DX Student Help'),
        ]);
    }

    /**
     * Sends a short message, preferring WhatsApp when DX has switched it on,
     * since South African families read WhatsApp and ignore SMS.
     */
    public function sendShortMessage(string $to, string $message, ?string $purpose = null): array
    {
        $channel = $this->isChannelReady('whatsapp') && (bool) setting('whatsapp_preferred', false)
            ? 'whatsapp'
            : 'sms';

        return $this->sendOn($channel, $to, $message, $purpose);
    }

    public function sendOn(string $channel, string $to, string $message, ?string $purpose = null): array
    {
        $credential = $this->activeCredential($channel);

        if (! $credential || ! $this->isChannelReady($channel)) {
            $result = ['sent' => false, 'reference' => null, 'error' => "No {$channel} gateway is configured."];
            $this->log($channel, 'none', $to, $purpose, $result);

            return $result;
        }

        $driver = $channel === 'whatsapp'
            ? new WhatsAppDriver($credential->provider, $credential->secrets())
            : new HttpSmsDriver($credential->provider, $credential->secrets());

        $result = $driver->send($to, $message, (string) setting('sms_sender_id', 'DXHelp'));

        $credential->update([
            'verified_at' => $result['sent'] ? now() : $credential->verified_at,
            'last_error' => $result['error'],
        ]);

        $this->log($channel, $credential->provider, $to, $purpose, $result);

        return $result;
    }

    private function log(string $channel, string $provider, string $to, ?string $purpose, array $result): void
    {
        DB::table('message_deliveries')->insert([
            'channel' => $channel,
            'provider' => $provider,
            // Partially masked: enough to trace, not a readable contact list
            'recipient' => $this->maskRecipient($to),
            'purpose' => $purpose,
            'status' => $result['sent'] ? 'sent' : 'failed',
            'reference' => $result['reference'],
            'error' => $result['error'],
            'created_at' => now(),
        ]);
    }

    private function maskRecipient(string $value): string
    {
        if (str_contains($value, '@')) {
            [$local, $domain] = explode('@', $value, 2);

            return mb_substr($local, 0, 2).str_repeat('*', max(mb_strlen($local) - 2, 1)).'@'.$domain;
        }

        return mb_substr($value, 0, 5).str_repeat('*', max(mb_strlen($value) - 7, 1)).mb_substr($value, -2);
    }
}
