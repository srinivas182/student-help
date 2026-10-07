<?php

namespace App\Console\Commands;

use App\Domains\Curriculum\Models\CurriculumItem;
use App\Domains\Tutoring\Models\HelpRequest;
use App\Domains\Tutoring\Services\MatchingService;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Measures the queries that run most often, so "it feels fast" becomes a number
 * we can watch as the data grows.
 */
class BenchmarkQueries extends Command
{
    protected $signature = 'platform:benchmark {--iterations=20}';

    protected $description = 'Time the hot queries and report queries-per-page';

    public function handle(): int
    {
        $iterations = (int) $this->option('iterations');

        $checks = [
            'Curriculum subjects (cached)' => fn () => CurriculumItem::cachedSubjects()->count(),
            'Curriculum subjects (uncached)' => fn () => CurriculumItem::ofType(CurriculumItem::TYPE_SUBJECT)
                ->active()->get(['id', 'name'])->count(),
            'Tutor queue' => fn () => HelpRequest::whereIn('status', ['open', 'escalated'])
                ->with(['subject:id,name', 'student:id,first_name'])->limit(20)->get()->count(),
            'Student request list' => fn () => HelpRequest::where('student_id', User::where('role', 'student')->value('id'))
                ->with('subject:id,name')->latest()->limit(15)->get()->count(),
            'Matching eligible tutors' => function () {
                $request = HelpRequest::first();

                return $request ? app(MatchingService::class)->eligibleTutors($request)->count() : 0;
            },
        ];

        $rows = [];

        foreach ($checks as $label => $callback) {
            $timings = [];
            $queries = 0;

            foreach (range(1, $iterations) as $run) {
                DB::flushQueryLog();
                DB::enableQueryLog();

                $start = microtime(true);
                $callback();
                $timings[] = (microtime(true) - $start) * 1000;

                $queries = count(DB::getQueryLog());
                DB::disableQueryLog();
            }

            sort($timings);

            $rows[] = [
                $label,
                number_format($timings[0], 2).' ms',
                number_format($timings[(int) floor(count($timings) * 0.95)] ?? end($timings), 2).' ms',
                $queries,
            ];
        }

        $this->table(['Query', 'Fastest', 'p95', 'Queries'], $rows);

        $this->newLine();
        $this->info('Run this after any schema change, and against production-sized data before launch.');

        return self::SUCCESS;
    }
}
