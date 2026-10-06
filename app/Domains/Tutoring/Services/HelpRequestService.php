<?php

namespace App\Domains\Tutoring\Services;

use App\Domains\Tutoring\Models\HelpRequest;
use App\Domains\Tutoring\Models\HelpRequestOffer;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The help request lifecycle (SRS: REQ-01 – REQ-11).
 *
 * open → assigned → resolved → closed, with escalated and cancelled branches.
 * Every transition is validated here rather than in controllers, so the rules
 * hold wherever a request is changed from.
 */
class HelpRequestService
{
    public function __construct(private readonly MatchingService $matching)
    {
    }

    /** @param  array<string, mixed>  $data */
    public function create(User $student, array $data): HelpRequest
    {
        $this->assertCanRaise($student);

        return DB::transaction(function () use ($student, $data) {
            $request = HelpRequest::create([
                'student_id' => $student->id,
                'subject_id' => $data['subject_id'],
                'topic' => $data['topic'],
                'description' => $data['description'],
                'status' => HelpRequest::STATUS_OPEN,
                'last_activity_at' => now(),
            ]);

            $notified = $this->matching->offer($request);

            audit('help_request.created', $request, [
                'subject_id' => $request->subject_id,
                'tutors_notified' => $notified,
            ]);

            return $request;
        });
    }

    /** CON-03 and REQ-07: consent gate and the open-request cap. */
    public function assertCanRaise(User $student): void
    {
        if (! $student->canParticipate()) {
            throw ValidationException::withMessages([
                'consent' => 'A parent or guardian needs to approve your account before you can ask for help.',
            ]);
        }

        $open = HelpRequest::where('student_id', $student->id)
            ->whereIn('status', [HelpRequest::STATUS_OPEN, HelpRequest::STATUS_ESCALATED, HelpRequest::STATUS_ASSIGNED])
            ->count();

        $max = (int) setting('max_open_requests_per_student', 3);

        if ($open >= $max) {
            throw ValidationException::withMessages([
                'limit' => "You already have {$max} open requests. Close one before asking for more help.",
            ]);
        }

        $quota = $student->free_for_life ? null : $student->monthly_request_quota;

        if ($quota !== null) {
            $used = HelpRequest::where('student_id', $student->id)
                ->where('created_at', '>=', now()->startOfMonth())
                ->count();

            if ($used >= $quota) {
                throw ValidationException::withMessages([
                    'quota' => "You have used all {$quota} requests for this month.",
                ]);
            }
        }
    }

    /** REQ-04: first eligible tutor to accept takes the request. */
    public function accept(HelpRequest $request, User $tutor): HelpRequest
    {
        return DB::transaction(function () use ($request, $tutor) {
            $fresh = HelpRequest::lockForUpdate()->findOrFail($request->id);

            if ($fresh->tutor_id !== null) {
                throw ValidationException::withMessages([
                    'request' => 'Another tutor has already taken this request.',
                ]);
            }

            if (! $this->isOfferedTo($fresh, $tutor)) {
                throw ValidationException::withMessages([
                    'request' => 'This request was not offered to you.',
                ]);
            }

            $fresh->update([
                'tutor_id' => $tutor->id,
                'status' => HelpRequest::STATUS_ASSIGNED,
                'assigned_at' => now(),
                'last_activity_at' => now(),
            ]);

            $fresh->offers()->where('tutor_id', $tutor->id)
                ->update(['status' => HelpRequestOffer::STATUS_ACCEPTED]);

            audit('help_request.accepted', $fresh, ['tutor_id' => $tutor->id]);

            return $fresh;
        });
    }

    /** REQ-05: a decline is permanent for that tutor. */
    public function decline(HelpRequest $request, User $tutor, ?string $reason = null): void
    {
        $request->offers()->where('tutor_id', $tutor->id)->update([
            'status' => HelpRequestOffer::STATUS_DECLINED,
            'decline_reason' => $reason,
        ]);

        audit('help_request.declined', $request, ['tutor_id' => $tutor->id, 'reason' => $reason]);
    }

    /** REQ-06: nobody accepted in time, so an administrator picks it up. */
    public function escalate(HelpRequest $request): void
    {
        if ($request->status !== HelpRequest::STATUS_OPEN) {
            return;
        }

        $request->update([
            'status' => HelpRequest::STATUS_ESCALATED,
            'escalated_at' => now(),
        ]);

        audit('help_request.escalated', $request);
    }

    /** REQ-10: an administrator may assign or move a request at any time. */
    public function assign(HelpRequest $request, User $tutor, User $actor): void
    {
        $previous = $request->tutor_id;

        HelpRequestOffer::firstOrCreate(
            ['help_request_id' => $request->id, 'tutor_id' => $tutor->id],
            ['status' => HelpRequestOffer::STATUS_ACCEPTED],
        );

        $request->update([
            'tutor_id' => $tutor->id,
            'status' => HelpRequest::STATUS_ASSIGNED,
            'assigned_at' => now(),
            'last_activity_at' => now(),
        ]);

        audit('help_request.reassigned', $request, [
            'from_tutor_id' => $previous,
            'to_tutor_id' => $tutor->id,
            'by' => $actor->id,
        ]);
    }

    /** REQ-09: the tutor marks it answered; the student then confirms. */
    public function resolve(HelpRequest $request, User $tutor): void
    {
        // Re-read: the caller may hold an instance from before assignment.
        $request->refresh();

        if ($request->tutor_id !== $tutor->id) {
            throw ValidationException::withMessages(['request' => 'This request is not assigned to you.']);
        }

        $request->update([
            'status' => HelpRequest::STATUS_RESOLVED,
            'resolved_at' => now(),
            'last_activity_at' => now(),
        ]);

        audit('help_request.resolved', $request, ['tutor_id' => $tutor->id]);
    }

    public function close(HelpRequest $request, bool $automatic = false): void
    {
        $request->update([
            'status' => HelpRequest::STATUS_CLOSED,
            'closed_at' => now(),
            'last_activity_at' => now(),
        ]);

        if ($request->tutor_id) {
            $request->tutor->tutorProfile?->increment('resolved_count');
        }

        audit('help_request.closed', $request, ['automatic' => $automatic]);
    }

    /** REQ-09: the student can send it back if the answer did not help. */
    public function reopen(HelpRequest $request): void
    {
        $request->update([
            'status' => HelpRequest::STATUS_ASSIGNED,
            'resolved_at' => null,
            'last_activity_at' => now(),
        ]);

        audit('help_request.reopened', $request);
    }

    public function cancel(HelpRequest $request, User $actor): void
    {
        $request->update([
            'status' => HelpRequest::STATUS_CANCELLED,
            'last_activity_at' => now(),
        ]);

        audit('help_request.cancelled', $request, ['by' => $actor->id]);
    }

    private function isOfferedTo(HelpRequest $request, User $tutor): bool
    {
        return $request->offers()
            ->where('tutor_id', $tutor->id)
            ->where('status', HelpRequestOffer::STATUS_OFFERED)
            ->exists();
    }
}
