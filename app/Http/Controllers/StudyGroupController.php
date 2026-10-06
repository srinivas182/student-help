<?php

namespace App\Http\Controllers;

use App\Domains\StudyGroup\Models\StudyGroup;
use App\Domains\StudyGroup\Models\StudyGroupMember;
use App\Domains\StudyGroup\Models\StudyGroupMessage;
use App\Domains\StudyGroup\Services\StudyGroupService;
use App\Domains\Tutoring\Services\ModerationService;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class StudyGroupController extends Controller
{
    public function __construct(
        private readonly StudyGroupService $groups,
        private readonly ModerationService $moderation,
    ) {
    }

    public function index(Request $request): Response
    {
        $user = $request->user();

        // Groups in the student's own subjects that they could still join
        $suggested = StudyGroup::open()
            ->whereNotIn('id', $user->studyGroups()->pluck('study_groups.id'))
            ->whereIn('curriculum_item_id', $user->subjects()->pluck('curriculum_items.id'))
            ->withCount('members')
            ->with('subject:id,name')
            ->limit(6)
            ->get()
            ->map(fn (StudyGroup $g) => [
                'id' => $g->id,
                'name' => $g->name,
                'subject' => $g->subject?->name,
                'members' => $g->members_count,
                'capacity' => $g->capacity,
            ]);

        return Inertia::render('StudyGroups/Index', [
            'groups' => $user->studyGroups()
                ->with('subject:id,name')
                ->withCount('members')
                ->get()
                ->map(fn (StudyGroup $g) => [
                    'id' => $g->id,
                    'name' => $g->name,
                    'description' => $g->description,
                    'subject' => $g->subject?->name,
                    'members' => $g->members_count,
                    'capacity' => $g->capacity,
                    'isOwner' => $g->owner_id === $user->id,
                    'isLocked' => $g->is_locked,
                ]),
            'suggested' => $suggested,
            'subjects' => $user->subjects()->get(['curriculum_items.id', 'name']),
            'canParticipate' => $user->canParticipate(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'curriculum_item_id' => [
                'nullable', 'integer',
                Rule::exists('academic_selections', 'curriculum_item_id')
                    ->where('user_id', $request->user()->id)
                    ->where('role', 'subject'),
            ],
            'capacity' => ['nullable', 'integer', 'between:2,30'],
        ], [
            'curriculum_item_id.exists' => 'Choose one of your own subjects.',
        ]);

        $group = $this->groups->create($request->user(), $validated);

        return redirect()->route('studyGroups.show', $group)
            ->with('success', "Group created. Share the code {$group->join_code} with your classmates.");
    }

    public function join(Request $request): RedirectResponse
    {
        $validated = $request->validate(['join_code' => ['required', 'string', 'max:12']]);

        $group = StudyGroup::where('join_code', strtoupper($validated['join_code']))->first();

        if (! $group) {
            return back()->withErrors(['join_code' => 'We could not find a group with that code.']);
        }

        $this->groups->join($group, $request->user());

        return redirect()->route('studyGroups.show', $group)->with('success', "You have joined {$group->name}.");
    }

    public function joinById(Request $request, StudyGroup $group): RedirectResponse
    {
        $this->groups->join($group, $request->user());

        return redirect()->route('studyGroups.show', $group)->with('success', "You have joined {$group->name}.");
    }

    public function show(Request $request, StudyGroup $group): Response
    {
        $user = $request->user();

        abort_unless($group->hasMember($user) || $user->isStaff(), 403);

        $group->load(['subject:id,name', 'owner:id,first_name,last_name']);

        return Inertia::render('StudyGroups/Show', [
            'group' => [
                'id' => $group->id,
                'name' => $group->name,
                'description' => $group->description,
                'subject' => $group->subject?->name,
                'owner' => $group->owner?->name,
                'joinCode' => $group->hasMember($user) ? $group->join_code : null,
                'isOwner' => $group->owner_id === $user->id,
                'isLocked' => $group->is_locked,
                'lockedReason' => $group->locked_reason,
                'capacity' => $group->capacity,
            ],
            'messages' => $this->serialise($group, $user),
            'members' => $group->members()->get(['users.id', 'first_name', 'last_name'])
                ->map(fn (User $member) => [
                    'id' => $member->id,
                    'name' => $member->name,
                    'role' => $member->pivot->role,
                ]),
            'canPost' => $user->canParticipate() && ! $group->is_locked && $group->hasMember($user),
        ]);
    }

    public function poll(Request $request, StudyGroup $group): JsonResponse
    {
        abort_unless($group->hasMember($request->user()), 403);

        return response()->json(['messages' => $this->serialise($group, $request->user())]);
    }

    public function post(Request $request, StudyGroup $group): RedirectResponse
    {
        $validated = $request->validate(['body' => ['required', 'string', 'max:2000']]);

        $message = $this->groups->post($group, $request->user(), $validated['body']);

        return back()->with(
            $message->body !== $message->body_original ? 'warning' : 'success',
            $message->body !== $message->body_original
                ? 'Posted. Contact details were removed — DX keeps conversations on the platform.'
                : null,
        );
    }

    public function report(Request $request, StudyGroup $group, StudyGroupMessage $message): RedirectResponse
    {
        abort_unless($message->study_group_id === $group->id, 404);
        abort_unless($group->hasMember($request->user()), 403);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'in:inappropriate,contact_details,academic_dishonesty,harassment,spam,other'],
        ]);

        $this->moderation->report($request->user(), $message, $validated['reason']);
        $this->groups->registerConcern($group);

        return back()->with('success', 'Thank you. A moderator will review this.');
    }

    public function leave(Request $request, StudyGroup $group): RedirectResponse
    {
        $this->groups->leave($group, $request->user());

        return redirect()->route('studyGroups.index')->with('success', 'You have left the group.');
    }

    public function removeMember(Request $request, StudyGroup $group, User $user): RedirectResponse
    {
        abort_unless($group->owner_id === $request->user()->id || $request->user()->isStaff(), 403);
        abort_if($user->id === $group->owner_id, 422, 'The owner cannot be removed.');

        $this->groups->remove($group, $user, $request->user());

        return back()->with('success', 'Member removed.');
    }

    private function serialise(StudyGroup $group, User $viewer): array
    {
        return $group->messages()->with('author:id,first_name,last_name')->oldest()->limit(200)->get()
            ->map(fn (StudyGroupMessage $m) => [
                'id' => $m->id,
                'body' => $m->visibleBody(),
                'author' => $m->author?->first_name,
                'isMine' => $m->user_id === $viewer->id,
                'isRemoved' => $m->is_removed,
                'sentAt' => $m->created_at?->format('H:i'),
            ])->all();
    }
}
