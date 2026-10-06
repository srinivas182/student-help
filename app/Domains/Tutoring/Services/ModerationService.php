<?php

namespace App\Domains\Tutoring\Services;

use App\Domains\Tutoring\Models\Report;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Reports and moderator actions (SRS: MSG-04, MSG-05, MOD-01 – MOD-05).
 */
class ModerationService
{
    public function report(User $reporter, Model $subject, string $reason, ?string $notes = null): Report
    {
        // MOD-04: anything involving a learner under 18 is handled first.
        $involvesMinor = $reporter->isMinor() || $this->subjectInvolvesMinor($subject);

        $report = Report::create([
            'reporter_id' => $reporter->id,
            'reportable_type' => $subject::class,
            'reportable_id' => $subject->getKey(),
            'reason' => $reason,
            'notes' => $notes,
            'severity' => $involvesMinor ? Report::SEVERITY_HIGH : Report::SEVERITY_NORMAL,
            'status' => Report::STATUS_OPEN,
        ]);

        audit('report.created', $report, ['reason' => $reason, 'severity' => $report->severity]);

        return $report;
    }

    public function resolve(Report $report, User $moderator, string $action, string $outcome): void
    {
        $report->update([
            'status' => Report::STATUS_CLOSED,
            'handled_by' => $moderator->id,
            'outcome' => $outcome,
        ]);

        audit('report.resolved', $report, ['action' => $action, 'moderator_id' => $moderator->id]);
    }

    public function suspendUser(User $user, User $moderator, string $reason): void
    {
        $user->update(['status' => 'suspended']);

        audit('user.suspended', $user, ['reason' => $reason, 'moderator_id' => $moderator->id]);
    }

    public function reinstateUser(User $user, User $moderator): void
    {
        $user->update(['status' => 'active']);

        audit('user.reinstated', $user, ['moderator_id' => $moderator->id]);
    }

    private function subjectInvolvesMinor(Model $subject): bool
    {
        if ($subject instanceof User) {
            return $subject->isMinor();
        }

        if (method_exists($subject, 'sender')) {
            return $subject->sender?->isMinor() ?? false;
        }

        return false;
    }
}
