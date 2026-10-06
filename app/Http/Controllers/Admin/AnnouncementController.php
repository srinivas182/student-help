<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Content\Models\Announcement;
use App\Domains\Curriculum\Models\CurriculumItem;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\AnnouncementPublished;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AnnouncementController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Announcements', [
            'announcements' => Announcement::with('author:id,first_name,last_name')
                ->latest()
                ->paginate(15)
                ->through(fn (Announcement $a) => [
                    'id' => $a->id,
                    'title' => $a->title,
                    'body' => $a->body,
                    'priority' => $a->priority,
                    'author' => $a->author?->name,
                    'targeting' => $a->targeting,
                    'publishAt' => $a->publish_at?->toDayDateTimeString(),
                    'expiresAt' => $a->expires_at?->toDayDateTimeString(),
                    'isScheduled' => $a->isScheduled(),
                    'createdAt' => $a->created_at?->diffForHumans(),
                ]),
            'levels' => CurriculumItem::active()
                ->whereIn('type', [CurriculumItem::TYPE_LEVEL, CurriculumItem::TYPE_FACULTY, CurriculumItem::TYPE_PATHWAY])
                ->with('parent')
                ->orderBy('position')
                ->get()
                ->map(fn (CurriculumItem $item) => [
                    'id' => $item->id,
                    'name' => $item->name,
                    'type' => $item->type,
                    'context' => collect($item->ancestors())->pluck('name')->implode(' · '),
                ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'body' => ['required', 'string', 'max:5000'],
            'priority' => ['required', Rule::in([Announcement::PRIORITY_NORMAL, Announcement::PRIORITY_IMPORTANT])],
            'roles' => ['array'],
            'roles.*' => [Rule::in([User::ROLE_STUDENT, User::ROLE_TUTOR])],
            'curriculum_item_ids' => ['array'],
            'curriculum_item_ids.*' => ['integer', 'exists:curriculum_items,id'],
            'publish_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after:publish_at'],
        ]);

        $announcement = Announcement::create([
            'author_id' => $request->user()->id,
            'title' => $validated['title'],
            'body' => $validated['body'],
            'priority' => $validated['priority'],
            'targeting' => [
                'roles' => $validated['roles'] ?? [],
                'curriculum_item_ids' => $validated['curriculum_item_ids'] ?? [],
            ],
            'publish_at' => $validated['publish_at'] ?? now(),
            'expires_at' => $validated['expires_at'] ?? null,
        ]);

        // ANN-04: important announcements are pushed, normal ones wait to be read.
        if ($announcement->priority === Announcement::PRIORITY_IMPORTANT && ! $announcement->isScheduled()) {
            $this->notifyTargets($announcement);
        }

        audit('announcement.created', $announcement, ['priority' => $announcement->priority]);

        return back()->with('success', $announcement->isScheduled()
            ? 'Scheduled. It will appear automatically.'
            : 'Published to everyone it targets.');
    }

    public function destroy(Announcement $announcement): RedirectResponse
    {
        audit('announcement.deleted', $announcement);
        $announcement->delete();

        return back()->with('success', 'Announcement removed.');
    }

    private function notifyTargets(Announcement $announcement): void
    {
        User::query()
            ->whereIn('role', $announcement->targeting['roles'] ?: [User::ROLE_STUDENT, User::ROLE_TUTOR])
            ->where('status', 'active')
            ->chunkById(200, function ($users) use ($announcement) {
                foreach ($users as $user) {
                    if ($announcement->appliesTo($user)) {
                        $user->notify(new AnnouncementPublished($announcement));
                    }
                }
            });
    }
}
