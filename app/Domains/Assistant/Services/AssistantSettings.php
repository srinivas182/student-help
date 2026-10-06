<?php

namespace App\Domains\Assistant\Services;

/**
 * Every AI behaviour DX can change without a release (SRS: ADM-05).
 */
class AssistantSettings
{
    public const MODE_OFF = 'off';
    public const MODE_FALLBACK = 'fallback';
    public const MODE_ALWAYS = 'always';

    public function mode(): string
    {
        return (string) setting('ai_mode', self::MODE_OFF);
    }

    public function isEnabled(): bool
    {
        return $this->mode() !== self::MODE_OFF && filled($this->apiKey());
    }

    /** Students can ask directly, rather than only after a tutor fails to answer. */
    public function isDirectAskAllowed(): bool
    {
        return $this->mode() === self::MODE_ALWAYS;
    }

    public function provider(): string
    {
        return (string) setting('ai_provider', 'anthropic');
    }

    public function model(): string
    {
        return (string) setting('ai_model', '');
    }

    public function apiKey(): ?string
    {
        $key = setting('ai_api_key');

        return $key ? (string) $key : null;
    }

    /** Hours a request waits for a human before the AI is offered (fallback mode). */
    public function fallbackAfterHours(): int
    {
        return (int) setting('ai_fallback_after_hours', 2);
    }

    public function monthlyQuota(): int
    {
        return (int) setting('ai_monthly_quota_per_student', 10);
    }

    public function dailyQuota(): int
    {
        return (int) setting('ai_daily_quota_per_student', 5);
    }

    /** The cap that protects DX from a runaway bill. */
    public function monthlyBudgetUsd(): float
    {
        return (float) setting('ai_monthly_budget_usd', 50);
    }
}
