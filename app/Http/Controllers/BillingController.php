<?php

namespace App\Http\Controllers;

use App\Domains\Billing\Models\Payment;
use App\Domains\Billing\Models\Plan;
use App\Domains\Billing\Services\PayFastGateway;
use App\Domains\Billing\Services\SubscriptionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class BillingController extends Controller
{
    public function __construct(
        private readonly PayFastGateway $payfast,
        private readonly SubscriptionService $subscriptions,
    ) {
    }

    public function plans(Request $request): Response
    {
        $user = $request->user();
        $subscription = $this->subscriptions->activeSubscription($user);

        return Inertia::render('Billing/Plans', [
            'plans' => Plan::public()->get()->map(fn (Plan $plan) => [
                'id' => $plan->id,
                'slug' => $plan->slug,
                'name' => $plan->name,
                'description' => $plan->description,
                'price' => $plan->priceRands(),
                'priceCents' => $plan->price_cents,
                'interval' => $plan->interval,
                'features' => $plan->features ?? [],
                'isFree' => $plan->isFree(),
                'isCurrent' => $subscription?->plan_id === $plan->id
                    || ($subscription === null && $plan->slug === 'free'),
            ]),
            'monetisationOn' => $this->subscriptions->isMonetisationOn(),
            'grandfathered' => $this->subscriptions->isGrandfathered($user),
            'freeForLife' => (bool) $user->free_for_life,
            'paymentsReady' => $this->payfast->isConfigured(),
            'subscription' => $subscription ? [
                'plan' => $subscription->plan?->name,
                'status' => $subscription->status,
                'endsAt' => $subscription->ends_at?->toFormattedDateString(),
                'cancelled' => $subscription->cancelled_at !== null,
            ] : null,
        ]);
    }

    /** Builds the signed PayFast form. The browser never sees our keys. */
    public function checkout(Request $request, Plan $plan): Response
    {
        abort_if($plan->isFree(), 422, 'The free plan needs no payment.');
        abort_unless($this->payfast->isConfigured(), 422, 'Payments are not set up yet.');

        $prepared = $this->payfast->prepare($request->user(), $plan);

        audit('payment.started', $prepared['payment'], ['plan' => $plan->slug]);

        return Inertia::render('Billing/Checkout', [
            'plan' => [
                'name' => $plan->name,
                'price' => $plan->priceRands(),
                'interval' => $plan->interval,
            ],
            'url' => $prepared['url'],
            'fields' => $prepared['fields'],
            'sandbox' => $this->payfast->isSandbox(),
        ]);
    }

    /**
     * PayFast's server-to-server notification. This is the only thing that
     * grants access — never the browser return, which anyone can forge.
     */
    public function notify(Request $request): HttpResponse
    {
        $payload = $request->all();
        $reference = $payload['m_payment_id'] ?? null;

        $payment = $reference ? Payment::where('merchant_reference', $reference)->first() : null;

        if (! $payment) {
            Log::warning('PayFast notification for an unknown reference', ['reference' => $reference]);

            return response('', 200);
        }

        // Three independent checks, all of which must pass.
        $checks = [
            'signature' => $this->payfast->signatureMatches($payload),
            'ip' => $this->payfast->ipIsValid($request->ip()),
            'amount' => isset($payload['amount_gross'])
                && (int) round(((float) $payload['amount_gross']) * 100) === $payment->amount_cents,
        ];

        if (in_array(false, $checks, true) || ! $this->payfast->confirmWithPayFast($payload)) {
            $payment->update(['status' => Payment::STATUS_FAILED, 'payload' => $payload]);

            audit('payment.rejected', $payment, ['checks' => $checks]);
            Log::warning('PayFast notification rejected', ['reference' => $reference, 'checks' => $checks]);

            return response('', 200);
        }

        if (($payload['payment_status'] ?? '') !== 'COMPLETE') {
            $payment->update([
                'status' => Payment::STATUS_FAILED,
                'payload' => $payload,
                'payment_id' => $payload['pf_payment_id'] ?? null,
            ]);

            return response('', 200);
        }

        // Already processed: PayFast retries, and we must not double-activate.
        if ($payment->status === Payment::STATUS_COMPLETE) {
            return response('', 200);
        }

        $plan = Plan::find($payload['custom_str1'] ?? null);

        if (! $plan) {
            return response('', 200);
        }

        $payment->update(['payment_id' => $payload['pf_payment_id'] ?? null, 'payload' => $payload]);

        $this->subscriptions->activate($payment, $plan, $payload['token'] ?? null);

        return response('', 200);
    }

    /** Where the student lands afterwards. Confirms nothing by itself. */
    public function return(Request $request): Response
    {
        return Inertia::render('Billing/Result', [
            'outcome' => 'returned',
            'subscription' => $this->subscriptions->activeSubscription($request->user()) !== null,
        ]);
    }

    public function cancel(Request $request): Response
    {
        return Inertia::render('Billing/Result', ['outcome' => 'cancelled', 'subscription' => false]);
    }

    public function cancelSubscription(Request $request): RedirectResponse
    {
        $subscription = $this->subscriptions->activeSubscription($request->user());

        abort_unless($subscription, 404);

        $this->subscriptions->cancel($subscription, $request->user());

        return back()->with('success', $subscription->ends_at
            ? 'Cancelled. You keep access until '.$subscription->ends_at->toFormattedDateString().'.'
            : 'Subscription cancelled.');
    }

    public function history(Request $request): Response
    {
        return Inertia::render('Billing/History', [
            'payments' => Payment::where('user_id', $request->user()->id)
                ->latest()
                ->limit(50)
                ->get()
                ->map(fn (Payment $payment) => [
                    'reference' => $payment->merchant_reference,
                    'amount' => $payment->amountRands(),
                    'status' => $payment->status,
                    'paidAt' => $payment->paid_at?->toFormattedDateString(),
                    'createdAt' => $payment->created_at?->toFormattedDateString(),
                ]),
        ]);
    }
}
