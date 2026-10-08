<?php

use App\Domains\Curriculum\Services\OnboardingService;
use App\Models\User;
use Database\Seeders\CurriculumSeeder;

beforeEach(fn () => $this->seed(CurriculumSeeder::class));

it('asks a tutor what they teach, not where they study', function () {
    $tutor = User::factory()->create(['role' => User::ROLE_TUTOR]);
    $student = User::factory()->create(['role' => User::ROLE_STUDENT]);

    $forTutor = app(OnboardingService::class)->nextStep($tutor);
    $forStudent = app(OnboardingService::class)->nextStep($student);

    expect($forTutor['title'])->toBe('What do you teach?')
        ->and($forStudent['title'])->toBe('Where are you studying?')
        // A qualified teacher should never be asked about their own studies
        ->and($forTutor['title'])->not->toContain('studying');
});
