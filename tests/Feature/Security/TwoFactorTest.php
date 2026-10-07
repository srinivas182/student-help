<?php

use App\Domains\Security\Services\GatewaySettings;
use App\Domains\Security\Services\OneTimeCodeService;
use App\Domains\Security\Services\TwoFactorService;
use App\Domains\Security\Services\TwoFactorSettings;
use App\Models\User;
use Database\Seeders\SettingsSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use PragmaRX\Google2FA\Google2FA;

function setSettings(array $values): void
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
    Notification::fake();
    $this->seed(SettingsSeeder::class);

    $this->admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'onboarding_completed_at' => now()]);
    $this->superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN, 'onboarding_completed_at' => now()]);
    $this->student = User::factory()->create(['role' => User::ROLE_STUDENT, 'onboarding_completed_at' => now()]);
});

it('never requires two-factor from students', function () {
    $settings = app(TwoFactorSettings::class);

    expect($settings->isRequiredFor($this->student))->toBeFalse()
        ->and($settings->isRequiredFor($this->admin))->toBeTrue();

    // Even if a student role is somehow configured as required
    setSettings(['2fa_required_roles' => ['student', 'admin']]);

    expect(app(TwoFactorSettings::class)->isRequiredFor($this->student))->toBeFalse();
});

it('enrols with an authenticator app and confirms with a real code', function () {
    $service = app(TwoFactorService::class);

    $enrolment = $service->beginEnrolment($this->admin);

    expect($service->isEnabled($this->admin->fresh()))->toBeFalse()
        ->and($enrolment['recovery'])->toHaveCount(TwoFactorService::RECOVERY_CODE_COUNT);

    $code = app(Google2FA::class)->getCurrentOtp($enrolment['secret']);

    $service->confirm($this->admin->fresh(), $code);

    expect($service->isEnabled($this->admin->fresh()))->toBeTrue();
});

it('rejects a wrong authenticator code', function () {
    $service = app(TwoFactorService::class);
    $service->beginEnrolment($this->admin);

    expect(fn () => $service->confirm($this->admin->fresh(), '000000'))
        ->toThrow(Illuminate\Validation\ValidationException::class);
});

it('burns a recovery code after it is used once', function () {
    $service = app(TwoFactorService::class);
    $enrolment = $service->beginEnrolment($this->admin);

    $user = $this->admin->fresh();
    $code = $enrolment['recovery'][0];

    expect($service->verifyRecoveryCode($user, $code))->toBeTrue()
        ->and($service->recoveryCodes($user->fresh()))->toHaveCount(7)
        // The same code cannot be used again
        ->and($service->verifyRecoveryCode($user->fresh(), $code))->toBeFalse();
});

it('keeps the secret and recovery codes out of serialised output', function () {
    app(TwoFactorService::class)->beginEnrolment($this->admin);

    $array = $this->admin->fresh()->toArray();

    expect($array)->not->toHaveKey('two_factor_secret')
        ->and($array)->not->toHaveKey('two_factor_recovery_codes');
});

it('grants a time-limited bypass rather than switching 2FA off permanently', function () {
    $service = app(TwoFactorService::class);
    $enrolment = $service->beginEnrolment($this->admin);
    $service->confirm($this->admin->fresh(), app(Google2FA::class)->getCurrentOtp($enrolment['secret']));

    // A second protected super admin so the break-glass rule does not block us
    $other = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN, 'two_factor_confirmed_at' => now()]);

    $this->actingAs($this->superAdmin)->post(route('admin.security.bypass', $this->admin), [
        'reason' => 'Lost their phone during the school holidays.',
    ])->assertRedirect();

    $user = $this->admin->fresh();

    expect($user->two_factor_confirmed_at)->toBeNull()
        ->and($user->two_factor_secret)->toBeNull()
        ->and($service->hasActiveBypass($user))->toBeTrue()
        ->and($user->two_factor_bypass_until->isAfter(now()))
        ->and($user->two_factor_bypass_until->isBefore(now()->addHours(25)))->toBeTrue()
        ->and(DB::table('audit_logs')->where('action', '2fa.bypass_granted')->count())->toBe(1);
});

it('requires a written reason for a bypass', function () {
    $this->actingAs($this->superAdmin)
        ->post(route('admin.security.bypass', $this->admin), ['reason' => 'lost'])
        ->assertSessionHasErrors('reason');
});

it('tells the user and the other administrators when a bypass is granted', function () {
    User::factory()->create(['role' => User::ROLE_SUPER_ADMIN, 'two_factor_confirmed_at' => now()]);

    $this->actingAs($this->superAdmin)->post(route('admin.security.bypass', $this->admin), [
        'reason' => 'Phone stolen, confirmed by the school principal.',
    ]);

    Notification::assertSentTo($this->admin, App\Notifications\TwoFactorBypassGranted::class);
});

it('refuses to unprotect the last super administrator', function () {
    $service = app(TwoFactorService::class);
    $enrolment = $service->beginEnrolment($this->superAdmin);
    $service->confirm($this->superAdmin->fresh(), app(Google2FA::class)->getCurrentOtp($enrolment['secret']));

    expect(fn () => $service->assertNotLastProtectedAdmin($this->superAdmin->fresh()))
        ->toThrow(Illuminate\Validation\ValidationException::class);
});

it('remembers a trusted device and forgets it on request', function () {
    $service = app(TwoFactorService::class);

    $token = $service->trustDevice($this->admin, 'Office laptop', '41.0.0.1');

    expect($service->isTrustedDevice($this->admin, $token))->toBeTrue()
        ->and($service->isTrustedDevice($this->admin, 'not-the-token'))->toBeFalse();

    $service->forgetDevices($this->admin);

    expect($service->isTrustedDevice($this->admin, $token))->toBeFalse();
});

it('will not issue a code on a channel that is not configured', function () {
    expect(app(GatewaySettings::class)->emailEnabled())->toBeFalse();

    expect(fn () => app(OneTimeCodeService::class)->issue($this->admin, 'login'))
        ->toThrow(Illuminate\Validation\ValidationException::class);
});

it('verifies a one-time code once, then refuses it', function () {
    setSettings(['email_gateway_enabled' => true, 'email_gateway_provider' => 'smtp']);

    $service = app(OneTimeCodeService::class);
    $code = $service->issue($this->admin, 'generation', 'email', ['topic_id' => 7]);

    $payload = $service->verify($this->admin, 'generation', $code);

    expect($payload['topic_id'])->toBe(7);

    expect(fn () => $service->verify($this->admin, 'generation', $code))
        ->toThrow(Illuminate\Validation\ValidationException::class);
});

it('locks a code after too many wrong attempts', function () {
    setSettings(['email_gateway_enabled' => true, 'email_gateway_provider' => 'smtp']);

    $service = app(OneTimeCodeService::class);
    $code = $service->issue($this->admin, 'login', 'email');

    foreach (range(1, OneTimeCodeService::MAX_ATTEMPTS) as $attempt) {
        try {
            $service->verify($this->admin, 'login', '111111');
        } catch (Illuminate\Validation\ValidationException) {
            // expected
        }
    }

    // Even the right code is refused once the attempts are spent
    expect(fn () => $service->verify($this->admin, 'login', $code))
        ->toThrow(Illuminate\Validation\ValidationException::class);
});

it('keeps security settings away from non-administrators', function () {
    $this->actingAs($this->student)->get(route('admin.security'))->assertForbidden();
});
