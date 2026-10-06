<?php

namespace App\Domains\Classroom\Services;

use App\Domains\Classroom\Models\Classroom;
use App\Domains\Classroom\Models\ClassroomMember;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ClassroomService
{
    public function create(User $teacher, array $data): Classroom
    {
        $isSchool = ($data['type'] ?? Classroom::TYPE_PERSONAL) === Classroom::TYPE_SCHOOL;

        $classroom = Classroom::create([
            'teacher_id' => $teacher->id,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'type' => $isSchool ? Classroom::TYPE_SCHOOL : Classroom::TYPE_PERSONAL,
            'institution_id' => $isSchool ? $data['institution_id'] : null,
            // A school link waits for DX approval; the class itself works meanwhile.
            'school_link_status' => $isSchool ? Classroom::LINK_PENDING : null,
            'curriculum_item_id' => $data['curriculum_item_id'] ?? null,
            'join_code' => $this->generateCode(),
            'join_code_active' => true,
            'capacity' => $data['capacity'] ?? null,
            'is_archived' => false,
        ]);

        audit('classroom.created', $classroom, [
            'type' => $classroom->type,
            'institution_id' => $classroom->institution_id,
        ]);

        return $classroom;
    }

    public function join(Classroom $classroom, User $student): ClassroomMember
    {
        if (! $classroom->canAcceptJoins()) {
            throw ValidationException::withMessages([
                'join_code' => $classroom->isFull()
                    ? 'This class is full.'
                    : 'This class is not accepting new students.',
            ]);
        }

        // CON-03: a minor without guardian consent cannot take part in group spaces.
        if (! $student->canParticipate()) {
            throw ValidationException::withMessages([
                'join_code' => 'A parent or guardian needs to approve your account before you can join a class.',
            ]);
        }

        /**
         * A school class is for students at that school, so the link means
         * something. Students who have not set an institution can still join;
         * we only block a mismatch.
         */
        if ($classroom->showsSchoolName()
            && $student->institution_id !== null
            && $student->institution_id !== $classroom->institution_id) {
            throw ValidationException::withMessages([
                'join_code' => 'This class is for students at '.$classroom->institution->name.'.',
            ]);
        }

        $member = ClassroomMember::updateOrCreate(
            ['classroom_id' => $classroom->id, 'user_id' => $student->id],
            ['status' => ClassroomMember::STATUS_ACTIVE, 'joined_at' => now()],
        );

        audit('classroom.joined', $classroom, ['student_id' => $student->id]);

        return $member;
    }

    public function remove(Classroom $classroom, User $student, User $actor): void
    {
        ClassroomMember::where('classroom_id', $classroom->id)
            ->where('user_id', $student->id)
            ->update(['status' => ClassroomMember::STATUS_REMOVED]);

        audit('classroom.member_removed', $classroom, [
            'student_id' => $student->id,
            'actor_id' => $actor->id,
        ]);
    }

    public function leave(Classroom $classroom, User $student): void
    {
        ClassroomMember::where('classroom_id', $classroom->id)
            ->where('user_id', $student->id)
            ->update(['status' => ClassroomMember::STATUS_LEFT]);

        audit('classroom.left', $classroom, ['student_id' => $student->id]);
    }

    /** If a code leaks, the teacher rotates it and old links stop working. */
    public function rotateCode(Classroom $classroom): string
    {
        $classroom->update(['join_code' => $this->generateCode(),
            'join_code_active' => true, 'join_code_active' => true]);

        audit('classroom.code_rotated', $classroom);

        return $classroom->join_code;
    }

    public function approveSchoolLink(Classroom $classroom, User $reviewer): void
    {
        $classroom->update([
            'school_link_status' => Classroom::LINK_APPROVED,
            'school_link_notes' => null,
        ]);

        audit('classroom.school_link_approved', $classroom, ['reviewer_id' => $reviewer->id]);
    }

    public function rejectSchoolLink(Classroom $classroom, User $reviewer, string $reason): void
    {
        $classroom->update([
            'school_link_status' => Classroom::LINK_REJECTED,
            'school_link_notes' => $reason,
            // It keeps working as the teacher's own group, without the school name.
            'type' => Classroom::TYPE_PERSONAL,
        ]);

        audit('classroom.school_link_rejected', $classroom, [
            'reviewer_id' => $reviewer->id,
            'reason' => $reason,
        ]);
    }

    private function generateCode(): string
    {
        do {
            // Unambiguous characters only — these get read out loud in classrooms.
            $code = strtoupper(Str::of(Str::random(10))->replaceMatches('/[^A-HJ-NP-Z2-9]/', '')->limit(6, ''));
        } while (strlen($code) < 6 || Classroom::where('join_code', $code)->exists());

        return $code;
    }
}
