<?php

use App\Domains\Billing\Models\Payment;
use App\Domains\Billing\Models\Plan;
use App\Domains\Billing\Models\Subscription;
use App\Domains\Billing\Services\PayFastGateway;
use App\Domains\Billing\Services\SubscriptionService;
use App\Models\User;
use Database\Seeders\PlanSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/** Pretend DNS resolved PayFast's network to this address. */
function allowPayFastIp(string $ip = '127.0.0.1'): void
{
    Cache::put('payfast.ips', [$ip], now()->addHour());
}

/** And that it resolved to something else entirely. */
function blockPayFastIp(): void
{
    Cache::put('payfast.ips', ['197.97.145.144'], now()->addHour());
}

function billingSettings(array $values): void
{
    foreach ($values as $key => $value) {
        DB::table('settings')->updateOrInsert(
            ['key' => $key],
            ['value' => json_encode($value), 'created_at' => now(), 'updated_at' => now()],
        );
    }

    Cache::forget('platform.settings');
}

beforeEach(function () {
    $this->seed(SettingsSeeder::class);
    $this->seed(PlanSeeder::class);

    billingSettings([
        'payfast_merchant_id' => '10000100',
        'payfast_merchant_key' => '46f0cd694581a',
        'payfast_passphrase' => 'test-passphrase',
        'payfast_sandbox' => true,
    ]);

    $this->student = User::factory()->create([
        'role' => User::ROLE_STUDENT,
        'date_of_birth' => now()->subYears(19),
        'onboarding_completed_at' => now(),
    ]);

    $this->plus = Plan::where('slug', 'plus')->firstOrFail();
});

/** A notification shaped the way PayFast sends them. */
function payfastPayload(Payment $payment, Plan $plan, array $overrides = []): array
{
    $payload = array_merge([
        'm_payment_id' => $payment->merchant_reference,
        'pf_payment_id' => '1089250',
        'payment_status' => 'COMPLETE',
        'item_name' => $plan->name,
        'amount_gross' => number_format($plan->price_cents / 100, 2, '.', ''),
        'amount_fee' => '-2.35',
        'amount_net' => '46.65',
        'custom_str1' => (string) $plan->id,
        'custom_str2' => (string) $payment->user_id,
        'merchant_id' => '10000100',
    ], $overrides);

    $payload['signature'] = app(PayFastGateway::class)->signature($payload);

    return $payload;
}

it('keeps everything free while monetisation is off', function () {
    $service = app(SubscriptionService::class);

    expect($service->isMonetisationOn())->toBeFalse()
        ->and($service->requestAllowance($this->student))->toBeNull();
});

it('applies the free allowance once monetisation is switched on', function () {
    billingSettings(['monetisation_mode' => 'freemium', 'free_monthly_request_allowance' => 5]);

    expect(app(SubscriptionService::class)->requestAllowance($this->student))->toBe(5);
});

it('grandfathers people who joined before monetisation started', function () {
    $early = User::factory()->create([
        'role' => User::ROLE_STUDENT,
        'created_at' => now()->subMonths(3),
        'onboarding_completed_at' => now(),
    ]);

    billingSettings([
        'monetisation_mode' => 'freemium',
        'grandfather_existing_users' => true,
        'monetisation_started_at' => now()->subMonth()->toDateTimeString(),
    ]);

    $service = app(SubscriptionService::class);

    expect($service->isGrandfathered($early))->toBeTrue()
        ->and($service->requestAllowance($early))->toBeNull()
        // Someone who joined after the cutoff is not grandfathered
        ->and($service->isGrandfathered($this->student))->toBeFalse();
});

it('builds a signed PayFast form without exposing the merchant key to the browser unsigned', function () {
    $prepared = app(PayFastGateway::class)->prepare($this->student, $this->plus);

    expect($prepared['url'])->toContain('sandbox.payfast.co.za')
        ->and($prepared['fields']['amount'])->toBe('49.00')
        ->and($prepared['fields']['signature'])->toHaveLength(32)
        // Monthly plans set up PayFast recurring billing
        ->and($prepared['fields']['subscription_type'])->toBe('1')
        ->and($prepared['fields']['frequency'])->toBe('3')
        ->and(Payment::count())->toBe(1);
});

it('verifies its own signature', function () {
    $gateway = app(PayFastGateway::class);

    $fields = ['merchant_id' => '10000100', 'amount' => '49.00', 'item_name' => 'Plus'];
    $fields['signature'] = $gateway->signature($fields);

    expect($gateway->signatureMatches($fields))->toBeTrue();

    $fields['amount'] = '1.00';

    expect($gateway->signatureMatches($fields))->toBeFalse();
});

