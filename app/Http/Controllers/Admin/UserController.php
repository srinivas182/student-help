<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Identity\Models\AdminInvitation;
use App\Domains\Tutoring\Services\ModerationService;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\AdminInvited;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function __construct(private readonly ModerationService $moderation)
    {
    }

    /** ADM-01: search and filter every account. */
    public function index(Request $request): Response
    {
        $users = User::query()
            ->when($request->string('search')->toString(), fn ($q, $term) => $q->where(
                fn ($query) => $query
                    ->where('first_name', 'like', "%{$term}%")
                    ->orWhere('last_name', 'like', "%{$term}%")
                    ->orWhere('email', 'like', "%{$term}%"),
            ))
            ->when($request->string('role')->toString(), fn ($q, $role) => $q->where('role', $role))
            ->when($request->string('status')->toString(), fn ($q, $status) => $q->where('status', $status))
            ->when($request->boolean('awaiting_consent'), fn ($q) => $q
                ->whereHas('guardianConsent', fn ($c) => $c->where('status', 'pending')))
            ->with('guardianConsent')
            ->latest()
            ->paginate(20)
            ->withQueryString()
            ->through(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'status' => $user->status,
                'isMinor' => $user->isMinor(),
                'consent' => $user->guardianConsent?->status,
                'joined' => $user->created_at?->toFormattedDateString(),
            ]);

        return Inertia::render('Admin/Users/Index', [
            'users' => $users,
            'filters' => $request->only('search', 'role', 'status', 'awaiting_consent'),
            'roles' => [User::ROLE_STUDENT, User::ROLE_TUTOR, User::ROLE_MODERATOR, User::ROLE_ADMIN, User::ROLE_SUPER_ADMIN],
            'canInvite' => $request->user()->role === User::ROLE_SUPER_ADMIN
                || $request->user()->role === User::ROLE_ADMIN,
            'invitations' => AdminInvitation::whereNull('accepted_at')
                ->where('expires_at', '>', now())
                ->get(['id', 'name', 'email', 'role', 'expires_at']),
        ]);
    }

    public function suspend(Request $request, User $user): RedirectResponse
    {
        abort_if($user->id === $request->user()->id, 422, 'You cannot suspend your own account.');

        $validated = $request->validate(['reason' => ['required', 'string', 'max:500']]);

        $this->moderation->suspendUser($user, $request->user(), $validated['reason']);

        return back()->with('success', $user->first_name.' has been suspended.');
    }

    public function reinstate(Request $request, User $user): RedirectResponse
    {
        $this->moderation->reinstateUser($user, $request->user());

        return back()->with('success', $user->first_name.' has been reinstated.');
    }

    /** ADM-02: staff accounts exist only by invitation. */
    public function invite(Request $request): RedirectResponse
    {
        abort_unless(in_array($request->user()->role, [User::ROLE_ADMIN, User::ROLE_SUPER_ADMIN], true), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email', 'unique:admin_invitations,email'],
            'role' => ['required', Rule::in([User::ROLE_MODERATOR, User::ROLE_ADMIN])],
        ]);

        $invitation = AdminInvitation::create([
            ...$validated,
            'token' => Str::random(64),
            'invited_by' => $request->user()->id,
            'expires_at' => now()->addDays(7),
        ]);

        \Illuminate\Support\Facades\Notification::route('mail', $invitation->email)
            ->notify(new AdminInvited($invitation));

        audit('admin.invited', $invitation, ['role' => $invitation->role]);

        return back()->with('success', "Invitation sent to {$invitation->email}.");
    }

    public function revokeInvitation(Request $request, AdminInvitation $invitation): RedirectResponse
    {
        abort_unless(in_array($request->user()->role, [User::ROLE_ADMIN, User::ROLE_SUPER_ADMIN], true), 403);

        $invitation->delete();

        audit('admin.invitation_revoked', $invitation);

        return back()->with('success', 'Invitation revoked.');
    }
}
