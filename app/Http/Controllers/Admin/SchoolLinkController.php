<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Classroom\Models\Classroom;
use App\Domains\Classroom\Services\ClassroomService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Approving a school link means DX is letting a teacher use a real
 * institution's name in front of learners. It is a trust decision, so it gets
 * its own queue rather than being buried in settings.
 */
class SchoolLinkController extends Controller
{
    public function __construct(private readonly ClassroomService $classrooms)
    {
    }

    public function index(Request $request): Response
    {
        $status = $request->string('status')->toString() ?: Classroom::LINK_PENDING;

        return Inertia::render('Admin/SchoolLinks', [
            'classrooms' => Classroom::where('type', Classroom::TYPE_SCHOOL)
                ->orWhere('school_link_status', $status)
                ->where('school_link_status', $status)
                ->with(['teacher:id,first_name,last_name,email', 'institution:id,name,type,city', 'subject:id,name'])
                ->withCount('students')
                ->oldest()
                ->paginate(15)
                ->withQueryString()
                ->through(fn (Classroom $c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                    'teacher' => $c->teacher?->name,
                    'teacherEmail' => $c->teacher?->email,
                    'institution' => $c->institution?->name,
                    'institutionType' => $c->institution?->type,
                    'city' => $c->institution?->city,
                    'subject' => $c->subject?->name,
                    'students' => $c->students_count,
                    'notes' => $c->school_link_notes,
                    'requestedAt' => $c->created_at?->diffForHumans(),
                ]),
            'filters' => ['status' => $status],
            'counts' => [
                'pending' => Classroom::where('school_link_status', Classroom::LINK_PENDING)->count(),
                'approved' => Classroom::where('school_link_status', Classroom::LINK_APPROVED)->count(),
                'rejected' => Classroom::where('school_link_status', Classroom::LINK_REJECTED)->count(),
            ],
        ]);
    }

    public function approve(Request $request, Classroom $classroom): RedirectResponse
    {
        $this->classrooms->approveSchoolLink($classroom, $request->user());

        return back()->with('success', 'Approved. The class now shows the school name.');
    }

    public function reject(Request $request, Classroom $classroom): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:500'],
        ]);

        $this->classrooms->rejectSchoolLink($classroom, $request->user(), $validated['reason']);

        return back()->with('success', 'Rejected. The class continues as the teacher\'s own group.');
    }
}
