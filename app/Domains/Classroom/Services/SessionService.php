<?php

namespace App\Domains\Classroom\Services;

use App\Domains\Classroom\Models\ClassSession;
use App\Domains\Classroom\Models\Classroom;
use App\Models\User;
use App\Notifications\ClassSessionChanged;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SessionService
{
    public const MAX_WEEKS = 26;

    /**
     * Creates a session and, for a weekly class, every repeat up to the end
     * date — so a teacher sets up a term once rather than every week.
     */
    public function schedule(Classroom $classroom, User $teacher, array $data): ClassSession
    {
        $this->assertTeacher($classroom, $teacher);

        $first = ClassSession::create([
            'classroom_id' => $classroom->id,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'mode' => $data['mode'],
            'meeting_url' => $data['mode'] === ClassSession::MODE_ONLINE ? ($data['meeting_url'] ?? null) : null,
            'location' => $data['mode'] === ClassSession::MODE_IN_PERSON ? ($data['location'] ?? null) : null,
            'starts_at' => $data['starts_at'],
            'duration_minutes' => $data['duration_minutes'] ?? 60,
            'repeats' => $data['repeats'] ?? null,
            'repeats_until' => $data['repeats_until'] ?? null,
        ]);

        if (($data['repeats'] ?? null) === 'weekly' && ! empty($data['repeats_until'])) {
            $this->createRepeats($first);
        }

        $this->notifyMembers($classroom, $first, 'New class session');

        audit('class_session.scheduled', $first, [
            'classroom_id' => $classroom->id,
            'repeats' => $first->repeats,
        ]);

        return $first;
    }

    private function createRepeats(ClassSession $first): void
    {
        $date = $first->starts_at->copy()->addWeek();
        $until = Carbon::parse($first->repeats_until)->endOfDay();
        $created = 0;

        while ($date->lte($until) && $created < self::MAX_WEEKS) {
            ClassSession::create([
                'classroom_id' => $first->classroom_id,
                'title' => $first->title,
                'description' => $first->description,
                'mode' => $first->mode,
                'meeting_url' => $first->meeting_url,
                'location' => $first->location,
                'starts_at' => $date->copy(),
                'duration_minutes' => $first->duration_minutes,
                'parent_session_id' => $first->id,
            ]);

            $date->addWeek();
            $created++;
        }
    }

    public function update(ClassSession $session, User $teacher, array $data, bool $wholeSeries = false): void
    {
        $this->assertTeacher($session->classroom, $teacher);

        $changes = [
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'mode' => $data['mode'],
            'meeting_url' => $data['mode'] === ClassSession::MODE_ONLINE ? ($data['meeting_url'] ?? null) : null,
            'location' => $data['mode'] === ClassSession::MODE_IN_PERSON ? ($data['location'] ?? null) : null,
            'duration_minutes' => $data['duration_minutes'] ?? 60,
        ];

        if ($wholeSeries) {
            // Times stay as they are across a series: only the details change,
            // or every learner's diary moves without warning.
            $this->seriesQuery($session)->where('starts_at', '>=', now())->update($changes);
        } else {
            $session->update($changes + ['starts_at' => $data['starts_at']]);
        }

        audit('class_session.updated', $session, ['series' => $wholeSeries]);
    }

    public function cancel(ClassSession $session, User $teacher, string $reason, bool $wholeSeries = false): void
    {
        $this->assertTeacher($session->classroom, $teacher);

        $changes = ['status' => ClassSession::STATUS_CANCELLED, 'cancel_reason' => $reason];

        if ($wholeSeries) {
            $this->seriesQuery($session)->where('starts_at', '>=', now())->update($changes);
        } else {
            $session->update($changes);
        }

        // Cancelling silently is worse than not scheduling at all
        $this->notifyMembers(
            $session->classroom,
            $session,
            $wholeSeries ? 'Class sessions cancelled' : 'Class session cancelled',
            $reason,
        );

        audit('class_session.cancelled', $session, ['series' => $wholeSeries, 'reason' => $reason]);
    }

    public function respond(ClassSession $session, User $student, string $response): void
    {
        abort_unless($session->classroom->students()->whereKey($student->id)->exists(), 403);

        $session->attendees()->syncWithoutDetaching([
            $student->id => ['response' => $response],
        ]);
    }

    public function markAttended(ClassSession $session, User $student): void
    {
        $session->attendees()->syncWithoutDetaching([
            $student->id => ['response' => 'going', 'attended_at' => now()],
        ]);
    }

    /** Everything a student has coming up, across all their classes. */
    public function upcomingFor(User $student, int $limit = 10): Collection
    {
        $classroomIds = $student->classrooms()->pluck('classrooms.id');

        if ($classroomIds->isEmpty()) {
            return collect();
        }

        return ClassSession::whereIn('classroom_id', $classroomIds)
            ->upcoming()
            ->with('classroom:id,name')
            ->limit($limit)
            ->get();
    }

    private function seriesQuery(ClassSession $session)
    {
        $rootId = $session->parent_session_id ?? $session->id;

        return ClassSession::where(fn ($q) => $q->where('id', $rootId)->orWhere('parent_session_id', $rootId));
    }

    private function assertTeacher(Classroom $classroom, User $user): void
    {
        if ($classroom->teacher_id !== $user->id) {
            throw ValidationException::withMessages([
                'session' => 'Only the teacher who owns this class can change its schedule.',
            ]);
        }
    }

    private function notifyMembers(Classroom $classroom, ClassSession $session, string $title, ?string $body = null): void
    {
        $students = $classroom->students()->get();

        if ($students->isEmpty()) {
            return;
        }

        \Illuminate\Support\Facades\Notification::send(
            $students,
            new ClassSessionChanged($session, $title, $body),
        );
    }
}
