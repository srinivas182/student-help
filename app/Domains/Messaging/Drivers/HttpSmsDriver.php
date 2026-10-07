<?php

namespace App\Domains\Messaging\Drivers;

use App\Domains\Messaging\Contracts\SmsDriver;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * One driver covering every supported SMS provider, since they all come down to
 * an authenticated HTTP POST with a slightly different body.
 *
 * Numbers are normalised to international format first: South African users
 * type 082..., and every gateway wants +2782...
 */
class HttpSmsDriver implements SmsDriver
{
    public function __construct(
        private readonly string $provider,
        private readonly array $secrets,
    ) {
    }

    public function name(): string
    {
        return $this->provider;
    }

    public function send(string $to, string $message, string $from): array
    {
        $number = $this->normalise($to);

        try {
            $response = match ($this->provider) {
                'clickatell' => Http::withHeaders(['Authorization' => $this->secrets['api_key'] ?? ''])
                    ->asJson()
                    ->post('https://platform.clickatell.com/v1/message', [
                        'messages' => [['channel' => 'sms', 'to' => $number, 'content' => $message]],
                    ]),

                'smsportal' => Http::withBasicAuth(
                    $this->secrets['client_id'] ?? '',
                    $this->secrets['api_secret'] ?? '',
                )->asJson()->post('https://rest.smsportal.com/v1/bulkmessages', [
                    'messages' => [['content' => $message, 'destination' => $number]],
                ]),

                'bulksms' => Http::withBasicAuth(
                    $this->secrets['token_id'] ?? '',
                    $this->secrets['token_secret'] ?? '',
                )->asJson()->post('https://api.bulksms.com/v1/messages', [
                    'to' => $number,
                    'body' => $message,
                ]),

                'winsms' => Http::withHeaders(['AUTHORIZATION' => $this->secrets['api_key'] ?? ''])
                    ->asJson()
                    ->post('https://www.winsms.co.za/api/rest/v1/sms/outgoing/send/', [
                        'recipients' => [['mobileNumber' => $number]],
                        'message' => $message,
                    ]),

                'twilio' => Http::withBasicAuth(
                    $this->secrets['sid'] ?? '',
                    $this->secrets['token'] ?? '',
                )->asForm()->post(
                    "https://api.twilio.com/2010-04-01/Accounts/{$this->secrets['sid']}/Messages.json",
                    ['To' => $number, 'From' => $this->secrets['from'] ?? $from, 'Body' => $message],
                ),

                default => null,
            };

            if ($response === null) {
                return ['sent' => false, 'reference' => null, 'error' => "Unknown provider: {$this->provider}"];
            }

            if ($response->failed()) {
                return [
                    'sent' => false,
                    'reference' => null,
                    'error' => 'Gateway returned '.$response->status().': '.str($response->body())->limit(180),
                ];
            }

            return [
                'sent' => true,
                'reference' => $this->reference($response->json()),
                'error' => null,
            ];
        } catch (Throwable $exception) {
            return ['sent' => false, 'reference' => null, 'error' => $exception->getMessage()];
        }
    }

    /** 082 123 4567 and 27821234567 both become +27821234567. */
    private function normalise(string $number): string
    {
        $digits = preg_replace('/[^\d+]/', '', $number) ?? $number;

        if (str_starts_with($digits, '+')) {
            return $digits;
        }

        if (str_starts_with($digits, '0')) {
            return '+27'.substr($digits, 1);
        }

        if (str_starts_with($digits, '27')) {
            return '+'.$digits;
        }

        return '+'.$digits;
    }

    private function reference(?array $body): ?string
    {
        if (! $body) {
            return null;
        }

        return $body['messages'][0]['apiMessageId']
            ?? $body['messages'][0]['id']
            ?? $body['sid']
            ?? $body['eventId']
            ?? null;
    }
}
