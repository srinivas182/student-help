<?php

namespace App\Console\Commands;

use App\Domains\Assessment\Models\TopicMastery;
use App\Notifications\ReviewDue;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Routine housekeeping. Everything here is cheap, idempotent and safe to run
 * repeatedly — the scheduler is not the place for clever work.
 */
class PlatformMaintenance extends Command
{
    protected $signature = 'platform:maintain {--dry-run}';

    protected $description = 'Expire one-time codes and trusted devices, prune old logs, nudge spaced reviews';

    public function handle(): int
    {
        $lapsed = app(\App\Domains\Billing\Services\SubscriptionService::class)->expireLapsed();

        if ($lapsed > 0) {
            $this->components->info("{$lapsed} subscription(s) expired back to the free plan.");
        }

        $dry = (bool) $this->option('dry-run');

        $expiredCodes = DB::table('one_time_codes')
            ->where('expires_at', '<', now()->subDay())
            ->when(! $dry, fn ($q) => $q->delete(), fn ($q) => $q->count());

        $expiredDevices = DB::table('trusted_devices')
            ->where('expires_at', '<', now())
            ->when(! $dry, fn ($q) => $q->delete(), fn ($q) => $q->count());

        // Delivery logs are useful for weeks, not forever
        $oldDeliveries = DB::table('message_deliveries')
            ->where('created_at', '<', now()->subDays(90))
            ->when(! $dry, fn ($q) => $q->delete(), fn ($q) => $q->count());

        // Bypasses that nobody used
        $staleBypasses = User::whereNotNull('two_factor_bypass_until')
            ->where('two_factor_bypass_until', '<', now())
            ->when(
                ! $dry,
                fn ($q) => $q->update(['two_factor_bypass_until' => null, 'two_factor_bypass_reason' => null]),
                fn ($q) => $q->count(),
            );

        $this->info("Expired codes: {$expiredCodes}");
        $this->info("Expired devices: {$expiredDevices}");
        $this->info("Pruned delivery logs: {$oldDeliveries}");
        $this->info("Cleared bypasses: {$staleBypasses}");

        return self::SUCCESS;
    }
}
