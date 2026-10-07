<?php

namespace App\Domains\Engagement\Services;

use App\Domains\Content\Models\Resource;
use App\Domains\Tutor\Models\Topic;
use App\Domains\Tutoring\Models\HelpRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * One search box across everything a student can reach.
 *
 * Scoped to their own curriculum and their own requests throughout: search must
 * never become a way to read another learner's conversation.
 */
class SearchService
{
    public const MIN_LENGTH = 2;

    public function search(User $user, string $term, int $perGroup = 5): array
    {
        $term = trim($term);

        if (mb_strlen($term) < self::MIN_LENGTH) {
            return ['term' => $term, 'groups' => [], 'total' => 0];
        }

        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';
        $subjectIds = $user->subjects()->pluck('curriculum_items.id');

        $groups = array_filter([
            $this->lessons($subjectIds, $like, $perGroup),
            $this->resources($subjectIds, $like, $perGroup),
            $this->community($subjectIds, $like, $perGroup),
            $this->ownRequests($user, $like, $perGroup),
        ], fn (?array $group) => $group !== null);

        return [
            'term' => $term,
            'groups' => array_values($groups),
            'total' => collect($groups)->sum(fn ($group) => count($group['items'])),
        ];
    }

    private function lessons($subjectIds, string $like, int $limit): ?array
    {
        $items = Topic::published()
            ->whereIn('curriculum_item_id', $subjectIds)
            ->where(fn ($q) => $q->where('title', 'like', $like)->orWhere('summary', 'like', $like))
            ->with('subject:id,name')
            ->limit($limit)
            ->get()
            ->map(fn (Topic $topic) => [
                'title' => $topic->title,
                'subtitle' => $topic->subject?->name,
                'url' => route('learn.topic', $topic),
            ]);

        return $items->isEmpty() ? null : ['label' => 'Lessons', 'items' => $items->all()];
    }

    private function resources($subjectIds, string $like, int $limit): ?array
    {
        $items = Resource::where('status', 'published')
            ->where(fn ($q) => $q->where('title', 'like', $like)->orWhere('description', 'like', $like))
            ->whereHas('curriculumItems', fn ($q) => $q->whereIn('curriculum_items.id', $subjectIds))
            ->limit($limit)
            ->get()
            ->map(fn (Resource $resource) => [
                'title' => $resource->title,
                'subtitle' => ucfirst(str_replace('_', ' ', $resource->type)),
                'url' => route('resources.show', $resource),
            ]);

        return $items->isEmpty() ? null : ['label' => 'Study material', 'items' => $items->all()];
    }

    private function community($subjectIds, string $like, int $limit): ?array
    {
        $rows = DB::table('community_posts')
            ->whereNull('parent_id')
            ->where('is_removed', false)
            ->whereIn('curriculum_item_id', $subjectIds)
            ->where(fn ($q) => $q->where('title', 'like', $like)->orWhere('body', 'like', $like))
            ->limit($limit)
            ->get(['id', 'title', 'replies_count']);

        if ($rows->isEmpty()) {
            return null;
        }

        return [
            'label' => 'Community',
            'items' => $rows->map(fn ($row) => [
                'title' => $row->title,
                'subtitle' => $row->replies_count === 1 ? '1 answer' : "{$row->replies_count} answers",
                'url' => route('community.show', $row->id),
            ])->all(),
        ];
    }

    /** Only ever the user's own conversations. */
    private function ownRequests(User $user, string $like, int $limit): ?array
    {
        $items = HelpRequest::query()
            ->where(fn ($q) => $user->isTutor()
                ? $q->where('tutor_id', $user->id)
                : $q->where('student_id', $user->id))
            ->where(fn ($q) => $q->where('topic', 'like', $like)->orWhere('description', 'like', $like))
            ->with('subject:id,name')
            ->latest('last_activity_at')
            ->limit($limit)
            ->get()
            ->map(fn (HelpRequest $request) => [
                'title' => $request->topic,
                'subtitle' => ($request->subject?->name ?? '').' · '.$request->status,
                'url' => route('requests.show', $request),
            ]);

        return $items->isEmpty() ? null : ['label' => 'Your questions', 'items' => $items->all()];
    }
}
