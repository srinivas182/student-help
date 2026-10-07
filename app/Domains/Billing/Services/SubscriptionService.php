<?php

namespace App\Domains\Billing\Services;

use App\Domains\Billing\Models\Payment;
use App\Domains\Billing\Models\Plan;
use App\Domains\Billing\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * What a student is entitled to, and how that changes when they pay.
 *
 * Monetisation is off by default. Existing users are grandfathered when it is
 * switched on, because changing the deal on people who joined for free is how
 * a platform loses the community it spent a year building.
 */
class SubscriptionService
{
    public function isMonetisationOn(): bool
    {
        return (string) setting('monetisation_mode', 'free') !== 'free';
    }

    public function activeSubscription(User $user): ?Subscription
    {
        return Subscription::where('user_id', $user->id)
            ->whereIn('status', [Subscription::STATUS_ACTIVE, Subscription::STATUS_CANCELLED])
            ->with('plan')
            ->latest('id')
            ->get()
            ->first(fn (Subscription $subscription) => $subscription->isUsable());
    }

    public function planFor(User $user): ?Plan
    {
        return $this->activeSubscription($user)?->plan
            ?? Plan::where('slug', 'free')->first();
    }

    /**
     * Request allowance. Unlimited while monetisation is off, for anyone
     * grandfathered, and for anyone on a paid plan with no cap.
     */
    public function requestAllowance(User $user): ?int
    {
        if (! $this->isMonetisationOn() || $user->free_for_life || $this->isGrandfathered($user)) {
            return null;
        }

        if ($subscription = $this->activeSubscription($user)) {
            return $subscription->plan?->monthly_request_quota;
        }

        return (int) setting('free_monthly_request_allowance', 5);
    }

    public function isGrandfathered(User $user): bool
    {
        if (! (bool) setting('grandfather_existing_users', true)) {
            return false;
        }

        $cutoff = setting('monetisation_started_at');

        return $cutoff !== null && $user->created_at?->lt($cutoff);
    }

    /** Called once a payment is confirmed, never from the browser. */
    public function activate(Payment $payment, Plan $plan, ?string $token = null): Subscription
    {
        return DB::transaction(function () use ($payment, $plan, $token) {
            $subscription = Subscription::updateOrCreate(
                ['user_id' => $payment->user_id, 'plan_id' => $plan->id],
                [
                    'status' => Subscription::STATUS_ACTIVE,
                    'provider' => 'payfast',
                    'provider_reference' => $payment->payment_id,
                    'provider_token' => $token,
                    'amount_cents' => $payment->amount_cents,
                    'starts_at' => now(),
                    'ends_at' => $plan->interval === 'month'
                        ? now()->addMonthNoOverflow()
                        : ($plan->interval === 'year' ? now()->addYear() : null),
                    'cancelled_at' => null,
                ],
            );

            $payment->update([
                'subscription_id' => $subscription->id,
                'status' => Payment::STATUS_COMPLETE,
                'paid_at' => now(),
            ]);

            $payment->user?->update([
                'plan' => $plan->slug,
                'plan_expires_at' => $subscription->ends_at,
                'monthly_request_quota' => $plan->monthly_request_quota,
                'monthly_ai_quota' => $plan->monthly_ai_quota,
            ]);

            audit('subscription.activated', $subscription, [
                'plan' => $plan->slug,
                'amount_cents' => $payment->amount_cents,
            ]);

            return $subscription;
        });
    }

    /** Access continues to the end of the period already paid for. */
    public function cancel(Subscription $subscription, User $actor): void
    {
        $subscription->update([
            'status' => Subscription::STATUS_CANCELLED,
            'cancelled_at' => now(),
        ]);

        audit('subscription.cancelled', $subscription, [
            'actor_id' => $actor->id,
            'access_until' => $subscription->ends_at?->toDateString(),
        ]);
    }

    public function expireLapsed(): int
    {
        $expired = Subscription::whereIn('status', [Subscription::STATUS_ACTIVE, Subscription::STATUS_CANCELLED])
            ->whereNotNull('ends_at')
            ->where('ends_at', '<', now())
            ->get();

        foreach ($expired as $subscription) {
            $subscription->update(['status' => Subscription::STATUS_EXPIRED]);

            $subscription->user?->update([
                'plan' => 'free',
                'plan_expires_at' => null,
                'monthly_request_quota' => null,
                'monthly_ai_quota' => null,
            ]);
        }

        return $expired->count();
    }
}
