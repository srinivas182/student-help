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

    /**
     * A recording, as a link or an uploaded file.
     *
     * Link is the default and the recommendation: an hour of video is several
     * hundred megabytes, which fills the server and costs a learner on mobile
     * data more than the lesson is worth. Cloud hosts stream it adaptively.
     */
    public function attachRecording(
        ClassSession $session,
        User $teacher,
        ?string $url,
        ?\Illuminate\Http\UploadedFile $file,
        ?string $note = null,
    ): void {
        $this->assertTeacher($session->classroom, $teacher);

        if (blank($url) && $file === null) {
            throw ValidationException::withMessages([
                'recording' => 'Paste a link to the recording, or upload the file.',
            ]);
        }

        $path = $session->recording_path;

        if ($file !== null) {
            // Replace rather than accumulate: a re-upload should not leave the
            // previous file sitting on disk forever
            if ($path) {
                \Illuminate\Support\Facades\Storage::disk('local')->delete($path);
            }

            $path = $file->store("recordings/{$session->classroom_id}/{$session->id}", 'local');
        }

        $session->update([
            'recording_url' => $file !== null ? null : $url,
            'recording_path' => $file !== null ? $path : null,
            'recording_note' => $note,
            'recording_added_at' => now(),
        ]);

        $this->notifyMembers(
            $session->classroom,
            $session,
            'Recording available',
            $note ?: 'The recording of this session is ready to watch.',
        );

        audit('class_session.recording_added', $session, [
            'kind' => $file !== null ? 'upload' : 'link',
        ]);
    }

    public function removeRecording(ClassSession $session, User $teacher): void
    {
        $this->assertTeacher($session->classroom, $teacher);

        if ($session->recording_path) {
            \Illuminate\Support\Facades\Storage::disk('local')->delete($session->recording_path);
        }

        $session->update([
            'recording_url' => null,
            'recording_path' => null,
            'recording_note' => null,
            'recording_added_at' => null,
        ]);

        audit('class_session.recording_removed', $session);
    }

    /** Slides, notes or a worksheet, attached to the session they belong to. */
    public function addMaterial(
        ClassSession $session,
        User $teacher,
        string $title,
        ?string $url,
        ?\Illuminate\Http\UploadedFile $file,
    ): \App\Domains\Classroom\Models\ClassSessionMaterial {
        $this->assertTeacher($session->classroom, $teacher);

        $path = null;
        $mime = null;
        $size = 0;

        if ($file !== null) {
            $path = $file->store("session-materials/{$session->id}", 'local');
            $mime = $file->getClientMimeType();
            $size = $file->getSize() ?: 0;
        }

        return $session->materials()->create([
            'uploaded_by' => $teacher->id,
            'title' => $title,
            'path' => $path,
            'external_url' => $file === null ? $url : null,
            'mime_type' => $mime,
            'size' => $size,
        ]);
    }

    /**
     * Who turned up, who said they would and did not, and who watched it
     * afterwards. The last column is the one that makes a recording worth
     * uploading.
     */
    public function register(ClassSession $session, User $teacher): \Illuminate\Support\Collection
    {
        $this->assertTeacher($session->classroom, $teacher);

        $responses = $session->attendees()->get()->keyBy('id');

        return $session->classroom->students()->get()->map(function (User $student) use ($responses) {
            $record = $responses->get($student->id);
            $pivot = $record?->pivot;

            return [
                'id' => $student->id,
                'name' => $student->name,
                'response' => $pivot?->response,
                'attended' => $pivot?->attended_at !== null,
                'attendedAt' => $pivot?->attended_at
                    ? Carbon::parse($pivot->attended_at)->format('H:i')
                    : null,
                'watchedRecording' => (bool) ($pivot?->watched_recording ?? false),
            ];
        })->sortBy('name')->values();
    }

    public function markWatchedRecording(ClassSession $session, User $student): void
    {
        $session->attendees()->syncWithoutDetaching([
            $student->id => ['watched_recording' => true],
        ]);
    }

    /**
     * Reminds people shortly before a session starts.
     *
     * Being told three weeks ahead that something is scheduled is not a
     * reminder. This is what actually gets a learner to turn up.
     */
    public function sendDueReminders(int $minutes = 60): int
    {
        $sent = 0;

        foreach (ClassSession::needingReminder($minutes)->with('classroom')->get() as $session) {
            if (! $session->classroom) {
                continue;
            }

            $students = $session->classroom->students()->get();

            if ($students->isNotEmpty()) {
                \Illuminate\Support\Facades\Notification::send(
                    $students,
                    new ClassSessionChanged(
                        $session,
                        'Starting soon: '.$session->title,
                        $session->mode === ClassSession::MODE_ONLINE
                            ? 'The join button appears on the class page shortly before it starts.'
                            : null,
                    ),
                );
            }

            $session->update(['reminded_at' => now()]);
            $sent++;
        }

        return $sent;
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
