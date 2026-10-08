<?php

use App\Domains\Classroom\Models\Classroom;
use App\Domains\Classroom\Models\ClassroomPost;
use App\Domains\Tutoring\Models\Report;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;

/**
 * Class posts cannot be deleted by anyone but a moderator, so that nothing can
 * be erased before it has been seen. Reporting is therefore the only route for
 * a post that should not stand.
 */
beforeEach(function () {
    $this->seed(DatabaseSeeder::class);

    $this->classroom = Classroom::has('students')->firstOrFail();
    $this->teacher = $this->classroom->teacher;
    $this->student = $this->classroom->students()->firstOrFail();

    $this->post = ClassroomPost::create([
        'classroom_id' => $this->classroom->id,
        'author_id' => $this->student->id,
        'type' => 'question',
        'title' => 'A question',
        'body' => 'Something that needs looking at.',
    ]);
});

it('lets anyone in the class report a post', function () {
    $this->actingAs($this->teacher)
        ->post(route('classrooms.posts.report', [$this->classroom, $this->post]), [
            'reason' => 'inappropriate',
        ])->assertRedirect();

    expect(Report::where('reportable_type', ClassroomPost::class)
        ->where('reportable_id', $this->post->id)
        ->exists())->toBeTrue();
});

it('treats a report about a minor as urgent', function () {
    $minor = User::factory()->create([
        'role' => User::ROLE_STUDENT,
        'date_of_birth' => now()->subYears(14),
        'onboarding_completed_at' => now(),
    ]);

    $this->classroom->members()->create([
        'user_id' => $minor->id,
        'status' => 'active',
        'joined_at' => now(),
    ]);

    $this->actingAs($minor)
        ->post(route('classrooms.posts.report', [$this->classroom, $this->post]), [
            'reason' => 'harassment',
        ]);

    expect(Report::latest('id')->first()->severity)->not->toBe('normal');
});

it('keeps people outside the class from reporting in it', function () {
    $outsider = User::factory()->create([
        'role' => User::ROLE_STUDENT,
        'onboarding_completed_at' => now(),
    ]);

    $this->actingAs($outsider)
        ->post(route('classrooms.posts.report', [$this->classroom, $this->post]), [
            'reason' => 'spam',
        ])->assertForbidden();
});

it('offers no way for an author or teacher to delete a class post', function () {
    $routes = collect(app('router')->getRoutes())
        ->map(fn ($route) => $route->getName())
        ->filter()
        ->filter(fn (string $name) => str_starts_with($name, 'classrooms.posts.'));

    // Removal lives in the moderation queue, nowhere else
    expect($routes->filter(fn ($name) => str_contains($name, 'destroy') || str_contains($name, 'delete')))
        ->toBeEmpty();
});
