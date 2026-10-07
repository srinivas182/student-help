<?php

namespace App\Domains\Tutor\Services;

use App\Domains\Tutor\Models\Topic;
use App\Domains\Tutor\Models\TopicQuestion;
use App\Domains\Tutor\Models\TopicVersion;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * The human gate between generated content and students.
 *
 * Nothing published here is "AI said so" — a named subject teacher has read it,
 * corrected it, and put their name against it. Every edit is kept so DX can see
 * how much the AI actually got wrong, which is the number that tells them
 * whether the model or the prompt needs changing.
 */
class ReviewService
{
    public function assertMayReview(User $reviewer, TopicVersion $version): void
    {
        $allowed = $reviewer->mayReview(
            $version->topic?->curriculum_item_id,
            $version->language_id,
        );

        if (! $allowed) {
            throw ValidationException::withMessages([
                'review' => 'This lesson is outside the subjects or languages you review.',
            ]);
        }
    }

    public function updateSegment(TopicVersion $version, int $index, array $data): void
    {
        $lesson = $version->lesson ?? ['segments' => []];
        $segments = $lesson['segments'] ?? [];

        if (! isset($segments[$index])) {
            throw ValidationException::withMessages(['segment' => 'That segment no longer exists.']);
        }

        $segments[$index] = array_merge($segments[$index], array_filter([
            'title' => $data['title'] ?? null,
            'narration' => $data['narration'] ?? null,
            'visual' => $data['visual'] ?? null,
        ], fn ($value) => $value !== null));

        $segments[$index]['edited'] = true;

        $version->update(['lesson' => ['segments' => array_values($segments)]]);

        audit('topic.segment_edited', $version, ['index' => $index]);
    }

    public function removeSegment(TopicVersion $version, int $index): void
    {
        $segments = $version->segments();

        unset($segments[$index]);

        $version->update(['lesson' => ['segments' => array_values($segments)]]);

        audit('topic.segment_removed', $version, ['index' => $index]);
    }

    public function updateQuestion(TopicQuestion $question, array $data): void
    {
        $question->update([
            'question' => $data['question'] ?? $question->question,
            'options' => $data['options'] ?? $question->options,
            'correct_index' => $data['correct_index'] ?? $question->correct_index,
            'explanation' => $data['explanation'] ?? $question->explanation,
            'level' => $data['level'] ?? $question->level,
        ]);

        audit('topic.question_edited', $question->version, ['question_id' => $question->id]);
    }

    public function publish(TopicVersion $version, User $reviewer, ?string $notes = null): void
    {
        $this->assertMayReview($reviewer, $version);

        if ($version->questions()->count() === 0) {
            throw ValidationException::withMessages([
                'review' => 'This lesson has no assessment questions. Add some before publishing.',
            ]);
        }

        if (count($version->segments()) === 0) {
            throw ValidationException::withMessages([
                'review' => 'This lesson has no content segments.',
            ]);
        }

        $version->update([
            'status' => TopicVersion::STATUS_PUBLISHED,
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
            'review_notes' => $notes,
        ]);

        // The topic becomes visible to students once any language is published.
        $version->topic?->update(['is_published' => true]);

        audit('topic.published', $version, [
            'reviewer_id' => $reviewer->id,
            'language_id' => $version->language_id,
        ]);
    }

    public function reject(TopicVersion $version, User $reviewer, string $reason): void
    {
        $this->assertMayReview($reviewer, $version);

        $version->update([
            'status' => TopicVersion::STATUS_REJECTED,
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
            'review_notes' => $reason,
        ]);

        audit('topic.rejected', $version, [
            'reviewer_id' => $reviewer->id,
            'reason' => $reason,
        ]);
    }

    /** Pulling a published lesson back, for when a mistake is spotted later. */
    public function unpublish(TopicVersion $version, User $actor, string $reason): void
    {
        $version->update([
            'status' => TopicVersion::STATUS_REVIEW,
            'review_notes' => $reason,
        ]);

        $topic = $version->topic;

        if ($topic && ! $topic->versions()->where('status', TopicVersion::STATUS_PUBLISHED)->exists()) {
            $topic->update(['is_published' => false]);
        }

        audit('topic.unpublished', $version, ['actor_id' => $actor->id, 'reason' => $reason]);
    }

    /** How much of the AI output a human had to change. */
    public function editStats(TopicVersion $version): array
    {
        $segments = $version->segments();
        $edited = collect($segments)->filter(fn ($segment) => ($segment['edited'] ?? false) === true)->count();

        return [
            'segments' => count($segments),
            'segmentsEdited' => $edited,
            'questions' => $version->questions()->count(),
        ];
    }

    /** @return \Illuminate\Database\Eloquent\Collection<int, TopicVersion> */
    public function queueFor(User $reviewer)
    {
        return TopicVersion::query()
            ->whereIn('status', [TopicVersion::STATUS_REVIEW, TopicVersion::STATUS_REJECTED])
            ->with(['topic.subject:id,name', 'language:id,name,native_name,code'])
            ->withCount('questions')
            ->oldest()
            ->get()
            ->filter(fn (TopicVersion $version) => $reviewer->mayReview(
                $version->topic?->curriculum_item_id,
                $version->language_id,
            ))
            ->values();
    }

    public function topicFor(TopicVersion $version): ?Topic
    {
        return $version->topic;
    }
}