it('activates a subscription only after a fully verified notification', function () {
    Http::fake(['*payfast.co.za/eng/query/validate' => Http::response('VALID', 200)]);

    $payment = app(PayFastGateway::class)->prepare($this->student, $this->plus)['payment'];
    $payload = payfastPayload($payment, $this->plus);

    allowPayFastIp();

    $this->post(route('billing.notify'), $payload)->assertOk();

    $payment->refresh();

    expect($payment->status)->toBe(Payment::STATUS_COMPLETE)
        ->and($payment->payment_id)->toBe('1089250')
        ->and(Subscription::where('user_id', $this->student->id)->where('status', 'active')->exists())->toBeTrue()
        ->and($this->student->fresh()->plan)->toBe('plus');
});

it('rejects a notification with a forged signature', function () {
    allowPayFastIp();

    $payment = app(PayFastGateway::class)->prepare($this->student, $this->plus)['payment'];

    $payload = payfastPayload($payment, $this->plus);
    $payload['amount_gross'] = '1.00';   // tampered after signing

    $this->post(route('billing.notify'), $payload)->assertOk();

    expect($payment->fresh()->status)->toBe(Payment::STATUS_FAILED)
        ->and(Subscription::count())->toBe(0);
});

it('rejects a notification that does not come from PayFast', function () {
    $payment = app(PayFastGateway::class)->prepare($this->student, $this->plus)['payment'];

    blockPayFastIp();

    $this->post(route('billing.notify'), payfastPayload($payment, $this->plus))->assertOk();

    expect($payment->fresh()->status)->toBe(Payment::STATUS_FAILED)
        ->and(Subscription::count())->toBe(0);
});

it('rejects a notification whose amount does not match what we charged', function () {
    Http::fake(['*' => Http::response('VALID', 200)]);

    allowPayFastIp();

    $gateway = app(PayFastGateway::class);
    $payment = $gateway->prepare($this->student, $this->plus)['payment'];
    $payload = payfastPayload($payment, $this->plus, ['amount_gross' => '1.00']);
    $payload['signature'] = $gateway->signature(collect($payload)->except('signature')->all());

    $this->post(route('billing.notify'), $payload)->assertOk();

    expect($payment->fresh()->status)->toBe(Payment::STATUS_FAILED)
        ->and(Subscription::count())->toBe(0);
});

it('does not activate twice when PayFast retries', function () {
    Http::fake(['*' => Http::response('VALID', 200)]);

    allowPayFastIp();

    $payment = app(PayFastGateway::class)->prepare($this->student, $this->plus)['payment'];
    $payload = payfastPayload($payment, $this->plus);

    $this->post(route('billing.notify'), $payload);
    $this->post(route('billing.notify'), $payload);

    expect(Subscription::count())->toBe(1);
});

it('lets a student cancel and keep access until the period ends', function () {
    $subscription = Subscription::create([
        'user_id' => $this->student->id,
        'plan_id' => $this->plus->id,
        'status' => Subscription::STATUS_ACTIVE,
        'amount_cents' => 4900,
        'starts_at' => now()->subDays(5),
        'ends_at' => now()->addDays(25),
    ]);

    $this->actingAs($this->student)->post(route('billing.cancelSubscription'))->assertRedirect();

    $subscription->refresh();

    expect($subscription->status)->toBe(Subscription::STATUS_CANCELLED)
        ->and($subscription->isUsable())->toBeTrue()
        ->and(app(SubscriptionService::class)->activeSubscription($this->student))->not->toBeNull();
});

it('drops a lapsed subscription back to free', function () {
    Subscription::create([
        'user_id' => $this->student->id,
        'plan_id' => $this->plus->id,
        'status' => Subscription::STATUS_ACTIVE,
        'amount_cents' => 4900,
        'starts_at' => now()->subMonths(2),
        'ends_at' => now()->subDay(),
    ]);
    $this->student->update(['plan' => 'plus']);

    expect(app(SubscriptionService::class)->expireLapsed())->toBe(1)
        ->and($this->student->fresh()->plan)->toBe('free');
});

it('will not charge for the free plan or when PayFast is not configured', function () {
    $free = Plan::where('slug', 'free')->firstOrFail();

    $this->actingAs($this->student)->get(route('billing.checkout', $free))->assertStatus(422);

    billingSettings(['payfast_merchant_id' => '', 'payfast_merchant_key' => '']);

    $this->actingAs($this->student)->get(route('billing.checkout', $this->plus))->assertStatus(422);
});

it('shows plans with the student\'s current one marked', function () {
    $this->actingAs($this->student)
        ->get(route('billing.plans'))
        ->assertInertia(fn ($page) => $page
            ->component('Billing/Plans')
            ->has('plans', 3)
            ->where('plans.0.isCurrent', true)
            ->where('monetisationOn', false));
});
