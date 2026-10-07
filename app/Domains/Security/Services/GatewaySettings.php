<?php

namespace App\Domains\Security\Services;

/**
 * Email and SMS delivery, configured in admin rather than in .env, so DX can
 * change provider without a developer. A one-time code is only ever offered on
 * a channel that is actually configured — no half-working security theatre.
 */
class GatewaySettings
{
    public const EMAIL_PROVIDERS = [
        'smtp' => 'SMTP',
        'postmark' => 'Postmark',
        'ses' => 'Amazon SES',
        'resend' => 'Resend',
    ];

    public const SMS_PROVIDERS = [
        'clickatell' => 'Clickatell',
        'smsportal' => 'SMSPortal',
        'twilio' => 'Twilio',
    ];

    public function emailEnabled(): bool
    {
        return (bool) setting('email_gateway_enabled', false) && filled($this->emailProvider());
    }

    public function emailProvider(): ?string
    {
        $value = setting('email_gateway_provider');

        return $value ? (string) $value : null;
    }

    public function emailFrom(): array
    {
        return [
            'address' => (string) setting('email_from_address', 'noreply@dxstudenthelp.co.za'),
            'name' => (string) setting('email_from_name', 'DX Student Help'),
        ];
    }

    public function smsEnabled(): bool
    {
        return (bool) setting('sms_gateway_enabled', false) && filled($this->smsProvider());
    }

    public function smsProvider(): ?string
    {
        $value = setting('sms_gateway_provider');

        return $value ? (string) $value : null;
    }

    public function smsSender(): string
    {
        return (string) setting('sms_sender_id', 'DXHelp');
    }
}
