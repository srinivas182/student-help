<?php

namespace App\Http\Controllers;

use App\Domains\Identity\Models\GuardianConsent;
use App\Domains\Identity\Services\ConsentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Public, unauthenticated pages used by a parent or guardian (SRS: CON-02).
 * The token in the link is the only credential, and it expires after 7 days.
 */
class ConsentController extends Controller
{
    public function __construct(private readonly ConsentService $consent)
    {
    }

    public function show(string $token): Response
    {
        $consent = GuardianConsent::where('token', $token)->with('user')->first();

        return Inertia::render('Consent/Decide', [
            'found' => $consent !== null,
            'expired' => $consent !== null && ! $this->consent->isLinkValid($consent),
            'alreadyDecided' => $consent?->decided_at !== null,
            'status' => $consent?->status,
            'token' => $token,
            'learner' => $consent ? [
                'name' => $consent->user->name,
                'age' => $consent->user->age(),
            ] : null,
            'guardianName' => $consent?->guardian_name,
        ]);
    }

    public function decide(Request $request, string $token): RedirectResponse
    {
        $consent = GuardianConsent::where('token', $token)->firstOrFail();

        abort_unless($this->consent->isLinkValid($consent), 410, 'This approval link has expired.');
        abort_unless($consent->decided_at === null, 409, 'This request has already been answered.');

        $validated = $request->validate([
            'decision' => ['required', 'in:approve,decline'],
        ]);

        $this->consent->decide($consent, $validated['decision'] === 'approve', $request->ip());

        return redirect()->route('consent.show', $token);
    }

    /** The learner can ask for the email to be sent again. */
    public function resend(Request $request): RedirectResponse
    {
        $consent = $request->user()->guardianConsent;

        abort_unless($consent !== null, 404);
        abort_unless($consent->status === GuardianConsent::STATUS_PENDING, 422);

        $this->consent->send($consent);

        return back()->with('success', 'We have emailed your parent or guardian again.');
    }
}
