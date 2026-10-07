<?php

namespace App\Domains\Engagement\Services;

use App\Domains\Assessment\Services\AssessmentService;
use App\Domains\Tutor\Models\Topic;
use App\Domains\Tutor\Models\TopicProgress;
use App\Domains\Tutoring\Models\HelpRequest;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Answers one question on the dashboard: what should this student do now?
 *
 * Ordered by what a learner would actually want first — an answer waiting for
 * them, then revision that is due, then a lesson they left half-finished.
 * Returns at most three, because a list of ten next actions is no next action.
 */
class NextActionService
{
    public function __construct(private readonly AssessmentService $assessments)
    {
    }

    public function forStudent(User $student): Collection
    {
        return collect([
            ...$this->answeredRequests($student),
            ...$this->dueReviews($student),
            ...$this->unfinishedLesson($student),
            ...$this->waitingRequests($student),
            ...$this->firstSteps($student),
        ])->take(3)->values();
    }

    /** Someone answered. Nothing else matters more than telling them. */
    private function answeredRequests(User $student): array
    {
        $resolved = HelpRequest::where('student_id', $student->id)
            ->where('status', HelpRequest::STATUS_RESOLVED)
            ->latest('last_activity_at')
            ->first();

        if (! $resolved) {
            return [];
        }

        return [[
            'kind' => 'answered',
            'tone' => 'positive',
            'title' => 'Your question was answered',
            'body' => $resolved->topic,
            'action' => 'Read the answer',
            'url' => route('requests.show', $resolved),
        ]];
    }

    private function dueReviews(User $student): array
    {
        $due = $this->assessments->dueForReview($student);

        if ($due->isEmpty()) {
            return [];
        }

        return [[
            'kind' => 'review',
            'tone' => 'default',
            'title' => $due->count() === 1
                ? '1 topic is due for review'
                : "{$due->count()} topics are due for review",
            'body' => $due->take(2)->map(fn ($mastery) => $mastery->topic?->title)->filter()->implode(' · '),
            'action' => 'Revise now',
            'url' => route('learn.reviews'),
        ]];
    }

    /** Half-finished lessons are the easiest win available. */
    private function unfinishedLesson(User $student): array
    {
        $progress = TopicProgress::where('user_id', $student->id)
            ->whereNull('completed_at')
            ->with('topic')
            ->latest('updated_at')
            ->first();

        if (! $progress || ! $progress->topic) {
            return [];
        }

        return [[
            'kind' => 'continue',
            'tone' => 'default',
            'title' => 'Carry on where you left off',
            'body' => $progress->topic->title,
            'action' => 'Continue',
            'url' => route('learn.topic', $progress->topic),
        ]];
    }

    private function waitingRequests(User $student): array
    {
        $waiting = HelpRequest::where('student_id', $student->id)
            ->whereIn('status', [HelpRequest::STATUS_OPEN, HelpRequest::STATUS_ESCALATED])
            ->oldest()
            ->first();

        if (! $waiting) {
            return [];
        }

        return [[
            'kind' => 'waiting',
            'tone' => 'muted',
            'title' => 'A tutor is looking at your question',
            'body' => $waiting->topic,
            'action' => 'See it',
            'url' => route('requests.show', $waiting),
        ]];
    }

    /** A new student with nothing started needs somewhere obvious to go. */
    private function firstSteps(User $student): array
    {
        $subjectIds = $student->subjects()->pluck('curriculum_items.id');

        $topic = Topic::published()
            ->whereIn('curriculum_item_id', $subjectIds)
            ->with('subject:id,name')
            ->first();

        $steps = [];

        if ($topic) {
            $steps[] = [
                'kind' => 'start',
                'tone' => 'positive',
                'title' => 'Start your first lesson',
                'body' => $topic->title.' · '.($topic->subject?->name ?? ''),
                'action' => 'Open it',
                'url' => route('learn.topic', $topic),
            ];
        }

        $steps[] = [
            'kind' => 'ask',
            'tone' => 'default',
            'title' => 'Stuck on something?',
            'body' => 'Ask a verified tutor. It is free and usually answered within a few hours.',
            'action' => 'Ask a question',
            'url' => route('requests.create'),
        ];

        return $steps;
    }
}
