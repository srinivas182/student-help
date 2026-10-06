<?php

namespace App\Domains\Progress\Services;

use App\Domains\Community\Models\CommunityPost;
use App\Domains\Content\Models\Resource;
use App\Domains\Tutoring\Models\HelpRequest;
use App\Domains\Tutoring\Models\Rating;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Progress for students and performance for tutors (SRS: PRG-01 – PRG-03).
 *
 * Streaks are stored as one row per active day — cheap to write, and a streak
 * is just a walk backwards through consecutive dates.
 */
class ProgressService
{
    public function record(User $user): void
    {
        $updated = DB::table('activity_days')
            ->where('user_id', $user->id)
            ->where('day', now()->toDateString())
            ->increment('actions');

        if ($updated === 0) {
            DB::table('activity_days')->insertOrIgnore([
                'user_id' => $user->id,
                'day' => now()->toDateString(),
                'actions' => 1,
            ]);
        }
    }

    public function currentStreak(User $user): int
    {
        $days = DB::table('activity_days')
            ->where('user_id', $user->id)
            ->orderByDesc('day')
            ->limit(400)
            ->pluck('day')
            ->map(fn ($day) => Carbon::parse($day)->toDateString());

        if ($days->isEmpty()) {
            return 0;
        }

        // A streak survives today being inactive, so long as yesterday was active.
        $cursor = $days->first() === now()->toDateString()
            ? now()
            : ($days->first() === now()->subDay()->toDateString() ? now()->subDay() : null);

        if ($cursor === null) {
            return 0;
        }

        $streak = 0;

        foreach ($days as $day) {
            if ($day !== $cursor->toDateString()) {
                break;
            }

            $streak++;
            $cursor = $cursor->copy()->subDay();
        }

        return $streak;
    }

    public function forStudent(User $student, int $days = 30): array
    {
        $since = now()->subDays($days);

        $requests = HelpRequest::where('student_id', $student->id);

        return [
            'requestsRaised' => (clone $requests)->count(),
            'requestsResolved' => (clone $requests)
                ->whereIn('status', [HelpRequest::STATUS_RESOLVED, HelpRequest::STATUS_CLOSED])->count(),
            'requestsOpen' => (clone $requests)
                ->whereIn('status', [HelpRequest::STATUS_OPEN, HelpRequest::STATUS_ESCALATED, HelpRequest::STATUS_ASSIGNED])
                ->count(),
            'inPeriod' => (clone $requests)->where('created_at', '>=', $since)->count(),
            'subjects' => $student->subjects()->count(),
            'subjectBreakdown' => HelpRequest::where('student_id', $student->id)
                ->select('subject_id', DB::raw('count(*) as total'))
                ->with('subject:id,name')
                ->groupBy('subject_id')
                ->orderByDesc('total')
                ->get()
                ->map(fn (HelpRequest $row) => [
                    'subject' => $row->subject?->name ?? 'Unknown',
                    'total' => (int) $row->total,
                ]),
            'questionsAsked' => CommunityPost::where('user_id', $student->id)->questions()->count(),
            'answersGiven' => CommunityPost::where('user_id', $student->id)->whereNotNull('parent_id')->count(),
            'acceptedAnswers' => CommunityPost::where('user_id', $student->id)->where('is_accepted', true)->count(),
            'streak' => $this->currentStreak($student),
            'activeDays' => DB::table('activity_days')
                ->where('user_id', $student->id)
                ->where('day', '>=', $since->toDateString())
                ->count(),
        ];
    }

    public function forTutor(User $tutor): array
    {
        $profile = $tutor->tutorProfile;

        $assigned = HelpRequest::where('tutor_id', $tutor->id);

        $acceptanceHours = HelpRequest::where('tutor_id', $tutor->id)
            ->whereNotNull('assigned_at')
            ->get()
            ->map(fn (HelpRequest $r) => $r->created_at->diffInMinutes($r->assigned_at) / 60);

        $resolutionHours = HelpRequest::where('tutor_id', $tutor->id)
            ->whereNotNull('assigned_at')
            ->whereNotNull('resolved_at')
            ->get()
            ->map(fn (HelpRequest $r) => $r->assigned_at->diffInMinutes($r->resolved_at) / 60);

        return [
            'studentsHelped' => (clone $assigned)->distinct('student_id')->count('student_id'),
            'resolved' => $profile?->resolved_count ?? 0,
            'active' => (clone $assigned)->where('status', HelpRequest::STATUS_ASSIGNED)->count(),
            'rating' => $profile?->average_rating,
            'ratingsCount' => $profile?->ratings_count ?? 0,
            'fiveStars' => Rating::where('tutor_id', $tutor->id)->where('stars', 5)->count(),
            'averageAcceptHours' => $acceptanceHours->isEmpty() ? null : round($acceptanceHours->avg(), 1),
            'averageResolveHours' => $resolutionHours->isEmpty() ? null : round($resolutionHours->avg(), 1),
            'resourcesShared' => Resource::where('uploaded_by', $tutor->id)
                ->where('status', Resource::STATUS_PUBLISHED)->count(),
            'resourceViews' => (int) Resource::where('uploaded_by', $tutor->id)->sum('views'),
            'thisMonth' => HelpRequest::where('tutor_id', $tutor->id)
                ->where('resolved_at', '>=', now()->startOfMonth())->count(),
            'streak' => $this->currentStreak($tutor),
        ];
    }

    /** Monthly leaderboard — recognition instead of payment (decision D-11). */
    public function leaderboard(int $limit = 10): array
    {
        return HelpRequest::query()
            ->whereNotNull('tutor_id')
            ->whereIn('status', [HelpRequest::STATUS_RESOLVED, HelpRequest::STATUS_CLOSED])
            ->where('resolved_at', '>=', now()->startOfMonth())
            ->select('tutor_id', DB::raw('count(*) as resolved'))
            ->groupBy('tutor_id')
            ->orderByDesc('resolved')
            ->limit($limit)
            ->with('tutor.tutorProfile')
            ->get()
            ->map(fn (HelpRequest $row) => [
                'name' => $row->tutor?->name,
                'resolved' => (int) $row->resolved,
                'rating' => $row->tutor?->tutorProfile?->average_rating,
            ])
            ->all();
    }
}
