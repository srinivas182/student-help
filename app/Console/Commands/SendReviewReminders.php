<?php

namespace App\Console\Commands;

use App\Domains\Assessment\Models\TopicMastery;
use App\Notifications\ReviewDue;
use Illuminate\Console\Command;

/**
 * Spaced review only works if somebody comes back. One nudge a day, batched per
 * student so nobody gets five emails for five topics.
 */
class SendReviewReminders extends Command
{
    protected $signature = 'reviews:remind';

    protected $description = 'Tell students which topics are due for review today';

    public function handle(): int
    {
        $due = TopicMastery::dueForReview()
            ->with(['user', 'topic:id,title'])
            ->get()
            ->groupBy('user_id');

        $sent = 0;

        foreach ($due as $records) {
            $user = $records->first()->user;

            if (! $user || $user->status !== 'active') {
                continue;
            }

            $user->notify(new ReviewDue(
                $records->pluck('topic.title')->filter()->take(5)->all(),
                $records->count(),
            ));

            $sent++;
        }

        $this->info("Review reminders sent: {$sent}");

        return self::SUCCESS;
    }
}
