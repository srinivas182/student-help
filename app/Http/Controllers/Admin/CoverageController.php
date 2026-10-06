<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Curriculum\Models\CurriculumItem;
use App\Domains\Tutoring\Models\HelpRequest;
use App\Domains\Tutoring\Models\TutorProfile;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Subject coverage (decision D-08).
 *
 * The single biggest launch risk is a student asking a question in a subject no
 * tutor covers. This screen tells DX exactly where the gaps are before launch.
 */
class CoverageController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $minimum = (int) setting('minimum_tutors_per_subject', 3);

        $tutorCounts = DB::table('tutor_subjects')
            ->join('tutor_profiles', 'tutor_profiles.id', '=', 'tutor_subjects.tutor_profile_id')
            ->where('tutor_profiles.verification_status', TutorProfile::STATUS_APPROVED)
            ->select('tutor_subjects.curriculum_item_id', DB::raw('count(*) as tutors'))
            ->groupBy('tutor_subjects.curriculum_item_id')
            ->pluck('tutors', 'curriculum_item_id');

        $demand = DB::table('academic_selections')
            ->where('role', 'subject')
            ->select('curriculum_item_id', DB::raw('count(*) as students'))
            ->groupBy('curriculum_item_id')
            ->pluck('students', 'curriculum_item_id');

        $requestCounts = HelpRequest::select('subject_id', DB::raw('count(*) as total'))
            ->groupBy('subject_id')
            ->pluck('total', 'subject_id');

        // Only subjects somebody is actually studying or asking about.
        $subjectIds = $demand->keys()->merge($tutorCounts->keys())->merge($requestCounts->keys())->unique();

        $rows = CurriculumItem::whereIn('id', $subjectIds)
            ->with('parent.parent')
            ->get()
            ->map(function (CurriculumItem $subject) use ($tutorCounts, $demand, $requestCounts, $minimum) {
                $tutors = (int) ($tutorCounts[$subject->id] ?? 0);

                return [
                    'id' => $subject->id,
                    'name' => $subject->name,
                    'context' => collect($subject->ancestors())->pluck('name')->take(3)->implode(' · '),
                    'tutors' => $tutors,
                    'students' => (int) ($demand[$subject->id] ?? 0),
                    'requests' => (int) ($requestCounts[$subject->id] ?? 0),
                    'isLive' => $tutors >= $minimum,
                    'shortfall' => max(0, $minimum - $tutors),
                ];
            })
            ->sortBy([['isLive', 'asc'], ['students', 'desc']])
            ->values();

        return Inertia::render('Admin/Coverage', [
            'subjects' => $rows,
            'minimum' => $minimum,
            'summary' => [
                'live' => $rows->where('isLive', true)->count(),
                'belowThreshold' => $rows->where('isLive', false)->count(),
                'noTutors' => $rows->where('tutors', 0)->count(),
                'verifiedTutors' => TutorProfile::where('verification_status', TutorProfile::STATUS_APPROVED)->count(),
            ],
        ]);
    }
}
