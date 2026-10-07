<?php

namespace App\Domains\Messaging;

/**
 * What DX can choose from, and what each one needs.
 *
 * The SMS list is deliberately South African first: a platform sending to SA
 * numbers should not be forced through a US provider at international rates.
 */
class Providers
{
    public const EMAIL = [
        'smtp' => [
            'name' => 'SMTP',
            'note' => 'Works with any mail server, including cPanel hosting.',
            'fields' => ['host' => 'Host', 'port' => 'Port', 'username' => 'Username', 'password' => 'Password', 'encryption' => 'Encryption (tls or ssl)'],
        ],
        'postmark' => [
            'name' => 'Postmark',
            'note' => 'Strong deliverability for transactional email.',
            'fields' => ['token' => 'Server API token'],
        ],
        'ses' => [
            'name' => 'Amazon SES',
            'note' => 'Cheapest at volume. Needs an AWS account out of sandbox.',
            'fields' => ['key' => 'Access key ID', 'secret' => 'Secret access key', 'region' => 'Region'],
        ],
        'resend' => [
            'name' => 'Resend',
            'note' => 'Simple setup, generous free tier.',
            'fields' => ['token' => 'API key'],
        ],
        'brevo' => [
            'name' => 'Brevo',
            'note' => '300 free emails a day — enough to run a pilot at no cost.',
            'fields' => ['token' => 'API key'],
        ],
    ];

    public const SMS = [
        'clickatell' => [
            'name' => 'Clickatell',
            'note' => 'South African, strongest local network relationships.',
            'fields' => ['api_key' => 'API key'],
        ],
        'smsportal' => [
            'name' => 'SMSPortal',
            'note' => 'South African, widely used by local businesses.',
            'fields' => ['client_id' => 'Client ID', 'api_secret' => 'API secret'],
        ],
        'bulksms' => [
            'name' => 'BulkSMS',
            'note' => 'South African, straightforward per-message pricing.',
            'fields' => ['token_id' => 'Token ID', 'token_secret' => 'Token secret'],
        ],
        'winsms' => [
            'name' => 'Winsms',
            'note' => 'South African, low cost for smaller volumes.',
            'fields' => ['api_key' => 'API key'],
        ],
        'twilio' => [
            'name' => 'Twilio',
            'note' => 'International reach. Costs more for South African numbers.',
            'fields' => ['sid' => 'Account SID', 'token' => 'Auth token', 'from' => 'From number'],
        ],
    ];

    public const WHATSAPP = [
        'meta_cloud' => [
            'name' => 'WhatsApp Business (Meta Cloud API)',
            'note' => 'Needs Meta Business verification and approved message templates. Off by default.',
            'fields' => ['phone_number_id' => 'Phone number ID', 'token' => 'Permanent access token', 'business_id' => 'Business account ID'],
        ],
        'twilio_whatsapp' => [
            'name' => 'WhatsApp via Twilio',
            'note' => 'Quicker to start than Meta direct, higher per-message cost.',
            'fields' => ['sid' => 'Account SID', 'token' => 'Auth token', 'from' => 'WhatsApp sender number'],
        ],
    ];

    public static function forChannel(string $channel): array
    {
        return match ($channel) {
            'email' => self::EMAIL,
            'sms' => self::SMS,
            'whatsapp' => self::WHATSAPP,
            default => [],
        };
    }

    public static function exists(string $channel, string $provider): bool
    {
        return array_key_exists($provider, self::forChannel($channel));
    }

    /** @return array<string, string> */
    public static function fields(string $channel, string $provider): array
    {
        return self::forChannel($channel)[$provider]['fields'] ?? [];
    }
}
