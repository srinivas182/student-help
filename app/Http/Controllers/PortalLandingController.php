<?php

namespace App\Http\Controllers;

use App\Domains\Identity\Portal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use App\Domains\Curriculum\Models\CurriculumItem;
use App\Domains\Tutor\Models\Topic;
use App\Domains\Tutoring\Models\HelpRequest;
use App\Domains\Tutoring\Models\TutorProfile;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The public landing page. Which one a visitor sees depends on the domain they
 * arrived at, and each page links clearly to the other door.
 */
class PortalLandingController extends Controller
{
    public function __construct(private readonly Portal $portal)
    {
    }

    public function __invoke(Request $request): Response|RedirectResponse
    {
        if ($user = $request->user()) {
            $home = config('portals.'.$this->portal->forUser($user).'.home');

            return redirect()->route($home);
        }

        $current = $this->portal->current($request);
        $other = $current === Portal::STUDENT ? Portal::TEACHER : Portal::STUDENT;

        $brand = [
            'key' => $current,
            'name' => (string) config("portals.{$current}.name"),
            'otherName' => (string) config("portals.{$other}.name"),
            'otherUrl' => $this->portal->urlFor($other),
        ];

        // Cached: a landing page should not query on every visit.
        $stats = Cache::remember("landing.stats.{$current}", now()->addMinutes(15), function () use ($current) {
            if ($current === Portal::TEACHER) {
                return [
                    'students' => User::where('role', User::ROLE_STUDENT)->count(),
                    'openRequests' => HelpRequest::whereIn('status', [
                        HelpRequest::STATUS_OPEN, HelpRequest::STATUS_ESCALATED,
                    ])->count(),
                    'subjects' => CurriculumItem::ofType(CurriculumItem::TYPE_SUBJECT)->active()->count(),
                ];
            }

            return [
                'tutors' => TutorProfile::where('verification_status', TutorProfile::STATUS_APPROVED)->count(),
                'subjects' => CurriculumItem::ofType(CurriculumItem::TYPE_SUBJECT)->active()->count(),
                'topics' => Topic::published()->count(),
            ];
        });

        return Inertia::render(
            $current === Portal::TEACHER ? 'Public/TeacherLanding' : 'Public/StudentLanding',
            ['brand' => $brand, 'stats' => $stats],
        );
    }
}
