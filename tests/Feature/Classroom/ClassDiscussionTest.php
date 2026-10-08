<?php

use App\Domains\Classroom\Models\Classroom;
use App\Domains\Classroom\Models\ClassroomPost;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;

/**
 * Only the teacher could post, so a student who joined a class had no way to
 * ask anything — which makes a class a broadcast rather than a class.
 */
beforeEach(function () {
    $this->seed(DatabaseSeeder::class);

    $this->classroom = Classroom::has('students')->firstOrFail();
    $this->teacher = $this->classroom->teacher;
    $this->student = $this->classroom->students()->firstOrFail();
});

it('lets a student ask the class a question', function () {
    $this->actingAs($this->student)->post(route('classrooms.posts.store', $this->classroom), [
        'type' => 'question',
        'title' => 'Stuck on question 4',
        'body' => 'I do not understand why the sign flips.',
    ])->assertRedirect();

    expect(ClassroomPost::where('type', 'question')->where('author_id', $this->student->id)->exists())
        ->toBeTrue();
});

it('will not let a student set a task or post a note', function () {
    foreach (['task', 'note'] as $type) {
        $this->actingAs($this->student)->post(route('classrooms.posts.store', $this->classroom), [
            'type' => $type,
            'title' => 'Not allowed',
            'body' => 'Students do not set work.',
        ])->assertSessionHasErrors('type');
    }
});

it('masks contact details a student posts, and keeps the original for moderators', function () {
    $this->actingAs($this->student)->post(route('classrooms.posts.store', $this->classroom), [
        'type' => 'question',
        'title' => 'Call me',
        'body' => 'Phone me on 082 123 4567 and we can talk.',
    ]);

    $post = ClassroomPost::where('author_id', $this->student->id)->latest('id')->firstOrFail();

    expect($post->body)->not->toContain('082 123 4567')
        ->and($post->body_original)->toContain('082 123 4567')
        ->and($post->is_flagged)->toBeTrue();
});

it('does not mask what the teacher writes', function () {
    $this->actingAs($this->teacher)->post(route('classrooms.posts.store', $this->classroom), [
        'type' => 'note',
        'title' => 'Contacting me',
        'body' => 'The school office number is 011 555 0000.',
    ]);

    $post = ClassroomPost::where('author_id', $this->teacher->id)->latest('id')->firstOrFail();

    expect($post->body)->toContain('011 555 0000')
        ->and($post->is_flagged)->toBeFalse();
});

it('lets the teacher and other students reply', function () {
    $post = ClassroomPost::create([
        'classroom_id' => $this->classroom->id,
        'author_id' => $this->student->id,
        'type' => 'question',
        'title' => 'Help please',
        'body' => 'Why does this work?',
    ]);

    $this->actingAs($this->teacher)
        ->post(route('classrooms.posts.reply', [$this->classroom, $post]), [
            'body' => 'Good question — think about the number line.',
        ])->assertRedirect();

    expect($post->fresh()->replies_count)->toBe(1)
        ->and($post->replies()->first()->author_id)->toBe($this->teacher->id);
});

it('keeps people outside the class from posting in it', function () {
    $outsider = User::factory()->create([
        'role' => User::ROLE_STUDENT,
        'onboarding_completed_at' => now(),
    ]);

    $this->actingAs($outsider)->post(route('classrooms.posts.store', $this->classroom), [
        'type' => 'question',
        'title' => 'Not my class',
        'body' => 'Letting myself in.',
    ])->assertForbidden();
});

it('shows joining options alongside classes rather than below them', function () {
    $this->actingAs($this->student)
        ->get(route('classrooms.index'))
        ->assertInertia(fn ($page) => $page
            ->component('Classrooms/StudentIndex')
            ->has('classrooms')
            ->has('upcoming')
            ->has('discoverable'));
});
