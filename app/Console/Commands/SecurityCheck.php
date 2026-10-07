<?php

namespace App\Console\Commands;

use App\Domains\Billing\Services\PayFastGateway;
use App\Domains\Security\Services\TwoFactorService;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * Run before every deployment. Catches the configuration mistakes that are
 * invisible until something has already gone wrong.
 */
class SecurityCheck extends Command
{
    protected $signature = 'platform:security-check';

    protected $description = 'Check the security configuration before a release';

    public function handle(): int
    {
        $checks = [];
        $twoFactor = app(TwoFactorService::class);

        $checks[] = $this->check(
            'Debug mode off',
            ! config('app.debug'),
            'APP_DEBUG=true leaks stack traces, environment values and database details to anyone who triggers an error.',
        );

        $checks[] = $this->check(
            'Environment is production',
            app()->environment('production'),
            'APP_ENV should be production on a live server.',
        );

        $checks[] = $this->check(
            'APP_KEY set',
            filled(config('app.key')),
            'Without it, every encrypted value and session is unreadable or forgeable.',
        );

        $checks[] = $this->check(
            'Secure session cookie',
            (bool) config('session.secure'),
            'SESSION_SECURE_COOKIE=true stops the session cookie being sent over plain http.',
        );

        $checks[] = $this->check(
            'Session cookie is http-only',
            (bool) config('session.http_only'),
            'Stops JavaScript reading the session cookie.',
        );

        $checks[] = $this->check(
            'HTTPS enforced',
            str_starts_with((string) config('app.url'), 'https://'),
            'APP_URL should be https:// so generated links and the consent emails are secure.',
        );

        $checks[] = $this->check(
            'Database is MySQL',
            config('database.default') === 'mysql',
            'SQLite is for development only.',
        );

        $checks[] = $this->check(
            'Redis in use for cache, session and queue',
            config('cache.default') === 'redis'
                && config('session.driver') === 'redis'
                && config('queue.default') === 'redis',
            'Falling back to file or sync drivers costs performance and loses queued jobs.',
        );

        $checks[] = $this->check(
            'No default passwords on staff accounts',
            ! $this->hasDefaultPasswords(),
            'A staff account still using the demo password is the easiest way into the platform.',
        );

        $checks[] = $this->check(
            'At least one super administrator has two-factor',
            User::where('role', User::ROLE_SUPER_ADMIN)->whereNotNull('two_factor_confirmed_at')->exists(),
            'Set up an authenticator app on a super admin account before going live.',
        );

        $checks[] = $this->check(
            'No active two-factor bypasses',
            ! User::where('two_factor_bypass_until', '>', now())->exists(),
            'A bypass left open is an account without a second factor.',
        );

        $checks[] = $this->check(
            'PayFast not left in sandbox',
            ! app(PayFastGateway::class)->isConfigured() || ! app(PayFastGateway::class)->isSandbox(),
            'Sandbox mode takes no real payments. Fine before launch, wrong after it.',
        );

        $checks[] = $this->check(
            'Storage not world-writable',
            $this->permissionsAreSane(),
            'storage/ and bootstrap/cache should be writable by the web user, not by everyone.',
        );

        $checks[] = $this->check(
            '.env is not inside the public directory',
            ! File::exists(public_path('.env')),
            'An .env file served over http gives away every secret the platform holds.',
        );

        $checks[] = $this->check(
            'No scale-test data present',
            ! DB::table('users')->where('email', 'like', 'scale-%@example.test')->exists(),
            'Generated load-test accounts must never reach production.',
        );

        $this->newLine();
        $this->table(['Check', 'Result'], array_map(
            fn ($check) => [$check['label'], $check['passed'] ? 'pass' : 'FAIL'],
            $checks,
        ));

        $failures = array_filter($checks, fn ($check) => ! $check['passed']);

        foreach ($failures as $failure) {
            $this->components->error($failure['label'].' — '.$failure['why']);
        }

        if ($failures === []) {
            $this->components->info('All '.count($checks).' checks passed.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->components->warn(count($failures).' of '.count($checks).' checks failed. Fix these before deploying.');

        return self::FAILURE;
    }

    private function check(string $label, bool $passed, string $why): array
    {
        return ['label' => $label, 'passed' => $passed, 'why' => $why];
    }

    private function hasDefaultPasswords(): bool
    {
        foreach (User::whereIn('role', [User::ROLE_ADMIN, User::ROLE_SUPER_ADMIN, User::ROLE_MODERATOR])->get() as $user) {
            if (\Illuminate\Support\Facades\Hash::check('password', $user->password)) {
                return true;
            }
        }

        return false;
    }

    private function permissionsAreSane(): bool
    {
        foreach ([storage_path(), base_path('bootstrap/cache')] as $path) {
            if (File::exists($path) && (fileperms($path) & 0o002)) {
                return false;
            }
        }

        return true;
    }
}
