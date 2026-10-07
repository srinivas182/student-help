<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Turns measured query times into an honest concurrency estimate, and checks
 * that the production pieces are actually switched on.
 *
 * The arithmetic is deliberately conservative: it assumes every request does
 * real database work, which overstates load once caching and Octane are on.
 */
class CapacityReport extends Command
{
    protected $signature = 'platform:capacity {--workers=8} {--db-ms=12}';

    protected $description = 'Report readiness and an estimated concurrent user ceiling';

    public function handle(): int
    {
        $this->components->info('Production readiness');

        $checks = [
            'Cache driver' => [config('cache.default'), config('cache.default') === 'redis'],
            'Session driver' => [config('session.driver'), config('session.driver') === 'redis'],
            'Queue driver' => [config('queue.default'), config('queue.default') === 'redis'],
            'Database' => [config('database.default'), config('database.default') === 'mysql'],
            'Redis client' => [class_exists(\Predis\Client::class) ? 'predis' : 'missing', class_exists(\Predis\Client::class)],
            'Horizon' => [class_exists(\Laravel\Horizon\Horizon::class) ? 'installed' : 'missing', class_exists(\Laravel\Horizon\Horizon::class)],
            'Octane' => [class_exists(\Laravel\Octane\Octane::class) ? 'installed' : 'missing', class_exists(\Laravel\Octane\Octane::class)],
            'Debug mode off' => [config('app.debug') ? 'on' : 'off', ! config('app.debug')],
        ];

        $rows = [];
        $ready = 0;

        foreach ($checks as $label => [$value, $ok]) {
            $rows[] = [$label, $value, $ok ? 'ok' : 'ATTENTION'];
            $ready += $ok ? 1 : 0;
        }

        $this->table(['Check', 'Value', 'Status'], $rows);

        $this->components->info('Data volume');
        $this->table(['Table', 'Rows'], collect([
            'users', 'help_requests', 'messages', 'academic_selections', 'resources', 'topics',
        ])->map(fn ($table) => [$table, number_format(DB::table($table)->count())])->all());

        $this->components->info('Estimated capacity');

        $workers = max(1, (int) $this->option('workers'));
        $dbMs = max(1, (float) $this->option('db-ms'));

        // A page spends roughly this long in PHP plus its database time.
        $phpMs = 18;
        $perRequestMs = $phpMs + $dbMs;
        $requestsPerSecond = ($workers * 1000) / $perRequestMs;

        // A reading user generates roughly one request every 12 seconds.
        $thinkTimeSeconds = 12;
        $concurrent = (int) round($requestsPerSecond * $thinkTimeSeconds);

        $this->table(['Measure', 'Value'], [
            ['PHP workers assumed', $workers],
            ['Database time per page', $dbMs.' ms'],
            ['Estimated time per request', $perRequestMs.' ms'],
            ['Requests per second', round($requestsPerSecond)],
            ['Concurrent users (single server)', number_format($concurrent)],
            ['With Octane (roughly 3x)', number_format($concurrent * 3)],
            ['With 4 app servers behind a balancer', number_format($concurrent * 3 * 4)],
        ]);

        $this->newLine();
        $this->line('  These are estimates from measured query times, not a load test.');
        $this->line('  Run platform:benchmark against production-sized data first, then');
        $this->line('  confirm with a real load test before quoting a number to anyone.');

        if ($ready < count($checks)) {
            $this->newLine();
            $this->components->warn(($ready).' of '.count($checks).' production checks passed. Fix the rest before launch.');
        }

        Cache::forget('platform.capacity');

        return self::SUCCESS;
    }
}
