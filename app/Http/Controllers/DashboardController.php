<?php

namespace App\Http\Controllers;

use App\Domains\Engagement\Services\NextActionService;
use App\Domains\Engagement\Services\StreakService;
use App\Domains\Engagement\Services\SubjectProgressService;
use App\Domains\Tutoring\Models\HelpRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        private readonly NextActionService $nextActions,
        private readonly StreakService $streaks,
        private readonly SubjectProgressService $subjectProgress,
    ) {
    }

    public function __invoke(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        if (! $user->hasCompletedOnboarding() && ! $user->isStaff()) {
            return redirect()->route('onboarding.show');
        }

        $subjects = $user->subjects()->get(['curriculum_items.id', 'name', 'code']);

        $requests = HelpRequest::query()
            ->when($user->isTutor(), fn ($q) => $q->where('tutor_id', $user->id))
            ->when(! $user->isTutor(), fn ($q) => $q->where('student_id', $user->id))
            ->with(['subject:id,name', 'tutor:id,first_name,last_name', 'student:id,first_name,last_name'])
            ->latest('last_activity_at')
            ->limit(5)
            ->get()
            ->map(fn (HelpRequest $r) => [
                'id' => $r->id,
                'topic' => $r->topic,
                'subject' => $r->subject?->name,
                'status' => $r->status,
                'counterpart' => $user->isTutor() ? $r->student?->name : $r->tutor?->name,
                'updated' => $r->last_activity_at?->diffForHumans(),
            ]);

        $isStudent = ! $user->isTutor() && ! $user->isStaff();

        return Inertia::render('Dashboard', [
            // The one thing a student should see first: what to do now
            'nextActions' => $isStudent ? $this->nextActions->forStudent($user) : [],
            'streak' => $isStudent ? $this->streaks->summary($user) : null,
            'subjectProgress' => $isStudent ? $this->subjectProgress->forStudent($user) : [],

            // Counts for the four feature blocks, scoped to this learner's own
            // subjects: being told there are forty lessons and finding two is
            // how a learner stops believing the rest of the page
            'featureCounts' => $isStudent ? $this->featureCounts($user) : null,
            'context' => $user->academicContext()->orderBy('position')->pluck('name'),
            'subjects' => $subjects,
            'requests' => $requests,
            'stats' => [
                'open' => $user->isTutor()
                    ? HelpRequest::where('tutor_id', $user->id)->where('status', HelpRequest::STATUS_ASSIGNED)->count()
                    : HelpRequest::where('student_id', $user->id)->whereIn('status', [HelpRequest::STATUS_OPEN, HelpRequest::STATUS_ESCALATED, HelpRequest::STATUS_ASSIGNED])->count(),
                'resolved' => $user->isTutor()
                    ? ($user->tutorProfile?->resolved_count ?? 0)
                    : HelpRequest::where('student_id', $user->id)->whereIn('status', [HelpRequest::STATUS_RESOLVED, HelpRequest::STATUS_CLOSED])->count(),
                'subjects' => $subjects->count(),
                'rating' => $user->isTutor() ? $user->tutorProfile?->average_rating : null,
            ],
            'participation' => [
                'canParticipate' => $user->canParticipate(),
                'isMinor' => $user->isMinor(),
                'consentStatus' => $user->guardianConsent?->status,
                'canSelfStudy' => $user->canSelfStudy(),
            ],
        ]);
    }

    /** Live numbers for the feature blocks, never decorative ones. */
    private function featureCounts(\App\Models\User $user): array
    {
        $subjectIds = $user->subjects()->pluck('curriculum_items.id');

        return [
            'topics' => $subjectIds->isEmpty() ? 0 : \App\Domains\Tutor\Models\Topic::published()
                ->whereIn('curriculum_item_id', $subjectIds)->count(),

            'tutors' => \App\Domains\Tutoring\Models\TutorProfile::query()
                ->where('verification_status', \App\Domains\Tutoring\Models\TutorProfile::STATUS_APPROVED)
                ->when($subjectIds->isNotEmpty(), fn ($q) => $q->whereHas(
                    'subjects',
                    fn ($s) => $s->whereIn('curriculum_items.id', $subjectIds),
                ))->count(),

            'groups' => $subjectIds->isEmpty() ? 0 : \Illuminate\Support\Facades\DB::table('study_groups')
                ->whereIn('curriculum_item_id', $subjectIds)
                ->where('is_locked', false)
                ->count(),

            'sessions' => app(\App\Domains\Classroom\Services\SessionService::class)
                ->upcomingFor($user, 20)
                ->count(),
        ];
    }
}
