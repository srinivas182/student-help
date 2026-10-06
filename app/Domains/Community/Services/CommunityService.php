<?php

namespace App\Domains\Community\Services;

use App\Domains\Community\Models\CommunityPost;
use App\Domains\Curriculum\Models\CurriculumItem;
use App\Domains\Progress\Services\ProgressService;
use App\Domains\Tutoring\Services\ContentFilter;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CommunityService
{
    public function __construct(
        private readonly ContentFilter $filter,
        private readonly ProgressService $progress,
    ) {
    }

    public function ask(User $author, CurriculumItem $subject, string $title, string $body): CommunityPost
    {
        $this->assertCanPost($author);

        $filtered = $this->filter->mask($body);

        $post = CommunityPost::create([
            'curriculum_item_id' => $subject->id,
            'user_id' => $author->id,
            'title' => $title,
            'body' => $filtered['body'],
            'body_original' => $body,
            'is_flagged' => $filtered['masked'] || $this->filter->shouldFlag($body),
        ]);

        $this->progress->record($author);

        audit('community.question_asked', $post, ['subject_id' => $subject->id]);

        return $post;
    }

    public function reply(User $author, CommunityPost $question, string $body): CommunityPost
    {
        $this->assertCanPost($author);

        if (! $question->isQuestion()) {
            throw ValidationException::withMessages(['body' => 'You can only reply to a question.']);
        }

        $filtered = $this->filter->mask($body);

        $reply = DB::transaction(function () use ($author, $question, $filtered, $body) {
            $reply = CommunityPost::create([
                'curriculum_item_id' => $question->curriculum_item_id,
                'user_id' => $author->id,
                'parent_id' => $question->id,
                'body' => $filtered['body'],
                'body_original' => $body,
                'is_flagged' => $filtered['masked'] || $this->filter->shouldFlag($body),
            ]);

            $question->increment('replies_count');

            return $reply;
        });

        $this->progress->record($author);

        return $reply;
    }

    /** Either the asker or a verified tutor can mark the answer that worked. */
    public function accept(CommunityPost $reply, User $actor): void
    {
        $question = $reply->question;

        if (! $question) {
            throw ValidationException::withMessages(['reply' => 'That is not a reply.']);
        }

        if ($question->user_id !== $actor->id && ! $actor->isVerifiedTutor() && ! $actor->isStaff()) {
            throw ValidationException::withMessages([
                'reply' => 'Only the person who asked, or a verified tutor, can accept an answer.',
            ]);
        }

        $question->replies()->update(['is_accepted' => false]);
        $reply->update(['is_accepted' => true]);

        audit('community.answer_accepted', $reply, ['question_id' => $question->id]);
    }

    public function vote(CommunityPost $post, User $voter): bool
    {
        if ($post->user_id === $voter->id) {
            throw ValidationException::withMessages(['vote' => 'You cannot upvote your own post.']);
        }

        $existing = DB::table('community_votes')
            ->where('community_post_id', $post->id)
            ->where('user_id', $voter->id)
            ->first();

        if ($existing) {
            DB::table('community_votes')->where('id', $existing->id)->delete();
            $post->decrement('votes');

            return false;
        }

        DB::table('community_votes')->insert([
            'community_post_id' => $post->id,
            'user_id' => $voter->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $post->increment('votes');

        return true;
    }

    public function remove(CommunityPost $post, User $moderator): void
    {
        $post->update(['is_removed' => true]);

        audit('community.post_removed', $post, ['moderator_id' => $moderator->id]);
    }

    private function assertCanPost(User $user): void
    {
        if (! $user->canParticipate()) {
            throw ValidationException::withMessages([
                'body' => 'A parent or guardian needs to approve your account before you can post.',
            ]);
        }
    }
}
