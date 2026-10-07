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
            'Admin dashboard counts' => fn () => HelpRequest::selectRaw('status, count(*) as total')
                ->groupBy('status')->pluck('total', 'status')->count(),
            'Subject coverage report' => fn () => DB::table('tutor_subjects')
                ->join('tutor_profiles', 'tutor_profiles.id', '=', 'tutor_subjects.tutor_profile_id')
                ->where('tutor_profiles.verification_status', 'approved')
                ->select('tutor_subjects.curriculum_item_id', DB::raw('count(*) as tutors'))
                ->groupBy('tutor_subjects.curriculum_item_id')->get()->count(),
            'Student dashboard' => function () {
                $student = User::where('role', 'student')->first();

                return $student
                    ? HelpRequest::where('student_id', $student->id)
                        ->whereIn('status', ['open', 'assigned', 'escalated'])
                        ->with('subject:id,name', 'tutor:id,first_name')->get()->count()
                    : 0;
            },
            'Unanswered community board' => fn () => DB::table('community_posts')
                ->whereNull('parent_id')->where('replies_count', 0)->limit(15)->get()->count(),
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
