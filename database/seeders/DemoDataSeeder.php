<?php

namespace Database\Seeders;

use App\Domains\Curriculum\Models\CurriculumItem;
use App\Domains\Curriculum\Models\Institution;
use App\Domains\Identity\Models\GuardianConsent;
use App\Domains\Tutoring\Models\HelpRequest;
use App\Domains\Tutoring\Models\Message;
use App\Domains\Tutoring\Models\TutorProfile;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Demo accounts and sample activity so the platform can be shown to the client
 * with realistic content. Not loaded in production.
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedInstitutions();

        $admin = $this->user('Thabo', 'Mkhize', 'admin@dxstudenthelp.co.za', User::ROLE_ADMIN, '1985-04-12');
        $this->user('Nomsa', 'Dlamini', 'moderator@dxstudenthelp.co.za', User::ROLE_MODERATOR, '1990-08-03');

        // Grade 11 learner, under 18, with approved guardian consent
        $learner = $this->user('Sipho', 'Ndlovu', 'student@dxstudenthelp.co.za', User::ROLE_STUDENT, now()->subYears(16)->format('Y-m-d'));
        $this->approveConsent($learner);
        $grade11 = CurriculumItem::where('type', CurriculumItem::TYPE_LEVEL)
            ->where('name', 'Grade 11')
            ->whereHas('parent', fn ($q) => $q->where('name', 'Public School'))
            ->first();
        $this->placeStudent($learner, $grade11, ['Mathematics', 'Physical Sciences', 'Accounting']);

        // University student, adult
        $student2 = $this->user('Lerato', 'Mokoena', 'student2@dxstudenthelp.co.za', User::ROLE_STUDENT, '2004-02-20');
        $faculty = CurriculumItem::where('type', CurriculumItem::TYPE_FACULTY)
            ->where('name', 'Information Technology')
            ->whereHas('parent', fn ($q) => $q->where('name', '1st Year')
                ->whereHas('parent', fn ($p) => $p->where('name', "Bachelor's Degree")))
            ->first();
        $this->placeStudent($student2, $faculty, ['Programming', 'Database Systems']);

        $tutors = [
            ['Kgomotso', 'Sithole', 'tutor@dxstudenthelp.co.za', 'BSc Mathematics, UKZN', ['Mathematics', 'Physical Sciences'], TutorProfile::STATUS_APPROVED, 4.8, 37],
            ['Johan', 'van Wyk', 'tutor2@dxstudenthelp.co.za', 'BCom Accounting, UNISA', ['Accounting', 'Business Studies'], TutorProfile::STATUS_APPROVED, 4.6, 21],
            ['Ayanda', 'Khumalo', 'tutor3@dxstudenthelp.co.za', 'BSc Computer Science, UCT', ['Programming', 'Information Technology'], TutorProfile::STATUS_APPROVED, 4.9, 54],
            ['Pieter', 'Botha', 'tutor4@dxstudenthelp.co.za', 'BEd Physical Sciences, NWU', ['Physical Sciences', 'Life Sciences'], TutorProfile::STATUS_PENDING, null, 0],
        ];

        $approved = [];

        foreach ($tutors as [$first, $last, $email, $qualification, $subjects, $status, $rating, $resolved]) {
            $user = $this->user($first, $last, $email, User::ROLE_TUTOR, '1993-06-15');

            $profile = TutorProfile::create([
                'user_id' => $user->id,
                'bio' => "{$first} helps learners with ".implode(' and ', $subjects).'.',
                'highest_qualification' => $qualification,
                'languages' => ['English', 'isiZulu'],
                'availability' => ['weekdays' => '16:00-20:00', 'weekends' => '09:00-13:00'],
                'verification_status' => $status,
                'reviewed_by' => $status === TutorProfile::STATUS_APPROVED ? $admin->id : null,
                'reviewed_at' => $status === TutorProfile::STATUS_APPROVED ? now()->subDays(10) : null,
                'average_rating' => $rating,
                'ratings_count' => $resolved,
                'resolved_count' => $resolved,
            ]);

            $items = CurriculumItem::where('type', CurriculumItem::TYPE_SUBJECT)
                ->whereIn('name', $subjects)
                ->limit(12)
                ->get();

            $profile->subjects()->sync($items->pluck('id'));
            $user->subjects()->sync($items->pluck('id')->mapWithKeys(fn ($id) => [$id => ['role' => 'subject']]));

            if ($status === TutorProfile::STATUS_APPROVED) {
                $approved[] = $user;
            }
        }

        $this->seedRequests($learner, $approved[0] ?? null);
    }

    private function seedInstitutions(): void
    {
        foreach ([
            ['Durban High School', 'school', 'public', 'KwaZulu-Natal', 'Durban'],
            ['Westville Boys High School', 'school', 'public', 'KwaZulu-Natal', 'Durban'],
            ['Clifton College', 'school', 'private', 'KwaZulu-Natal', 'Durban'],
            ['Coastal KZN TVET College', 'college', 'public', 'KwaZulu-Natal', 'Durban'],
            ['Damelin Durban', 'college', 'private', 'KwaZulu-Natal', 'Durban'],
            ['University of KwaZulu-Natal', 'university', 'public', 'KwaZulu-Natal', 'Durban'],
            ['Durban University of Technology', 'university', 'public', 'KwaZulu-Natal', 'Durban'],
        ] as [$name, $type, $sector, $province, $city]) {
            Institution::create(compact('name', 'type', 'sector', 'province', 'city') + ['is_verified' => true]);
        }
    }

    private function user(string $first, string $last, string $email, string $role, string $dob): User
    {
        return User::create([
            'first_name' => $first,
            'last_name' => $last,
            'name' => "{$first} {$last}",
            'email' => $email,
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'date_of_birth' => $dob,
            'role' => $role,
            'status' => 'active',
        ]);
    }

    private function approveConsent(User $user): void
    {
        GuardianConsent::create([
            'user_id' => $user->id,
            'guardian_name' => 'Thandi Ndlovu',
            'guardian_email' => 'guardian@example.co.za',
            'guardian_mobile' => '+27 82 000 0000',
            'token' => Str::random(48),
            'status' => GuardianConsent::STATUS_APPROVED,
            'requested_at' => now()->subDays(14),
            'decided_at' => now()->subDays(13),
            'policy_version' => '1.0',
        ]);
    }

    private function placeStudent(User $user, ?CurriculumItem $context, array $subjectNames): void
    {
        if (! $context) {
            return;
        }

        $contextIds = collect($context->ancestors())->pluck('id')->push($context->id);
        $user->academicContext()->sync($contextIds->mapWithKeys(fn ($id) => [$id => ['role' => 'context']]));

        $subjects = $context->children()
            ->where('type', CurriculumItem::TYPE_SUBJECT)
            ->whereIn('name', $subjectNames)
            ->get();

        $user->subjects()->sync($subjects->pluck('id')->mapWithKeys(fn ($id) => [$id => ['role' => 'subject']]));

        $user->update(['onboarding_completed_at' => now()->subDays(12)]);
    }

    private function seedRequests(User $student, ?User $tutor): void
    {
        $subject = $student->subjects()->first();

        if (! $subject || ! $tutor) {
            return;
        }

        $resolved = HelpRequest::create([
            'student_id' => $student->id,
            'tutor_id' => $tutor->id,
            'subject_id' => $subject->id,
            'topic' => 'Factorising trinomials',
            'description' => 'I get stuck when the coefficient of x squared is not 1. Could you walk me through the method on a worked example?',
            'status' => HelpRequest::STATUS_RESOLVED,
            'assigned_at' => now()->subDays(3),
            'resolved_at' => now()->subDays(2),
            'last_activity_at' => now()->subDays(2),
        ]);

        Message::create([
            'help_request_id' => $resolved->id,
            'sender_id' => $tutor->id,
            'body' => 'Happy to help. Start by multiplying the first and last coefficients, then find two factors of that product which add to the middle coefficient.',
            'body_original' => 'Happy to help. Start by multiplying the first and last coefficients, then find two factors of that product which add to the middle coefficient.',
        ]);

        HelpRequest::create([
            'student_id' => $student->id,
            'subject_id' => $subject->id,
            'topic' => 'Newton\'s second law problem',
            'description' => 'A 5 kg block on a frictionless surface is pushed with 20 N. I worked out the acceleration but I am not sure how to show the free-body diagram.',
            'status' => HelpRequest::STATUS_OPEN,
            'last_activity_at' => now()->subHours(4),
        ]);
    }
}
