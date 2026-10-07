<?php

namespace App\Domains\Engagement\Services;

use App\Domains\Progress\Services\ProgressService;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Streaks, made visible and nudged — but gently.
 *
 * A streak should reward a learner who is showing up, not punish one who is
 * not. There is no shaming language anywhere here, the nudge only fires for a
 * streak worth protecting, and it fires once.
 */
class StreakService
{
    /** Below this, a reminder is more nagging than motivating. */
    public const NUDGE_FROM_DAYS = 3;

    public function __construct(private readonly ProgressService $progress)
    {
    }

    public function summary(User $student): array
    {
        $streak = $this->progress->currentStreak($student);
        $activeToday = $this->activeToday($student);

        return [
            'days' => $streak,
            'activeToday' => $activeToday,
            // Only say something is at risk when there is something to lose
            'atRisk' => $streak >= self::NUDGE_FROM_DAYS && ! $activeToday,
            'best' => $this->longestStreak($student),
            'message' => $this->message($streak, $activeToday),
        ];
    }

    public function activeToday(User $student): bool
    {
        return DB::table('activity_days')
            ->where('user_id', $student->id)
            ->whereDate('day', today())
            ->exists();
    }

    public function longestStreak(User $student): int
    {
        $days = DB::table('activity_days')
            ->where('user_id', $student->id)
            ->orderBy('day')
            ->pluck('day')
            ->map(fn ($day) => \Illuminate\Support\Carbon::parse($day)->startOfDay());

        $best = 0;
        $run = 0;
        $previous = null;

        foreach ($days as $day) {
            $run = ($previous && $previous->copy()->addDay()->isSameDay($day)) ? $run + 1 : 1;
            $best = max($best, $run);
            $previous = $day;
        }

        return $best;
    }

    private function message(int $streak, bool $activeToday): string
    {
        if ($streak === 0) {
            return 'Open a lesson or ask a question to start a streak.';
        }

        if ($activeToday) {
            return $streak === 1
                ? 'You studied today. Come back tomorrow to start a streak.'
                : "{$streak} days in a row. Nicely done.";
        }

        return "You are on {$streak} days. A few minutes today keeps it going.";
    }

    /** Students on a streak worth protecting who have not been in today. */
    public function studentsToNudge(): \Illuminate\Support\Collection
    {
        return User::where('role', User::ROLE_STUDENT)
            ->where('status', 'active')
            ->whereHas('activityDays', fn ($q) => $q->whereDate('day', today()->subDay()))
            ->whereDoesntHave('activityDays', fn ($q) => $q->whereDate('day', today()))
            ->get()
            ->filter(fn (User $student) => $this->progress->currentStreak($student) >= self::NUDGE_FROM_DAYS);
    }
}
