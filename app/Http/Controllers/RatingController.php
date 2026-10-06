<?php

namespace App\Http\Controllers;

use App\Domains\Tutoring\Models\HelpRequest;
use App\Domains\Tutoring\Models\Rating;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RatingController extends Controller
{
    /** RAT-01 – RAT-04: one rating per request, by the student, after resolution. */
    public function store(Request $request, HelpRequest $helpRequest): RedirectResponse
    {
        abort_unless($helpRequest->student_id === $request->user()->id, 403);
        abort_unless($helpRequest->tutor_id !== null, 422);
        abort_unless(
            in_array($helpRequest->status, [HelpRequest::STATUS_RESOLVED, HelpRequest::STATUS_CLOSED], true),
            422,
        );
        abort_if($helpRequest->rating()->exists(), 422, 'This request has already been rated.');

        $validated = $request->validate([
            'stars' => ['required', 'integer', 'between:1,5'],
            'comment' => ['nullable', 'string', 'max:500'],
        ]);

        DB::transaction(function () use ($helpRequest, $validated) {
            Rating::create([
                'help_request_id' => $helpRequest->id,
                'student_id' => $helpRequest->student_id,
                'tutor_id' => $helpRequest->tutor_id,
                'stars' => $validated['stars'],
                'comment' => $validated['comment'] ?? null,
            ]);

            $this->recalculate($helpRequest);
        });

        audit('rating.created', $helpRequest, ['stars' => $validated['stars']]);

        if ($validated['stars'] <= 2) {
            // RAT-04: low ratings are surfaced to administrators.
            audit('rating.low_flagged', $helpRequest, ['stars' => $validated['stars']]);
        }

        return back()->with('success', 'Thank you for rating your tutor.');
    }

    private function recalculate(HelpRequest $helpRequest): void
    {
        $profile = $helpRequest->tutor?->tutorProfile;

        if (! $profile) {
            return;
        }

        $stats = Rating::where('tutor_id', $helpRequest->tutor_id)
            ->selectRaw('count(*) as total, avg(stars) as average')
            ->first();

        $profile->update([
            'ratings_count' => (int) $stats->total,
            'average_rating' => round((float) $stats->average, 2),
        ]);
    }
}
