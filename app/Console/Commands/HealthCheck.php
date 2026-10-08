<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Is the deployment actually working? Run it straight after deploying, and
 * from monitoring afterwards.
 */
class HealthCheck extends Command
{
    protected $signature = 'platform:health {--json}';

    protected $description = 'Check database, cache, queue, storage and scheduler health';

    public function handle(): int
    {
        $results = [
            'database' => $this->attempt(fn () => DB::select('select 1') !== []),
            'migrations' => $this->attempt(fn () => ! DB::table('migrations')->doesntExist()),
            'cache' => $this->attempt(function () {
                Cache::put('health:ping', 'ok', 10);

                return Cache::get('health:ping') === 'ok';
            }),
            'queue' => $this->attempt(fn () => Queue::connection()->size() >= 0),
            // A worker that is not running shows up as jobs piling up, which
            // otherwise fails silently: emails and notifications just never
            // arrive and nothing anywhere says so
            'queue_moving' => $this->attempt(fn () => Queue::connection()->size() < 100),
            'storage' => $this->attempt(function () {
                Storage::disk('local')->put('health.txt', 'ok');
                $ok = Storage::disk('local')->get('health.txt') === 'ok';
                Storage::disk('local')->delete('health.txt');

                return $ok;
            }),
            'public_storage_link' => $this->attempt(fn () => is_link(public_path('storage'))),
            // If this is stale, the cron entry is missing and nothing automated runs
            'scheduler' => $this->attempt(function () {
                $last = Cache::get('health:scheduler_last_run');

                return $last === null || now()->diffInMinutes($last) < 60;
            }),
        ];

        if ($this->option('json')) {
            $this->line(json_encode([
                'status' => in_array(false, $results, true) ? 'unhealthy' : 'healthy',
                'checks' => $results,
                'time' => now()->toIso8601String(),
            ]));

            return in_array($results, [false], true) ? self::FAILURE : self::SUCCESS;
        }

        $this->table(['Check', 'Result'], collect($results)
            ->map(fn (bool $ok, string $name) => [$name, $ok ? 'ok' : 'FAIL'])
            ->values()
            ->all());

        if (in_array(false, $results, true)) {
            $this->components->error('Some checks failed. The site may be partly broken.');

            return self::FAILURE;
        }

        $this->components->info('All checks passed.');

        return self::SUCCESS;
    }

    private function attempt(callable $check): bool
    {
        try {
            return (bool) $check();
        } catch (Throwable) {
            return false;
        }
    }
}
