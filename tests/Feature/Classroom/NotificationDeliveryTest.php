<?php

use App\Domains\Classroom\Models\Classroom;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Queue;

/**
 * Every notification implements ShouldQueue. With no queue worker running — the
 * normal state on a fresh server — a queued in-app notification never arrives
 * and nothing anywhere says so. In-app delivery is a single insert, so it runs
 * synchronously; email still queues, because sending is slow and worth retrying.
 */
beforeEach(function () {
    $this->seed(DatabaseSeeder::class);

    $this->classroom = Classroom::has('students')->whereNotNull('teacher_id')->firstOrFail();
    $this->teacher = $this->classroom->teacher;
    $this->student = $this->classroom->students()->firstOrFail();
});

it('writes an in-app notification even with no queue worker running', function () {
    config(['queue.default' => 'database']);

    $before = $this->teacher->notifications()->count();

    $this->actingAs($this->student)->post(route('classrooms.posts.store', $this->classroom), [
        'type' => 'question',
        'title' => 'Stuck on question 4',
        'body' => 'Why does the sign flip when dividing?',
    ]);

    // Nothing was processed from the queue, and it arrived anyway
    expect($this->teacher->fresh()->notifications()->count())->toBe($before + 1);
});

it('tells the teacher when a learner asks them privately from a class', function () {
    $before = $this->teacher->notifications()->count();

    $this->actingAs($this->student)->post(route('classrooms.askTeacher', $this->classroom), [
        'topic' => 'Question 4',
        'description' => 'I tried factorising but the brackets do not match.',
    ]);

    expect($this->teacher->fresh()->notifications()->count())->toBe($before + 1);
});

it('shows a learner their own questions to this teacher on the class page', function () {
    $this->actingAs($this->student)->post(route('classrooms.askTeacher', $this->classroom), [
        'topic' => 'Finding my way back',
        'description' => 'I want to be able to find this conversation again later.',
    ]);

    $this->actingAs($this->student)
        ->get(route('classrooms.show', $this->classroom))
        // Seeded data may already hold requests between these two, so assert
        // the new one is listed rather than that it is the only one
        ->assertInertia(function ($page) {
            $topics = collect($page->toArray()['props']['myQuestions'])->pluck('topic');

            expect($topics)->toContain('Finding my way back');
        });
});
