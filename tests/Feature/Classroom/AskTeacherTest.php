<?php

use App\Domains\Classroom\Models\Classroom;
use App\Domains\Tutoring\Models\HelpRequest;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Notification;

/**
 * Asking from inside a class should skip the pick-a-subject, pick-a-topic,
 * wait-to-be-matched steps: the subject and the tutor are both already known.
 */
beforeEach(function () {
    Notification::fake();
    $this->seed(DatabaseSeeder::class);

    $this->classroom = Classroom::has('students')->whereNotNull('teacher_id')->firstOrFail();
    $this->teacher = $this->classroom->teacher;
    $this->student = $this->classroom->students()->firstOrFail();
});

it('sends the question straight to the class teacher', function () {
    $this->actingAs($this->student)->post(route('classrooms.askTeacher', $this->classroom), [
        'topic' => 'Question 4 on the past paper',
        'description' => 'I tried factorising but the brackets do not match.',
    ])->assertRedirect();

    $request = HelpRequest::latest('id')->firstOrFail();

    expect($request->tutor_id)->toBe($this->teacher->id)
        ->and($request->student_id)->toBe($this->student->id)
        // Already assigned: no waiting to be matched with anyone
        ->and($request->status)->toBe(HelpRequest::STATUS_ASSIGNED)
        // Subject comes from the class, so the learner never picks one
        ->and($request->subject_id)->toBe($this->classroom->curriculum_item_id);
});

it('tells the teacher about it', function () {
    $this->actingAs($this->student)->post(route('classrooms.askTeacher', $this->classroom), [
        'topic' => 'Help with homework',
        'description' => 'I am stuck on the second part of the question.',
    ]);

    Notification::assertSentTo($this->teacher, App\Notifications\RequestAccepted::class);
});

it('opens the conversation rather than a list', function () {
    $this->actingAs($this->student)->post(route('classrooms.askTeacher', $this->classroom), [
        'topic' => 'Help',
        'description' => 'Something I do not understand at all.',
    ])->assertRedirect(route('conversations.show', HelpRequest::latest('id')->first()));
});

it('keeps someone outside the class from messaging its teacher', function () {
    $outsider = User::factory()->create([
        'role' => User::ROLE_STUDENT,
        'date_of_birth' => now()->subYears(19),
        'onboarding_completed_at' => now(),
    ]);

    $this->actingAs($outsider)->post(route('classrooms.askTeacher', $this->classroom), [
        'topic' => 'Not my class',
        'description' => 'Letting myself in to message a teacher.',
    ])->assertForbidden();
});

it('holds a minor back until their guardian approves', function () {
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

    $this->actingAs($minor)->post(route('classrooms.askTeacher', $this->classroom), [
        'topic' => 'A question',
        'description' => 'Something I would like help with please.',
    ])->assertSessionHasErrors('ask');

    expect(HelpRequest::where('student_id', $minor->id)->exists())->toBeFalse();
});

it('wants enough detail for the teacher to work with', function () {
    $this->actingAs($this->student)->post(route('classrooms.askTeacher', $this->classroom), [
        'topic' => 'Help',
        'description' => 'stuck',
    ])->assertSessionHasErrors('description');
});
