<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Security\Services\GatewaySettings;
use App\Domains\Security\Services\TwoFactorService;
use App\Domains\Security\Services\TwoFactorSettings;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SecurityController extends Controller
{
    public function __construct(
        private readonly TwoFactorService $twoFactor,
        private readonly TwoFactorSettings $settings,
        private readonly GatewaySettings $gateways,
    ) {
    }

    public function edit(): Response
    {
        return Inertia::render('Admin/Security', [
            'twoFactor' => [
                'primaryMethod' => $this->settings->primaryMethod(),
                'backupMethods' => $this->settings->backupMethods(),
                'requiredRoles' => $this->settings->requiredRoles(),
                'trustedDeviceDays' => $this->settings->trustedDeviceDays(),
                'bypassHours' => $this->settings->bypassHours(),
            ],
            'gateways' => [
                'emailEnabled' => $this->gateways->emailEnabled(),
                'emailProvider' => $this->gateways->emailProvider(),
                'emailFrom' => $this->gateways->emailFrom(),
                'smsEnabled' => $this->gateways->smsEnabled(),
                'smsProvider' => $this->gateways->smsProvider(),
                'smsSender' => $this->gateways->smsSender(),
            ],
            'generation' => [
                'otpEnabled' => (bool) setting('ai_tutor_otp_enabled', true),
                'costThreshold' => (float) setting('ai_tutor_otp_cost_threshold', 2.0),
                'languageThreshold' => (int) setting('ai_tutor_otp_language_threshold', 5),
                'inputCost' => (float) setting('ai_tutor_input_cost_per_million', 3.0),
                'outputCost' => (float) setting('ai_tutor_output_cost_per_million', 15.0),
                'usdToZar' => (float) setting('usd_to_zar', 18.0),
                'inheritsAssistant' => (bool) setting('ai_tutor_inherit_assistant', true),
                'provider' => (string) setting('ai_tutor_provider', ''),
                'model' => (string) setting('ai_tutor_model', ''),
            ],
            'emailProviders' => GatewaySettings::EMAIL_PROVIDERS,
            'smsProviders' => GatewaySettings::SMS_PROVIDERS,
            'roles' => [User::ROLE_TUTOR, User::ROLE_MODERATOR, User::ROLE_ADMIN, User::ROLE_SUPER_ADMIN],
            'staff' => User::whereIn('role', [User::ROLE_MODERATOR, User::ROLE_ADMIN, User::ROLE_SUPER_ADMIN])
                ->get(['id', 'first_name', 'last_name', 'email', 'role', 'two_factor_confirmed_at', 'two_factor_bypass_until'])
                ->map(fn (User $user) => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'enabled' => $user->two_factor_confirmed_at !== null,
                    'bypassUntil' => $user->two_factor_bypass_until?->toDayDateTimeString(),
                ]),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'primary_method' => ['required', Rule::in(['app', 'email', 'sms'])],
            'backup_methods' => ['array'],
            'backup_methods.*' => [Rule::in(['email', 'sms'])],
            'required_roles' => ['array'],
            'required_roles.*' => [Rule::in([
                User::ROLE_TUTOR, User::ROLE_MODERATOR, User::ROLE_ADMIN, User::ROLE_SUPER_ADMIN,
            ])],
            'trusted_device_days' => ['required', 'integer', 'between:0,90'],
            'bypass_hours' => ['required', 'integer', 'between:1,168'],

            'email_gateway_enabled' => ['boolean'],
            'email_gateway_provider' => ['nullable', Rule::in(array_keys(GatewaySettings::EMAIL_PROVIDERS))],
            'email_from_address' => ['nullable', 'email'],
            'email_from_name' => ['nullable', 'string', 'max:80'],
            'sms_gateway_enabled' => ['boolean'],
            'sms_gateway_provider' => ['nullable', Rule::in(array_keys(GatewaySettings::SMS_PROVIDERS))],
            'sms_sender_id' => ['nullable', 'string', 'max:11'],

            'ai_tutor_otp_enabled' => ['boolean'],
            'ai_tutor_otp_cost_threshold' => ['required', 'numeric', 'between:0,1000'],
            'ai_tutor_otp_language_threshold' => ['required', 'integer', 'between:1,20'],
            'ai_tutor_input_cost_per_million' => ['required', 'numeric', 'between:0,1000'],
            'ai_tutor_output_cost_per_million' => ['required', 'numeric', 'between:0,1000'],
            'usd_to_zar' => ['required', 'numeric', 'between:1,100'],
            'ai_tutor_inherit_assistant' => ['boolean'],
            'ai_tutor_provider' => ['nullable', 'string', 'max:32'],
            'ai_tutor_model' => ['nullable', 'string', 'max:64'],
        ]);

        // Students are never required, whatever is submitted.
        $map = [
            '2fa_primary_method' => $validated['primary_method'],
            '2fa_backup_methods' => $validated['backup_methods'] ?? [],
            '2fa_required_roles' => array_values(array_diff(
                $validated['required_roles'] ?? [],
                [User::ROLE_STUDENT],
            )),
            '2fa_trusted_device_days' => $validated['trusted_device_days'],
            '2fa_bypass_hours' => $validated['bypass_hours'],
        ];

        foreach ($validated as $key => $value) {
            if (str_starts_with($key, 'email_') || str_starts_with($key, 'sms_')
                || str_starts_with($key, 'ai_tutor_') || $key === 'usd_to_zar') {
                $map[$key] = $value;
            }
        }

        foreach ($map as $key => $value) {
            DB::table('settings')->updateOrInsert(
                ['key' => $key],
                ['value' => json_encode($value), 'updated_at' => now(), 'created_at' => now()],
            );
        }

        Cache::forget('platform.settings');

        audit('security.settings_updated', null, ['keys' => array_keys($map)]);

        return back()->with('success', 'Security and gateway settings saved.');
    }

    /** A temporary bypass, never a permanent switch. */
    public function grantBypass(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:300'],
        ], [
            'reason.required' => 'Record why this bypass is needed — it goes in the audit log.',
        ]);

        $this->twoFactor->assertNotLastProtectedAdmin($user);

        $until = $this->twoFactor->grantBypass($user, $request->user(), $validated['reason']);

        // Everyone who could be affected is told, including the user themselves.
        $recipients = User::whereIn('role', [User::ROLE_ADMIN, User::ROLE_SUPER_ADMIN])
            ->where('id', '!=', $request->user()->id)
            ->get()
            ->push($user);

        Notification::send($recipients, new \App\Notifications\TwoFactorBypassGranted(
            $user->name,
            $request->user()->name,
            $validated['reason'],
            $until->toDayDateTimeString(),
        ));

        return back()->with('success',
            "{$user->first_name} can sign in with their password until {$until->format('j M, H:i')} and must set up two-factor again.");
    }

    public function testEmail(Request $request): RedirectResponse
    {
        abort_unless($this->gateways->emailEnabled(), 422, 'Enable and configure email first.');

        Notification::route('mail', $request->user()->email)->notify(
            new \App\Notifications\OneTimeCodeNotification('000000', 'This is a test message from DX Student Help. If you received it, your email gateway is working.'),
        );

        return back()->with('success', "Test email sent to {$request->user()->email}.");
    }
}
