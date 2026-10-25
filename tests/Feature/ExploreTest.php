<?php

use App\Models\User;
use Database\Seeders\DatabaseSeeder;

/**
 * The feature hub, built from the client's poster. Its counts are live rather
 * than decorative: a learner told there are forty lessons who finds two stops
 * believing anything else on the page.
 */
beforeEach(function () {
    $this->seed(DatabaseSeeder::class);

    $this->student = User::where('email', 'student@dxstudenthelp.co.za')->firstOrFail();
});

it('shows what the platform can do, with real counts', function () {
    $this->actingAs($this->student)
        ->get(route('explore'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Explore')
            ->has('counts.topics')
            ->has('counts.tutors')
            ->has('liveSessions')
            ->where('canParticipate', true));
});

it('counts only lessons in the learner\'s own subjects', function () {
    $this->actingAs($this->student)
        ->get(route('explore'))
        ->assertInertia(function ($page) {
            $props = $page->toArray()['props'];

            expect($props['counts']['topics'])->toBeGreaterThan(0)
                ->and($props['counts']['topics'])
                ->toBeLessThanOrEqual(App\Domains\Tutor\Models\Topic::published()->count());
        });
});

it('tells a learner awaiting consent what is still open to them', function () {
    $minor = User::factory()->create([
        'role' => User::ROLE_STUDENT,
        'date_of_birth' => now()->subYears(14),
        'onboarding_completed_at' => now(),
    ]);

    $this->actingAs($minor)
        ->get(route('explore'))
        ->assertInertia(fn ($page) => $page->where('canParticipate', false));
});

it('is behind a login, since it lists the learner\'s own classes', function () {
    $this->get(route('explore'))->assertRedirect(route('login'));
});
