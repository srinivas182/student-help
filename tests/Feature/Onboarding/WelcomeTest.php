<?php

use App\Domains\Curriculum\Models\CurriculumItem;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;

/**
 * A learner used to finish onboarding and land on a dashboard with no idea
 * what the platform could do. This is the one moment they are certain to be
 * paying attention.
 */
beforeEach(function () {
    $this->seed(DatabaseSeeder::class);

    $this->student = User::factory()->create([
        'role' => User::ROLE_STUDENT,
        'first_name' => 'Sipho',
        'date_of_birth' => now()->subYears(19),
        'onboarding_completed_at' => now(),
    ]);

    $maths = CurriculumItem::ofType(CurriculumItem::TYPE_SUBJECT)
        ->where('name', 'Mathematics')
        ->firstOrFail();

    $this->student->subjects()->syncWithoutDetaching([$maths->id => ['role' => 'subject']]);
});

it('greets the learner by name and names their subjects', function () {
    $this->actingAs($this->student)
        ->get(route('onboarding.welcome'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Onboarding/Welcome')
            ->where('firstName', 'Sipho')
            ->where('canParticipate', true)
            ->has('subjects'));
});

it('counts the lessons actually waiting in their subjects', function () {
    $this->actingAs($this->student)
        ->get(route('onboarding.welcome'))
        ->assertInertia(function ($page) {
            // Not a marketing number: it reflects what this learner can open
            expect($page->toArray()['props']['topicCount'])->toBeGreaterThan(0);
        });
});

it('tells a learner waiting on consent what they can still do', function () {
    $minor = User::factory()->create([
        'role' => User::ROLE_STUDENT,
        'first_name' => 'Lerato',
        'date_of_birth' => now()->subYears(14),
        'onboarding_completed_at' => now(),
    ]);

    $this->actingAs($minor)
        ->get(route('onboarding.welcome'))
        ->assertInertia(fn ($page) => $page->where('canParticipate', false));
});

it('sends a learner to the welcome screen when onboarding finishes', function () {
    $fresh = User::factory()->create([
        'role' => User::ROLE_STUDENT,
        'date_of_birth' => now()->subYears(19),
        'onboarding_completed_at' => null,
    ]);

    $subjects = CurriculumItem::ofType(CurriculumItem::TYPE_SUBJECT)->limit(2)->pluck('id');

    $this->actingAs($fresh)
        ->post(route('onboarding.subjects.store'), ['subject_ids' => $subjects->all()])
        ->assertRedirect(route('onboarding.welcome'));
});

it('is reachable after onboarding is already complete', function () {
    // It sits under the onboarding prefix, so it must not be bounced away the
    // way the wizard itself is once finished
    $this->actingAs($this->student)->get(route('onboarding.welcome'))->assertOk();
});
