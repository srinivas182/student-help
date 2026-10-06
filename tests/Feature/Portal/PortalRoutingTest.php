<?php

use App\Models\User;

beforeEach(function () {
    config([
        'portals.student.host' => 'student-help.rightally.io',
        'portals.teacher.host' => 'teacher-help.rightally.io',
        'portals.allow_shared_host' => true,
    ]);
});

it('sends a student who lands on the teacher domain back to their own', function () {
    $student = User::factory()->create(['role' => User::ROLE_STUDENT, 'onboarding_completed_at' => now()]);

    $this->actingAs($student)
        ->get('http://teacher-help.rightally.io/dashboard')
        ->assertRedirect('http://student-help.rightally.io/dashboard');
});

it('sends a tutor who lands on the student domain to the teacher portal', function () {
    $tutor = User::factory()->create(['role' => User::ROLE_TUTOR, 'onboarding_completed_at' => now()]);

    $this->actingAs($tutor)
        ->get('http://student-help.rightally.io/dashboard')
        ->assertRedirect('http://teacher-help.rightally.io/dashboard');
});

it('lets each role through on its own domain', function () {
    $student = User::factory()->create(['role' => User::ROLE_STUDENT, 'onboarding_completed_at' => now()]);
    $tutor = User::factory()->create(['role' => User::ROLE_TUTOR, 'onboarding_completed_at' => now()]);

    $this->actingAs($student)->get('http://student-help.rightally.io/dashboard')->assertOk();
    $this->actingAs($tutor)->get('http://teacher-help.rightally.io/tutor/queue')->assertOk();
});

it('keeps staff on the teacher domain', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'onboarding_completed_at' => now()]);

    $this->actingAs($admin)
        ->get('http://student-help.rightally.io/admin')
        ->assertRedirect('http://teacher-help.rightally.io/admin');
});

it('serves both portals on a shared host during development', function () {
    $student = User::factory()->create(['role' => User::ROLE_STUDENT, 'onboarding_completed_at' => now()]);
    $tutor = User::factory()->create(['role' => User::ROLE_TUTOR, 'onboarding_completed_at' => now()]);

    $this->actingAs($student)->get('http://localhost/dashboard')->assertOk();
    $this->actingAs($tutor)->get('http://localhost/tutor/queue')->assertOk();
});

it('does not redirect guests, so both login pages work', function () {
    $this->get('http://teacher-help.rightally.io/login')->assertOk();
    $this->get('http://student-help.rightally.io/login')->assertOk();
});
