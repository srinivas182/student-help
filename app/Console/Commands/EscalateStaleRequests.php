<?php

namespace App\Console\Commands;

use App\Domains\Tutoring\Models\HelpRequest;
use App\Domains\Tutoring\Services\HelpRequestService;
use Illuminate\Console\Command;

/**
 * REQ-06 and REQ-09, run every 15 minutes by the scheduler:
 * escalate requests nobody accepted, and close resolved requests the student
 * never confirmed.
 */
class EscalateStaleRequests extends Command
{
    protected $signature = 'requests:maintain';

    protected $description = 'Escalate unaccepted help requests and auto-close resolved ones';

    public function handle(HelpRequestService $service): int
    {
        $escalateAfter = (int) setting('request_escalation_hours', 24);
        $closeAfter = (int) setting('auto_close_hours_after_resolved', 72);

        $escalated = 0;

        HelpRequest::where('status', HelpRequest::STATUS_OPEN)
            ->where('created_at', '<=', now()->subHours($escalateAfter))
            ->each(function (HelpRequest $request) use ($service, &$escalated) {
                $service->escalate($request);
                $escalated++;
            });

        $closed = 0;

        HelpRequest::where('status', HelpRequest::STATUS_RESOLVED)
            ->where('resolved_at', '<=', now()->subHours($closeAfter))
            ->each(function (HelpRequest $request) use ($service, &$closed) {
                $service->close($request, automatic: true);
                $closed++;
            });

        $this->info("Escalated {$escalated} request(s), auto-closed {$closed}.");

        return self::SUCCESS;
    }
}
