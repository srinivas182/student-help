<?php

namespace App\Http\Controllers\Tutor;

use App\Domains\Content\Models\Resource;
use App\Domains\Tutoring\Models\HelpRequest;
use App\Domains\Tutoring\Models\HelpRequestOffer;
use App\Domains\Tutoring\Models\Rating;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The teacher portal home. Everything a teacher needs on arrival: who is
 * waiting, what is in progress, and the impact of their work.
 */
class TeacherDashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $teacher = $request->user();
        $profile = $teacher->tutorProfile;

        $waiting = HelpRequest::whereNull('tutor_id')
            ->openForMatching()
            ->whereHas('offers', fn ($q) => $q
                ->where('tutor_id', $teacher->id)
                ->where('status', HelpRequestOffer::STATUS_OFFERED))
            ->with(['subject:id,name', 'student:id,first_name'])
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn (HelpRequest $r) => [
                'id' => $r->id,
                'topic' => $r->topic,
                'subject' => $r->subject?->name,
                'student' => $r->student?->first_name,
                'waiting' => $r->created_at?->diffForHumans(null, true),
                'isEscalated' => $r->status === HelpRequest::STATUS_ESCALATED,
            ]);

        $active = HelpRequest::where('tutor_id', $teacher->id)
            ->whereIn('status', [HelpRequest::STATUS_ASSIGNED, HelpRequest::STATUS_RESOLVED])
            ->with(['subject:id,name', 'student:id,first_name'])
            ->latest('last_activity_at')
            ->limit(5)
            ->get()
            ->map(fn (HelpRequest $r) => [
                'id' => $r->id,
                'topic' => $r->topic,
                'subject' => $r->subject?->name,
                'student' => $r->student?->first_name,
                'status' => $r->status,
                'updatedAt' => $r->last_activity_at?->diffForHumans(),
            ]);

        $studentsHelped = HelpRequest::where('tutor_id', $teacher->id)
            ->distinct('student_id')
            ->count('student_id');

        return Inertia::render('Teacher/Dashboard', [
            'verification' => [
                'status' => $profile?->verification_status,
                'isVerified' => $teacher->isVerifiedTutor(),
                'isAvailable' => (bool) ($profile?->is_available ?? false),
                'notes' => $profile?->verification_notes,
            ],
            'impact' => [
                'studentsHelped' => $studentsHelped,
                'resolved' => $profile?->resolved_count ?? 0,
                'rating' => $profile?->average_rating,
                'ratingsCount' => $profile?->ratings_count ?? 0,
                'materialShared' => Resource::where('uploaded_by', $teacher->id)
                    ->where('status', Resource::STATUS_PUBLISHED)->count(),
                'materialViews' => (int) Resource::where('uploaded_by', $teacher->id)->sum('views'),
                'thisMonth' => HelpRequest::where('tutor_id', $teacher->id)
                    ->where('resolved_at', '>=', now()->startOfMonth())->count(),
            ],
            'waiting' => $waiting,
            'active' => $active,
            'subjects' => $teacher->subjects()->pluck('name'),
            'recentFeedback' => Rating::where('tutor_id', $teacher->id)
                ->whereNotNull('comment')
                ->latest()
                ->limit(3)
                ->get()
                ->map(fn (Rating $r) => ['stars' => $r->stars, 'comment' => $r->comment]),
        ]);
    }
}
