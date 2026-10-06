<?php

namespace App\Domains\StudyGroup\Services;

use App\Domains\StudyGroup\Models\StudyGroup;
use App\Domains\StudyGroup\Models\StudyGroupMember;
use App\Domains\StudyGroup\Models\StudyGroupMessage;
use App\Domains\Tutoring\Services\ContentFilter;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StudyGroupService
{
    public function __construct(private readonly ContentFilter $filter)
    {
    }

    public function create(User $owner, array $data): StudyGroup
    {
        $this->assertCanParticipate($owner);

        $group = DB::transaction(function () use ($owner, $data) {
            $group = StudyGroup::create([
                'owner_id' => $owner->id,
                'curriculum_item_id' => $data['curriculum_item_id'] ?? null,
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'join_code' => $this->generateCode(),
                'is_open' => true,
                'capacity' => min((int) ($data['capacity'] ?? 30), 30),
            ]);

            StudyGroupMember::create([
                'study_group_id' => $group->id,
                'user_id' => $owner->id,
                'role' => StudyGroupMember::ROLE_OWNER,
                'status' => StudyGroupMember::STATUS_ACTIVE,
                'joined_at' => now(),
            ]);

            return $group;
        });

        audit('study_group.created', $group, ['owner_id' => $owner->id]);

        return $group;
    }

    public function join(StudyGroup $group, User $student): void
    {
        $this->assertCanParticipate($student);

        if (! $group->canAcceptJoins()) {
            throw ValidationException::withMessages([
                'join_code' => $group->is_locked
                    ? 'This group has been closed by a moderator.'
                    : ($group->isFull() ? 'This group is full.' : 'This group is not accepting new members.'),
            ]);
        }

        StudyGroupMember::updateOrCreate(
            ['study_group_id' => $group->id, 'user_id' => $student->id],
            [
                'role' => StudyGroupMember::ROLE_MEMBER,
                'status' => StudyGroupMember::STATUS_ACTIVE,
                'joined_at' => now(),
            ],
        );

        audit('study_group.joined', $group, ['user_id' => $student->id]);
    }

    public function post(StudyGroup $group, User $author, string $body): StudyGroupMessage
    {
        if ($group->is_locked) {
            throw ValidationException::withMessages(['body' => 'This group is closed.']);
        }

        if (! $group->hasMember($author)) {
            throw ValidationException::withMessages(['body' => 'You are not a member of this group.']);
        }

        $this->assertCanParticipate($author, 'post in a study group');

        // Same masking and flagging as private messages — minors talking to minors.
        $filtered = $this->filter->mask($body);

        $message = StudyGroupMessage::create([
            'study_group_id' => $group->id,
            'user_id' => $author->id,
            'body' => $filtered['body'],
            'body_original' => $body,
            'is_flagged' => $filtered['masked'] || $this->filter->shouldFlag($body),
        ]);

        if ($message->is_flagged) {
            $this->registerConcern($group);
            audit('study_group.message_flagged', $group, ['message_id' => $message->id]);
        }

        return $message;
    }

    public function leave(StudyGroup $group, User $user): void
    {
        $membership = $group->memberships()->where('user_id', $user->id)->first();

        if (! $membership) {
            return;
        }

        // If the owner leaves, hand the group to the longest-standing member.
        if ($membership->role === StudyGroupMember::ROLE_OWNER) {
            $successor = $group->memberships()
                ->where('user_id', '!=', $user->id)
                ->where('status', StudyGroupMember::STATUS_ACTIVE)
                ->oldest('joined_at')
                ->first();

            if ($successor) {
                $successor->update(['role' => StudyGroupMember::ROLE_OWNER]);
                $group->update(['owner_id' => $successor->user_id]);
            } else {
                $group->update(['is_open' => false]);
            }
        }

        $membership->update(['status' => StudyGroupMember::STATUS_LEFT]);

        audit('study_group.left', $group, ['user_id' => $user->id]);
    }

    public function remove(StudyGroup $group, User $target, User $actor): void
    {
        $group->memberships()->where('user_id', $target->id)
            ->update(['status' => StudyGroupMember::STATUS_REMOVED]);

        audit('study_group.member_removed', $group, [
            'user_id' => $target->id,
            'actor_id' => $actor->id,
        ]);
    }

    /**
     * Accumulated concerns flag the group for a moderator automatically, so a
     * problem surfaces without anyone having to notice it manually.
     */
    public function registerConcern(StudyGroup $group): void
    {
        $group->increment('report_count');

        if ($group->fresh()->report_count >= StudyGroup::AUTO_FLAG_THRESHOLD && ! $group->is_flagged) {
            $group->update(['is_flagged' => true]);

            audit('study_group.auto_flagged', $group, ['reports' => $group->report_count]);
        }
    }

    public function lock(StudyGroup $group, User $moderator, string $reason): void
    {
        $group->update(['is_locked' => true, 'is_open' => false, 'locked_reason' => $reason]);

        audit('study_group.locked', $group, ['moderator_id' => $moderator->id, 'reason' => $reason]);
    }

    public function unlock(StudyGroup $group, User $moderator): void
    {
        $group->update([
            'is_locked' => false,
            'is_open' => true,
            'is_flagged' => false,
            'report_count' => 0,
            'locked_reason' => null,
        ]);

        audit('study_group.unlocked', $group, ['moderator_id' => $moderator->id]);
    }

    private function assertCanParticipate(User $user, string $action = 'join a study group'): void
    {
        if (! $user->canParticipate()) {
            throw ValidationException::withMessages([
                'join_code' => "A parent or guardian needs to approve your account before you can {$action}.",
                'body' => "A parent or guardian needs to approve your account before you can {$action}.",
            ]);
        }
    }

    private function generateCode(): string
    {
        do {
            $code = strtoupper(Str::of(Str::random(10))->replaceMatches('/[^A-HJ-NP-Z2-9]/', '')->limit(6, ''));
        } while (strlen($code) < 6 || StudyGroup::where('join_code', $code)->exists());

        return $code;
    }
}
