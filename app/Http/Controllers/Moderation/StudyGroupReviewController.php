<?php

namespace App\Http\Controllers\Moderation;

use App\Domains\StudyGroup\Models\StudyGroup;
use App\Domains\StudyGroup\Models\StudyGroupMessage;
use App\Domains\StudyGroup\Services\StudyGroupService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Study groups have no adult in the room, so moderators get the same visibility
 * here as they have over private conversations — and groups that collect
 * concerns rise to the top by themselves.
 */
class StudyGroupReviewController extends Controller
{
    public function __construct(private readonly StudyGroupService $groups)
    {
    }

    public function index(Request $request): Response
    {
        $filter = $request->string('filter')->toString() ?: 'flagged';

        return Inertia::render('Moderation/StudyGroups', [
            'groups' => StudyGroup::query()
                ->when($filter === 'flagged', fn ($q) => $q->where('is_flagged', true))
                ->when($filter === 'locked', fn ($q) => $q->where('is_locked', true))
                ->with(['owner:id,first_name,last_name', 'subject:id,name'])
                ->withCount(['members', 'messages'])
                ->orderByDesc('is_flagged')
                ->orderByDesc('report_count')
                ->paginate(15)
                ->withQueryString()
                ->through(fn (StudyGroup $g) => [
                    'id' => $g->id,
                    'name' => $g->name,
                    'subject' => $g->subject?->name,
                    'owner' => $g->owner?->name,
                    'members' => $g->members_count,
                    'messages' => $g->messages_count,
                    'reports' => $g->report_count,
                    'isFlagged' => $g->is_flagged,
                    'isLocked' => $g->is_locked,
                    'lockedReason' => $g->locked_reason,
                    'createdAt' => $g->created_at?->diffForHumans(),
                ]),
            'filters' => ['filter' => $filter],
            'counts' => [
                'flagged' => StudyGroup::where('is_flagged', true)->count(),
                'locked' => StudyGroup::where('is_locked', true)->count(),
                'all' => StudyGroup::count(),
            ],
        ]);
    }

    /** The full, unmasked thread. Every view is recorded. */
    public function show(Request $request, StudyGroup $group): Response
    {
        audit('study_group.viewed_by_moderator', $group, ['moderator_id' => $request->user()->id]);

        $group->load(['owner:id,first_name,last_name', 'subject:id,name']);

        return Inertia::render('Moderation/StudyGroupThread', [
            'group' => [
                'id' => $group->id,
                'name' => $group->name,
                'subject' => $group->subject?->name,
                'owner' => $group->owner?->name,
                'reports' => $group->report_count,
                'isLocked' => $group->is_locked,
            ],
            'messages' => $group->messages()->with('author:id,first_name,last_name,role')->oldest()->get()
                ->map(fn (StudyGroupMessage $m) => [
                    'id' => $m->id,
                    'author' => $m->author?->name,
                    'isMinor' => $m->author?->isMinor(),
                    'body' => $m->body,
                    'original' => $m->body_original,
                    'wasMasked' => $m->body !== $m->body_original,
                    'isFlagged' => $m->is_flagged,
                    'isRemoved' => $m->is_removed,
                    'sentAt' => $m->created_at?->toDayDateTimeString(),
                ]),
            'members' => $group->members()->get(['users.id', 'first_name', 'last_name', 'date_of_birth'])
                ->map(fn ($member) => [
                    'id' => $member->id,
                    'name' => $member->name,
                    'isMinor' => $member->isMinor(),
                ]),
        ]);
    }

    public function removeMessage(Request $request, StudyGroupMessage $message): RedirectResponse
    {
        $message->update(['is_removed' => true]);

        audit('study_group.message_removed', $message, ['moderator_id' => $request->user()->id]);

        return back()->with('success', 'Message removed from the group.');
    }

    public function lock(Request $request, StudyGroup $group): RedirectResponse
    {
        $validated = $request->validate(['reason' => ['required', 'string', 'min:10', 'max:500']]);

        $this->groups->lock($group, $request->user(), $validated['reason']);

        return back()->with('success', 'Group closed. Members can still read it but cannot post.');
    }

    public function unlock(Request $request, StudyGroup $group): RedirectResponse
    {
        $this->groups->unlock($group, $request->user());

        return back()->with('success', 'Group reopened and its flags cleared.');
    }
}
