<?php

namespace App\Domains\Engagement\Services;

use App\Domains\Assessment\Models\TopicMastery;
use App\Domains\Tutor\Models\Topic;
use App\Domains\Tutor\Models\TopicProgress;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * How far through each subject a student is.
 *
 * Counting only published topics keeps the denominator honest: a learner should
 * never see "6 of 200" because the library is mostly unwritten.
 */
class SubjectProgressService
{
    public function forStudent(User $student): Collection
    {
        $subjects = $student->subjects()->get(['curriculum_items.id', 'name']);

        if ($subjects->isEmpty()) {
            return collect();
        }

        $topics = Topic::published()
            ->whereIn('curriculum_item_id', $subjects->pluck('id'))
            ->get(['id', 'curriculum_item_id']);

        $completedIds = TopicProgress::where('user_id', $student->id)
            ->whereNotNull('completed_at')
            ->pluck('topic_id');

        $masteredIds = TopicMastery::where('user_id', $student->id)
            ->where('highest_level_passed', '!=', null)
            ->pluck('topic_id');

        return $subjects->map(function ($subject) use ($topics, $completedIds, $masteredIds) {
            $subjectTopics = $topics->where('curriculum_item_id', $subject->id);
            $total = $subjectTopics->count();

            $completed = $subjectTopics->whereIn('id', $completedIds)->count();
            $mastered = $subjectTopics->whereIn('id', $masteredIds)->count();

            return [
                'id' => $subject->id,
                'name' => $subject->name,
                'total' => $total,
                'completed' => $completed,
                'mastered' => $mastered,
                'percent' => $total > 0 ? (int) round($completed / $total * 100) : 0,
                // No lessons yet is different from no progress, and should read differently
                'hasContent' => $total > 0,
            ];
        })->sortByDesc('hasContent')->values();
    }
}
