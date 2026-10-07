<?php

namespace App\Domains\Messaging\Drivers;

use App\Domains\Messaging\Contracts\SmsDriver;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * WhatsApp, which matters here because South African learners and parents use it
 * far more than SMS. Off by default: Meta requires business verification and
 * approved templates before a single message can be sent.
 */
class WhatsAppDriver implements SmsDriver
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
        $number = ltrim(preg_replace('/[^\d]/', '', $to) ?? $to, '+');

        try {
            $response = match ($this->provider) {
                'meta_cloud' => Http::withToken($this->secrets['token'] ?? '')
                    ->asJson()
                    ->post("https://graph.facebook.com/v21.0/{$this->secrets['phone_number_id']}/messages", [
                        'messaging_product' => 'whatsapp',
                        'to' => $number,
                        'type' => 'text',
                        'text' => ['body' => $message],
                    ]),

                'twilio_whatsapp' => Http::withBasicAuth(
                    $this->secrets['sid'] ?? '',
                    $this->secrets['token'] ?? '',
                )->asForm()->post(
                    "https://api.twilio.com/2010-04-01/Accounts/{$this->secrets['sid']}/Messages.json",
                    [
                        'To' => 'whatsapp:+'.$number,
                        'From' => 'whatsapp:'.($this->secrets['from'] ?? $from),
                        'Body' => $message,
                    ],
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
                    'error' => 'WhatsApp returned '.$response->status().': '.str($response->body())->limit(180),
                ];
            }

            $body = $response->json();

            return [
                'sent' => true,
                'reference' => $body['messages'][0]['id'] ?? $body['sid'] ?? null,
                'error' => null,
            ];
        } catch (Throwable $exception) {
            return ['sent' => false, 'reference' => null, 'error' => $exception->getMessage()];
        }
    }
}
