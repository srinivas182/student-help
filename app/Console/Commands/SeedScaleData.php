<?php

namespace App\Console\Commands;

use App\Domains\Curriculum\Models\CurriculumItem;
use App\Domains\Tutoring\Models\HelpRequest;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Generates production-sized data so performance can be measured against
 * realistic volumes rather than a demo dataset. Bulk inserts only — this is
 * about producing rows, not exercising the domain services.
 */
class SeedScaleData extends Command
{
    protected $signature = 'platform:seed-scale
        {--students=20000}
        {--tutors=800}
        {--requests=60000}';

    protected $description = 'Generate production-scale data for performance measurement';

    public function handle(): int
    {
        $students = (int) $this->option('students');
        $tutors = (int) $this->option('tutors');
        $requests = (int) $this->option('requests');

        $subjectIds = CurriculumItem::ofType(CurriculumItem::TYPE_SUBJECT)->pluck('id');

        if ($subjectIds->isEmpty()) {
            $this->error('Seed the curriculum first.');

            return self::FAILURE;
        }

        $password = Hash::make('password');
        $now = now();

        $this->info("Creating {$students} students and {$tutors} tutors…");

        $studentIds = $this->insertUsers($students, 'student', $password, $now);
        $tutorIds = $this->insertUsers($tutors, 'tutor', $password, $now);

        $this->info('Attaching subjects…');
        $this->attachSubjects($studentIds, $subjectIds, 'subject');

        $this->info('Creating tutor profiles…');
        $profileIds = $this->insertTutorProfiles($tutorIds, $now);
        $this->attachTutorSubjects($profileIds, $subjectIds);

        $this->info("Creating {$requests} help requests…");
        $this->insertRequests($requests, $studentIds, $tutorIds, $subjectIds, $now);

        $this->newLine();
        $this->table(['Table', 'Rows'], [
            ['users', DB::table('users')->count()],
            ['academic_selections', DB::table('academic_selections')->count()],
            ['tutor_profiles', DB::table('tutor_profiles')->count()],
            ['tutor_subjects', DB::table('tutor_subjects')->count()],
            ['help_requests', DB::table('help_requests')->count()],
        ]);

        return self::SUCCESS;
    }

    private function insertUsers(int $count, string $role, string $password, $now): array
    {
        $ids = [];
        $offset = DB::table('users')->max('id') ?? 0;

        foreach (array_chunk(range(1, $count), 1000) as $chunk) {
            $rows = [];

            foreach ($chunk as $index) {
                $n = $offset + $index;

                $rows[] = [
                    'first_name' => 'Scale',
                    'last_name' => "{$role}{$n}",
                    'name' => "Scale {$role}{$n}",
                    'email' => "scale-{$role}-{$n}@example.test",
                    'password' => $password,
                    'role' => $role,
                    'status' => 'active',
                    'email_verified_at' => $now,
                    'date_of_birth' => $role === 'student' ? '2008-05-14' : '1992-03-02',
                    'onboarding_completed_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            DB::table('users')->insert($rows);
        }

        return DB::table('users')->where('email', 'like', "scale-{$role}-%")->pluck('id')->all();
    }

    private function attachSubjects(array $userIds, $subjectIds, string $role): void
    {
        foreach (array_chunk($userIds, 500) as $chunk) {
            $rows = [];

            foreach ($chunk as $userId) {
                foreach ($subjectIds->random(min(6, $subjectIds->count())) as $subjectId) {
                    $rows[] = [
                        'user_id' => $userId,
                        'curriculum_item_id' => $subjectId,
                        'role' => $role,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }

            DB::table('academic_selections')->insertOrIgnore($rows);
        }
    }

    private function insertTutorProfiles(array $tutorIds, $now): array
    {
        foreach (array_chunk($tutorIds, 500) as $chunk) {
            $rows = [];

            foreach ($chunk as $userId) {
                $rows[] = [
                    'user_id' => $userId,
                    'verification_status' => 'approved',
                    'is_available' => true,
                    'resolved_count' => random_int(0, 80),
                    'average_rating' => random_int(35, 50) / 10,
                    'ratings_count' => random_int(0, 40),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            DB::table('tutor_profiles')->insert($rows);
        }

        return DB::table('tutor_profiles')->whereIn('user_id', $tutorIds)->pluck('id')->all();
    }

    private function attachTutorSubjects(array $profileIds, $subjectIds): void
    {
        foreach (array_chunk($profileIds, 500) as $chunk) {
            $rows = [];

            foreach ($chunk as $profileId) {
                foreach ($subjectIds->random(min(4, $subjectIds->count())) as $subjectId) {
                    $rows[] = ['tutor_profile_id' => $profileId, 'curriculum_item_id' => $subjectId];
                }
            }

            DB::table('tutor_subjects')->insertOrIgnore($rows);
        }
    }

    private function insertRequests(int $count, array $studentIds, array $tutorIds, $subjectIds, $now): void
    {
        $statuses = ['open', 'assigned', 'resolved', 'closed', 'escalated'];

        foreach (array_chunk(range(1, $count), 2000) as $chunk) {
            $rows = [];

            foreach ($chunk as $index) {
                $status = $statuses[array_rand($statuses)];
                $assigned = in_array($status, ['assigned', 'resolved', 'closed'], true);
                $created = $now->copy()->subMinutes(random_int(0, 60 * 24 * 120));

                $rows[] = [
                    'student_id' => $studentIds[array_rand($studentIds)],
                    'tutor_id' => $assigned ? $tutorIds[array_rand($tutorIds)] : null,
                    'subject_id' => $subjectIds->random(),
                    'topic' => 'Scale question '.$index,
                    'description' => 'Generated for performance measurement. '.Str::random(80),
                    'status' => $status,
                    'assigned_at' => $assigned ? $created->copy()->addHours(2) : null,
                    'resolved_at' => in_array($status, ['resolved', 'closed'], true) ? $created->copy()->addHours(6) : null,
                    'escalated_at' => $status === 'escalated' ? $created->copy()->addDay() : null,
                    'last_activity_at' => $created,
                    'created_at' => $created,
                    'updated_at' => $created,
                ];
            }

            DB::table('help_requests')->insert($rows);
        }
    }
}
