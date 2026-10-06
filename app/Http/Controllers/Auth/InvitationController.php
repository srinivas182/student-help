<?php

namespace App\Http\Controllers\Auth;

use App\Domains\Identity\Models\AdminInvitation;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

/** Staff accept their invitation and set a password (SRS: AUTH-08). */
class InvitationController extends Controller
{
    public function show(string $token): Response
    {
        $invitation = AdminInvitation::where('token', $token)->first();

        return Inertia::render('Auth/AcceptInvitation', [
            'valid' => $invitation?->isUsable() ?? false,
            'token' => $token,
            'name' => $invitation?->name,
            'email' => $invitation?->email,
            'role' => $invitation?->role,
        ]);
    }

    public function accept(Request $request, string $token): RedirectResponse
    {
        $invitation = AdminInvitation::where('token', $token)->firstOrFail();

        abort_unless($invitation->isUsable(), 410, 'This invitation has expired or has already been used.');

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'password' => ['required', 'confirmed', Password::defaults()->min(12)->mixedCase()->numbers()->symbols()->uncompromised()],
        ], [
            'password.min' => 'Staff passwords must be at least 12 characters.',
        ]);

        $user = User::create([
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'name' => trim($validated['first_name'].' '.$validated['last_name']),
            'email' => $invitation->email,
            'password' => Hash::make($validated['password']),
            'email_verified_at' => now(),
            'role' => $invitation->role,
            'status' => 'active',
            'onboarding_completed_at' => now(),
        ]);

        $invitation->update(['accepted_at' => now()]);

        audit('admin.invitation_accepted', $user, ['role' => $user->role]);

        Auth::login($user);

        return redirect()->route('admin.dashboard')
            ->with('success', 'Welcome to DX Student Help. Please set up multi-factor authentication from your profile.');
    }
}
