<?php

namespace App\Http\Controllers;

use App\Domains\Progress\Services\ProgressService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProgressController extends Controller
{
    public function __construct(private readonly ProgressService $progress)
    {
    }

    public function __invoke(Request $request): Response
    {
        $user = $request->user();

        if ($user->isTutor()) {
            return Inertia::render('Progress/Tutor', [
                'stats' => $this->progress->forTutor($user),
                'leaderboard' => $this->progress->leaderboard(),
                'isVerified' => $user->isVerifiedTutor(),
                'name' => $user->name,
            ]);
        }

        return Inertia::render('Progress/Student', [
            'stats' => $this->progress->forStudent($user),
            'name' => $user->first_name ?? $user->name,
        ]);
    }

    /**
     * A printable record of what a tutor has contributed — genuinely useful on a
     * student tutor's CV, and the main non-financial reason to keep showing up.
     */
    public function certificate(Request $request): Response
    {
        $user = $request->user();

        abort_unless($user->isVerifiedTutor(), 403);

        $stats = $this->progress->forTutor($user);

        return Inertia::render('Progress/Certificate', [
            'name' => $user->name,
            'stats' => $stats,
            'subjects' => $user->subjects()->pluck('name'),
            'since' => $user->tutorProfile?->reviewed_at?->toFormattedDateString(),
            'issuedOn' => now()->toFormattedDateString(),
            'reference' => 'DX-'.str_pad((string) $user->id, 5, '0', STR_PAD_LEFT).'-'.now()->format('Ym'),
        ]);
    }
}
