<?php

namespace App\Domains\Tutoring\Services;

use App\Domains\Curriculum\Models\CurriculumItem;
use App\Domains\Tutoring\Models\HelpRequest;
use App\Domains\Tutoring\Models\HelpRequestOffer;
use App\Domains\Tutoring\Models\TutorProfile;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Routes a help request to tutors who are actually qualified to answer it
 * (SRS: REQ-03 – REQ-06).
 *
 * A tutor is eligible when they are verified, available, not suspended, and
 * teach the exact subject. Tutors who declined are never offered it again.
 */
class MatchingService
{
    /** @return Collection<int, User> */
    public function eligibleTutors(HelpRequest $request): Collection
    {
        $declined = $request->offers()
            ->where('status', HelpRequestOffer::STATUS_DECLINED)
            ->pluck('tutor_id');

        return User::query()
            ->where('role', User::ROLE_TUTOR)
            ->where('status', 'active')
            ->whereNotIn('id', $declined)
            ->whereHas('tutorProfile', fn ($q) => $q
                ->where('verification_status', TutorProfile::STATUS_APPROVED)
                ->where('is_available', true)
                ->whereHas('subjects', fn ($s) => $s->where('curriculum_items.id', $request->subject_id)))
            ->with('tutorProfile')
            ->get()
            // Best-rated and least-loaded tutors first, so work spreads fairly.
            ->sortByDesc(fn (User $tutor) => ($tutor->tutorProfile->average_rating ?? 3.5) * 10
                - $tutor->assignedRequests()->where('status', HelpRequest::STATUS_ASSIGNED)->count())
            ->values();
    }

    /** Offer the request to every eligible tutor. Returns how many were notified. */
    public function offer(HelpRequest $request): int
    {
        $tutors = $this->eligibleTutors($request);

        foreach ($tutors as $tutor) {
            HelpRequestOffer::firstOrCreate(
                ['help_request_id' => $request->id, 'tutor_id' => $tutor->id],
                ['status' => HelpRequestOffer::STATUS_OFFERED],
            );
        }

        return $tutors->count();
    }

    /** How many verified tutors cover a subject — drives subject go-live (D-08). */
    public function coverage(CurriculumItem $subject): int
    {
        return TutorProfile::query()
            ->where('verification_status', TutorProfile::STATUS_APPROVED)
            ->whereHas('subjects', fn ($q) => $q->where('curriculum_items.id', $subject->id))
            ->count();
    }

    /** REQ-06: median hours to acceptance, shown on the request form. */
    public function typicalAcceptanceHours(CurriculumItem $subject): ?float
    {
        $hours = HelpRequest::query()
            ->where('subject_id', $subject->id)
            ->whereNotNull('assigned_at')
            ->get()
            ->map(fn (HelpRequest $r) => $r->created_at->diffInMinutes($r->assigned_at) / 60)
            ->sort()
            ->values();

        if ($hours->isEmpty()) {
            return null;
        }

        return round($hours[(int) floor($hours->count() / 2)], 1);
    }
}
