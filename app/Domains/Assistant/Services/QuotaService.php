<?php

namespace App\Domains\Assistant\Services;

use App\Domains\Assistant\Models\AiAnswer;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Three limits, because one is not enough:
 *  - per student per month, so nobody monopolises the budget
 *  - per student per day, so an exam-eve binge does not wipe out the month
 *  - platform spend per month, which is what actually protects DX's bill
 */
class QuotaService
{
    public function __construct(private readonly AssistantSettings $settings)
    {
    }

    public function monthlyLimit(User $user): int
    {
        return $user->monthly_ai_quota ?? $this->settings->monthlyQuota();
    }

    public function usedThisMonth(User $user): int
    {
        return AiAnswer::where('user_id', $user->id)
            ->where('created_at', '>=', now()->startOfMonth())
            ->where('refused', false)
            ->count();
    }

    public function usedToday(User $user): int
    {
        return AiAnswer::where('user_id', $user->id)
            ->where('created_at', '>=', now()->startOfDay())
            ->where('refused', false)
            ->count();
    }

    public function remaining(User $user): int
    {
        return max(0, $this->monthlyLimit($user) - $this->usedThisMonth($user));
    }

    public function platformSpendThisMonth(): float
    {
        return (float) AiAnswer::where('created_at', '>=', now()->startOfMonth())->sum('cost_usd');
    }

    public function resetsOn(): Carbon
    {
        return now()->startOfMonth()->addMonth();
    }

    /**
     * @return array{allowed: bool, reason: ?string, message: ?string}
     */
    public function check(User $user): array
    {
        if (! $this->settings->isEnabled()) {
            return $this->deny('disabled', 'The study assistant is not available right now.');
        }

        if ($this->platformSpendThisMonth() >= $this->settings->monthlyBudgetUsd()) {
            return $this->deny(
                'platform_budget',
                'The study assistant has reached its limit for this month. Ask a tutor instead — they are free and unlimited.',
            );
        }

        if ($user->free_for_life) {
            return ['allowed' => true, 'reason' => null, 'message' => null];
        }

        if ($this->usedToday($user) >= $this->settings->dailyQuota()) {
            return $this->deny(
                'daily',
                "You have used all {$this->settings->dailyQuota()} instant answers for today. More tomorrow, or ask a tutor now.",
            );
        }

        if ($this->usedThisMonth($user) >= $this->monthlyLimit($user)) {
            $resets = $this->resetsOn()->format('j F');

            return $this->deny(
                'monthly',
                "You have used all {$this->monthlyLimit($user)} instant answers for this month. Your quota resets on {$resets}. You can still ask a tutor — that is free and unlimited.",
            );
        }

        return ['allowed' => true, 'reason' => null, 'message' => null];
    }

    public function summary(User $user): array
    {
        $check = $this->check($user);

        return [
            'enabled' => $this->settings->isEnabled(),
            'mode' => $this->settings->mode(),
            'canAskDirectly' => $this->settings->isDirectAskAllowed(),
            'limit' => $this->monthlyLimit($user),
            'used' => $this->usedThisMonth($user),
            'remaining' => $this->remaining($user),
            'usedToday' => $this->usedToday($user),
            'dailyLimit' => $this->settings->dailyQuota(),
            'unlimited' => (bool) $user->free_for_life,
            'resetsOn' => $this->resetsOn()->format('j F'),
            'allowed' => $check['allowed'],
            'message' => $check['message'],
        ];
    }

    private function deny(string $reason, string $message): array
    {
        return ['allowed' => false, 'reason' => $reason, 'message' => $message];
    }
}
