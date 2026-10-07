<?php

namespace App\Domains\Billing\Services;

use App\Domains\Billing\Models\Payment;
use App\Domains\Billing\Models\Plan;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * PayFast, South Africa's most widely used gateway.
 *
 * Three things make a payment integration trustworthy, and all three are here:
 * the signature on the way out, and on the way back the signature again, the
 * source IP, and a confirmation call to PayFast itself. Anyone can POST to a
 * webhook URL; only PayFast can satisfy all three.
 */
class PayFastGateway
{
    private const LIVE_PROCESS = 'https://www.payfast.co.za/eng/process';
    private const SANDBOX_PROCESS = 'https://sandbox.payfast.co.za/eng/process';
    private const LIVE_VALIDATE = 'https://www.payfast.co.za/eng/query/validate';
    private const SANDBOX_VALIDATE = 'https://sandbox.payfast.co.za/eng/query/validate';

    /** PayFast only ever calls from these networks. */
    private const ALLOWED_HOSTS = [
        'www.payfast.co.za',
        'sandbox.payfast.co.za',
        'w1w.payfast.co.za',
        'w2w.payfast.co.za',
    ];

    public function isConfigured(): bool
    {
        return filled($this->merchantId()) && filled($this->merchantKey());
    }

    public function isSandbox(): bool
    {
        return (bool) setting('payfast_sandbox', true);
    }

    public function merchantId(): ?string
    {
        $value = setting('payfast_merchant_id');

        return $value ? (string) $value : null;
    }

    public function merchantKey(): ?string
    {
        $value = setting('payfast_merchant_key');

        return $value ? (string) $value : null;
    }

    private function passphrase(): ?string
    {
        $value = setting('payfast_passphrase');

        return $value ? (string) $value : null;
    }

    public function processUrl(): string
    {
        return $this->isSandbox() ? self::SANDBOX_PROCESS : self::LIVE_PROCESS;
    }

    /** @return array{url: string, fields: array<string, string>, payment: Payment} */
    public function prepare(User $user, Plan $plan): array
    {
        $reference = 'DX-'.strtoupper(Str::random(10));

        $payment = Payment::create([
            'user_id' => $user->id,
            'provider' => 'payfast',
            'merchant_reference' => $reference,
            'amount_cents' => $plan->price_cents,
            'currency' => 'ZAR',
            'status' => Payment::STATUS_PENDING,
        ]);

        $fields = [
            'merchant_id' => $this->merchantId(),
            'merchant_key' => $this->merchantKey(),
            'return_url' => route('billing.return'),
            'cancel_url' => route('billing.cancel'),
            'notify_url' => route('billing.notify'),
            'name_first' => $user->first_name ?? 'Student',
            'email_address' => $user->email,
            'm_payment_id' => $reference,
            'amount' => number_format($plan->price_cents / 100, 2, '.', ''),
            'item_name' => Str::limit($plan->name, 100, ''),
            'item_description' => Str::limit($plan->description ?? $plan->name, 200, ''),
            'custom_str1' => (string) $plan->id,
            'custom_str2' => (string) $user->id,
        ];

        // Monthly plans use PayFast's own recurring billing.
        if ($plan->interval === 'month' && ! $plan->isFree()) {
            $fields += [
                'subscription_type' => '1',
                'billing_date' => now()->format('Y-m-d'),
                'recurring_amount' => $fields['amount'],
                'frequency' => '3',  // monthly
                'cycles' => '0',     // until cancelled
            ];
        }

        $fields = array_filter($fields, fn ($value) => $value !== null && $value !== '');
        $fields['signature'] = $this->signature($fields);

        return ['url' => $this->processUrl(), 'fields' => $fields, 'payment' => $payment];
    }

    /** PayFast's signature: urlencoded in field order, uppercase hex, with the passphrase appended. */
    public function signature(array $fields): string
    {
        $pairs = [];

        foreach ($fields as $key => $value) {
            if ($key === 'signature' || $value === null || $value === '') {
                continue;
            }

            $pairs[] = $key.'='.urlencode(trim((string) $value));
        }

        $payload = implode('&', $pairs);

        if (filled($this->passphrase())) {
            $payload .= '&passphrase='.urlencode(trim($this->passphrase()));
        }

        return md5($payload);
    }

    public function signatureMatches(array $payload): bool
    {
        $given = $payload['signature'] ?? '';

        return filled($given) && hash_equals($this->signature($payload), $given);
    }

    /**
     * The notification must actually come from PayFast's network.
     * Resolution is cached for an hour: PayFast retries, and a DNS lookup per
     * retry is both slow and a dependency we do not want in the hot path.
     */
    public function ipIsValid(?string $ip): bool
    {
        if (! $ip) {
            return false;
        }

        return in_array($ip, $this->allowedIps(), true);
    }

    /** @return array<int, string> */
    public function allowedIps(): array
    {
        return Cache::remember('payfast.ips', now()->addHour(), function () {
            $ips = [];

            foreach (self::ALLOWED_HOSTS as $host) {
                foreach (gethostbynamel($host) ?: [] as $resolved) {
                    $ips[] = $resolved;
                }
            }

            return array_values(array_unique($ips));
        });
    }

    /** Final check: ask PayFast whether it really sent this. */
    public function confirmWithPayFast(array $payload): bool
    {
        unset($payload['signature']);

        $response = Http::asForm()->timeout(10)->post(
            $this->isSandbox() ? self::SANDBOX_VALIDATE : self::LIVE_VALIDATE,
            $payload,
        );

        return $response->successful() && str_contains(strtoupper($response->body()), 'VALID');
    }
}
