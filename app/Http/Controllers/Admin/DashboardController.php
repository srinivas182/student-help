<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Tutoring\Models\HelpRequest;
use App\Domains\Tutoring\Models\Rating;
use App\Domains\Tutoring\Models\Report;
use App\Domains\Tutoring\Models\TutorProfile;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Operational dashboard (SRS: ADM-03).
 * Everything DX needs to run the platform day to day, on one screen.
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $days = (int) $request->integer('days', 30);
        $since = now()->subDays($days);

        return Inertia::render('Admin/Dashboard', [
            'days' => $days,
            'people' => [
                'students' => User::where('role', User::ROLE_STUDENT)->count(),
                'newStudents' => User::where('role', User::ROLE_STUDENT)->where('created_at', '>=', $since)->count(),
                'tutors' => TutorProfile::where('verification_status', TutorProfile::STATUS_APPROVED)->count(),
                'pendingTutors' => TutorProfile::where('verification_status', TutorProfile::STATUS_PENDING)->count(),
                'awaitingConsent' => DB::table('guardian_consents')->where('status', 'pending')->count(),
                'suspended' => User::where('status', 'suspended')->count(),
            ],
            'requests' => [
                'total' => HelpRequest::count(),
                'inPeriod' => HelpRequest::where('created_at', '>=', $since)->count(),
                'byStatus' => HelpRequest::selectRaw('status, count(*) as total')
                    ->groupBy('status')->pluck('total', 'status'),
                'unassigned' => HelpRequest::whereNull('tutor_id')
                    ->whereIn('status', [HelpRequest::STATUS_OPEN, HelpRequest::STATUS_ESCALATED])->count(),
                'escalated' => HelpRequest::where('status', HelpRequest::STATUS_ESCALATED)->count(),
            ],
            'service' => [
                'medianAcceptHours' => $this->medianHours('created_at', 'assigned_at'),
                'medianResolveHours' => $this->medianHours('assigned_at', 'resolved_at'),
                'resolutionRate' => $this->resolutionRate(),
                'averageRating' => round((float) Rating::avg('stars'), 2),
                'lowRatings' => Rating::where('stars', '<=', 2)->count(),
            ],
            'safety' => [
                'openReports' => Report::where('status', Report::STATUS_OPEN)->count(),
                'highPriority' => Report::where('status', Report::STATUS_OPEN)
                    ->where('severity', Report::SEVERITY_HIGH)->count(),
                'flaggedMessages' => DB::table('messages')->where('is_flagged', true)->count(),
            ],
            'topSubjects' => HelpRequest::select('subject_id', DB::raw('count(*) as total'))
                ->with('subject:id,name')
                ->where('created_at', '>=', $since)
                ->groupBy('subject_id')
                ->orderByDesc('total')
                ->limit(8)
                ->get()
                ->map(fn (HelpRequest $row) => [
                    'subject' => $row->subject?->name ?? 'Unknown',
                    'total' => (int) $row->total,
                ]),
            'busiestTutors' => TutorProfile::with('user:id,first_name,last_name')
                ->where('verification_status', TutorProfile::STATUS_APPROVED)
                ->orderByDesc('resolved_count')
                ->limit(5)
                ->get()
                ->map(fn (TutorProfile $p) => [
                    'name' => $p->user?->name,
                    'resolved' => $p->resolved_count,
                    'rating' => $p->average_rating,
                ]),
        ]);
    }

    private function medianHours(string $from, string $to): ?float
    {
        $values = HelpRequest::whereNotNull($from)->whereNotNull($to)->get()
            ->map(fn (HelpRequest $r) => $r->{$from}->diffInMinutes($r->{$to}) / 60)
            ->filter(fn ($hours) => $hours >= 0)
            ->sort()
            ->values();

        return $values->isEmpty() ? null : round($values[(int) floor($values->count() / 2)], 1);
    }

    private function resolutionRate(): ?float
    {
        $assigned = HelpRequest::whereNotNull('assigned_at')->count();

        if ($assigned === 0) {
            return null;
        }

        $resolved = HelpRequest::whereIn('status', [HelpRequest::STATUS_RESOLVED, HelpRequest::STATUS_CLOSED])->count();

        return round($resolved / $assigned * 100, 1);
    }
}
