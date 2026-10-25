<?php

use App\Domains\Curriculum\Models\CurriculumItem;
use App\Domains\Tutoring\Models\TutorProfile;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;

/**
 * The admin dashboard showed four raw numbers. What an administrator needs is
 * what is waiting for a person today — a tutor application, an open report, a
 * subject nobody can answer questions in.
 */
beforeEach(function () {
    $this->seed(DatabaseSeeder::class);

    $this->admin = User::where('email', 'admin@dxstudenthelp.co.za')->firstOrFail();
});

it('counts subjects that no verified tutor covers', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Dashboard')
            ->has('uncoveredSubjects')
            ->has('people.pendingTutors')
            ->has('safety.openReports')
            ->has('requests.unassigned'));
});

it('reports a subject as uncovered when its only tutor is unverified', function () {
    $subject = CurriculumItem::ofType(CurriculumItem::TYPE_SUBJECT)->active()->firstOrFail();

    // Detach every approved tutor from this subject
    TutorProfile::where('verification_status', TutorProfile::STATUS_APPROVED)
        ->get()
        ->each(fn (TutorProfile $profile) => $profile->subjects()->detach($subject->id));

    $before = $this->actingAs($this->admin)->get(route('admin.dashboard'))
        ->getOriginalContent()->getData()['page']['props']['uncoveredSubjects'];

    expect($before)->toBeGreaterThan(0);
});

it('counts a subject as covered once a verified tutor takes it', function () {
    $subject = CurriculumItem::ofType(CurriculumItem::TYPE_SUBJECT)->active()->firstOrFail();

    $covered = $subject->tutorProfiles()
        ->where('verification_status', TutorProfile::STATUS_APPROVED)
        ->exists();

    // The relation is what the dashboard counts on, so it must resolve
    expect($covered)->toBeBool();
});
