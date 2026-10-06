<?php

use App\Domains\Curriculum\Models\CurriculumItem;
use App\Models\User;
use Database\Seeders\CurriculumSeeder;

beforeEach(function () {
    $this->seed(CurriculumSeeder::class);

    $this->student = User::factory()->create([
        'role' => User::ROLE_STUDENT,
        'date_of_birth' => now()->subYears(20),
        'onboarding_completed_at' => null,
    ]);
});

it('offers the three pathways as the first step', function () {
    $this->actingAs($this->student)
        ->get(route('onboarding.show'))
        ->assertInertia(fn ($page) => $page
            ->component('Onboarding/Step')
            ->where('step.type', CurriculumItem::TYPE_PATHWAY)
            ->has('step.options', 3));
});

it('walks a school learner from pathway to subject selection', function () {
    $school = CurriculumItem::ofType(CurriculumItem::TYPE_PATHWAY)->where('name', 'School')->firstOrFail();
    $this->actingAs($this->student)->post(route('onboarding.store'), ['curriculum_item_id' => $school->id]);

    $public = $school->children()->where('name', 'Public School')->firstOrFail();
    $this->post(route('onboarding.store'), ['curriculum_item_id' => $public->id]);

    $grade11 = $public->children()->where('name', 'Grade 11')->firstOrFail();
    $this->post(route('onboarding.store'), ['curriculum_item_id' => $grade11->id])
        ->assertRedirect(route('onboarding.show'));

    $this->get(route('onboarding.show'))->assertRedirect(route('onboarding.subjects'));

    $this->get(route('onboarding.subjects'))
        ->assertInertia(fn ($page) => $page->component('Onboarding/Subjects')->has('subjects'));
});

it('rejects a choice that is not valid at the current step', function () {
    $grade = CurriculumItem::ofType(CurriculumItem::TYPE_LEVEL)->where('name', 'Grade 11')->firstOrFail();

    $this->actingAs($this->student)
        ->post(route('onboarding.store'), ['curriculum_item_id' => $grade->id])
        ->assertSessionHasErrors('curriculum_item_id');

    expect($this->student->academicContext()->count())->toBe(0);
});

it('completes onboarding once subjects are chosen', function () {
    $school = CurriculumItem::ofType(CurriculumItem::TYPE_PATHWAY)->where('name', 'School')->firstOrFail();
    $public = $school->children()->where('name', 'Public School')->firstOrFail();
    $grade = $public->children()->where('name', 'Grade 10')->firstOrFail();

    $this->actingAs($this->student);

    foreach ([$school, $public, $grade] as $item) {
        $this->post(route('onboarding.store'), ['curriculum_item_id' => $item->id]);
    }

    $subjects = $grade->children()->ofType(CurriculumItem::TYPE_SUBJECT)->limit(3)->pluck('id')->all();

    $this->post(route('onboarding.subjects.store'), ['subject_ids' => $subjects])
        ->assertRedirect(route('dashboard'));

    $student = $this->student->fresh();

    expect($student->hasCompletedOnboarding())->toBeTrue()
        ->and($student->subjects()->count())->toBe(3)
        ->and($student->academicContext()->count())->toBe(3);
});

it('requires at least one subject', function () {
    $school = CurriculumItem::ofType(CurriculumItem::TYPE_PATHWAY)->where('name', 'School')->firstOrFail();
    $public = $school->children()->where('name', 'Public School')->firstOrFail();
    $grade = $public->children()->where('name', 'Grade 9')->firstOrFail();

    $this->actingAs($this->student);

    foreach ([$school, $public, $grade] as $item) {
        $this->post(route('onboarding.store'), ['curriculum_item_id' => $item->id]);
    }

    $this->post(route('onboarding.subjects.store'), ['subject_ids' => []])
        ->assertSessionHasErrors('subject_ids');
});

it('lets a student step back to change an answer', function () {
    $school = CurriculumItem::ofType(CurriculumItem::TYPE_PATHWAY)->where('name', 'School')->firstOrFail();

    $this->actingAs($this->student)->post(route('onboarding.store'), ['curriculum_item_id' => $school->id]);
    expect($this->student->academicContext()->count())->toBe(1);

    $this->post(route('onboarding.back'));
    expect($this->student->fresh()->academicContext()->count())->toBe(0);
});

it('sends an unonboarded student from the dashboard back to onboarding', function () {
    $this->actingAs($this->student)
        ->get(route('dashboard'))
        ->assertRedirect(route('onboarding.show'));
});
