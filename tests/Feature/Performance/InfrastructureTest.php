<?php

use App\Models\User;
use App\Notifications\GuardianConsentRequest;
use App\Domains\Identity\Models\GuardianConsent;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

it('schedules every background task the platform depends on', function () {
    $schedule = app(Illuminate\Console\Scheduling\Schedule::class);

    $commands = collect($schedule->events())
        ->map(fn ($event) => $event->command)
        ->filter()
        ->implode(' ');

    expect($commands)->toContain('requests:maintain')      // escalation and auto-close
        ->and($commands)->toContain('platform:maintain')   // housekeeping
        ->and($commands)->toContain('reviews:remind')      // spaced review nudges
        ->and($commands)->toContain('horizon:snapshot');   // queue metrics
});

it('puts time-sensitive notifications on the urgent queue', function () {
    $consent = new GuardianConsent([
        'user_id' => 1,
        'guardian_name' => 'Parent',
        'guardian_email' => 'parent@example.co.za',
        'token' => Str::random(48),
    ]);

    expect((new GuardianConsentRequest($consent))->queue)->toBe('urgent')
        ->and((new App\Domains\Voice\Jobs\TranscribeVoiceNote(1))->queue)->toBe('transcription');
});

it('separates queue tiers so slow work cannot block urgent work', function () {
    $supervisors = config('horizon.defaults');

    expect($supervisors)->toHaveKeys(['supervisor-urgent', 'supervisor-default', 'supervisor-heavy'])
        ->and($supervisors['supervisor-urgent']['queue'])->toBe(['urgent'])
        ->and($supervisors['supervisor-heavy']['queue'])->toContain('generation')
        // Generation gets a long timeout; urgent work gets a short one
        ->and($supervisors['supervisor-heavy']['timeout'])->toBeGreaterThan(600)
        ->and($supervisors['supervisor-urgent']['timeout'])->toBeLessThan(60);
});

it('keeps the queue dashboard away from everyone except administrators', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $student = User::factory()->create(['role' => User::ROLE_STUDENT]);
    $moderator = User::factory()->create(['role' => User::ROLE_MODERATOR]);

    expect(Gate::forUser($admin)->allows('viewHorizon'))->toBeTrue()
        // Job payloads can contain personal data, so moderators are out too
        ->and(Gate::forUser($moderator)->allows('viewHorizon'))->toBeFalse()
        ->and(Gate::forUser($student)->allows('viewHorizon'))->toBeFalse()
        ->and(Gate::forUser(null)->allows('viewHorizon'))->toBeFalse();
});

it('cleans up expired codes, devices and stale bypasses', function () {
    $user = User::factory()->create([
        'role' => User::ROLE_ADMIN,
        'two_factor_bypass_until' => now()->subHour(),
        'two_factor_bypass_reason' => 'Lost phone',
    ]);

    DB::table('one_time_codes')->insert([
        'user_id' => $user->id,
        'purpose' => 'login',
        'channel' => 'email',
        'code_hash' => 'x',
        'expires_at' => now()->subDays(3),
        'created_at' => now()->subDays(3),
        'updated_at' => now()->subDays(3),
    ]);

    DB::table('trusted_devices')->insert([
        'user_id' => $user->id,
        'token_hash' => 'x',
        'expires_at' => now()->subDay(),
        'created_at' => now()->subMonth(),
        'updated_at' => now()->subMonth(),
    ]);

    Artisan::call('platform:maintain');

    expect(DB::table('one_time_codes')->count())->toBe(0)
        ->and(DB::table('trusted_devices')->count())->toBe(0)
        ->and($user->fresh()->two_factor_bypass_until)->toBeNull();
});

it('does not delete anything on a dry run', function () {
    $user = User::factory()->create(['role' => User::ROLE_ADMIN]);

    DB::table('trusted_devices')->insert([
        'user_id' => $user->id,
        'token_hash' => 'x',
        'expires_at' => now()->subDay(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    Artisan::call('platform:maintain', ['--dry-run' => true]);

    expect(DB::table('trusted_devices')->count())->toBe(1);
});
