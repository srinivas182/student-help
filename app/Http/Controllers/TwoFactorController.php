<?php

namespace App\Http\Controllers;

use App\Domains\Security\Services\GatewaySettings;
use App\Domains\Security\Services\TwoFactorService;
use App\Domains\Security\Services\TwoFactorSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TwoFactorController extends Controller
{
    public function __construct(
        private readonly TwoFactorService $twoFactor,
        private readonly TwoFactorSettings $settings,
        private readonly GatewaySettings $gateways,
    ) {
    }

    public function show(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Profile/TwoFactor', [
            'enabled' => $this->twoFactor->isEnabled($user),
            'required' => $this->settings->isRequiredFor($user),
            'recoveryRemaining' => count($this->twoFactor->recoveryCodes($user)),
            'primaryMethod' => $this->settings->primaryMethod(),
            'backupMethods' => collect($this->settings->backupMethods())
                ->filter(fn ($method) => $this->settings->isMethodAvailable($method, $this->gateways))
                ->values(),
            'trustedDeviceDays' => $this->settings->trustedDeviceDays(),
            'bypassActive' => $this->twoFactor->hasActiveBypass($user),
        ]);
    }

    public function enrol(Request $request): Response
    {
        $enrolment = $this->twoFactor->beginEnrolment($request->user());

        return Inertia::render('Profile/TwoFactorSetup', [
            'secret' => $enrolment['secret'],
            'qrUrl' => $enrolment['qr'],
            'recoveryCodes' => $enrolment['recovery'],
        ]);
    }

    public function confirm(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'size:6'],
        ]);

        $this->twoFactor->confirm($request->user(), $validated['code']);

        return redirect()->route('twoFactor.show')
            ->with('success', 'Two-factor authentication is on. Keep your recovery codes somewhere safe.');
    }

    public function regenerateRecovery(Request $request): RedirectResponse
    {
        $codes = $this->twoFactor->regenerateRecoveryCodes($request->user());

        return back()->with('recoveryCodes', $codes);
    }

    public function disable(Request $request): RedirectResponse
    {
        $user = $request->user();

        abort_if($this->settings->isRequiredFor($user), 422,
            'Two-factor authentication is required for your role.');

        $this->twoFactor->assertNotLastProtectedAdmin($user);

        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        audit('2fa.disabled', $user, ['self' => true]);

        return back()->with('success', 'Two-factor authentication turned off.');
    }

    public function forgetDevices(Request $request): RedirectResponse
    {
        $this->twoFactor->forgetDevices($request->user());

        return back()->with('success', 'All remembered devices will need a code again.');
    }
}
