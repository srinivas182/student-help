<?php

namespace App\Http\Controllers;

use App\Domains\Classroom\Models\ClassSession;
use App\Domains\Classroom\Services\SessionService;
use App\Domains\Content\Models\Resource;
use App\Domains\Tutor\Models\Topic;
use App\Domains\Tutoring\Models\TutorProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * One screen showing what the platform can do, with live counts rather than
 * marketing numbers: a learner who is told there are 40 lessons and finds two
 * trusts nothing else on the page.
 */
class ExploreController extends Controller
{
    public function __construct(private readonly SessionService $sessions)
    {
    }

    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $subjectIds = $user->subjects()->pluck('curriculum_items.id');

        return Inertia::render('Explore', [
            'liveSessions' => $this->sessions->upcomingFor($user, 3)
                ->map(fn (ClassSession $session) => [
                    'id' => $session->id,
                    'title' => $session->title,
                    'classroom' => $session->classroom?->name,
                    'classroomId' => $session->classroom_id,
                    'whenLabel' => $session->whenLabel(),
                    'mode' => $session->mode,
                    'isJoinable' => $session->isJoinable(),
                    'isLive' => $session->isLive(),
                ]),

            'counts' => [
                'topics' => $subjectIds->isEmpty() ? 0 : Topic::published()
                    ->whereIn('curriculum_item_id', $subjectIds)->count(),
                'tutors' => TutorProfile::where('verification_status', TutorProfile::STATUS_APPROVED)
                    ->when($subjectIds->isNotEmpty(), fn ($q) => $q->whereHas(
                        'subjects',
                        fn ($s) => $s->whereIn('curriculum_items.id', $subjectIds),
                    ))->count(),
                'groups' => $subjectIds->isEmpty() ? 0 : DB::table('study_groups')
                    ->whereIn('curriculum_item_id', $subjectIds)
                    ->where('is_locked', false)
                    ->count(),
                'resources' => $subjectIds->isEmpty() ? 0 : Resource::where('status', 'published')
                    ->whereHas('curriculumItems', fn ($q) => $q->whereIn('curriculum_items.id', $subjectIds))
                    ->count(),
            ],

            'canParticipate' => $user->canParticipate(),
        ]);
    }
}
