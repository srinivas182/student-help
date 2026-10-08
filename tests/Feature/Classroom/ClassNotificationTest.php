<?php

use App\Domains\Classroom\Models\Classroom;
use App\Domains\Classroom\Models\ClassroomPost;
use App\Notifications\ClassPostActivity;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Notification;

/**
 * Who hears about what. Notifying a whole class every time anyone speaks is
 * how people switch notifications off, and then miss the ones that matter.
 */
beforeEach(function () {
    Notification::fake();
    $this->seed(DatabaseSeeder::class);

    $this->classroom = Classroom::has('students')->firstOrFail();
    $this->teacher = $this->classroom->teacher;

    $students = $this->classroom->students()->get();
    $this->asker = $students->first();
    $this->bystander = $students->skip(1)->first();
});

it('tells only the teacher when a student asks', function () {
    $this->actingAs($this->asker)->post(route('classrooms.posts.store', $this->classroom), [
        'type' => 'question',
        'title' => 'Stuck on question 4',
        'body' => 'Why does the sign flip?',
    ]);

    Notification::assertSentTo($this->teacher, ClassPostActivity::class);

    // The rest of the class is not told that one of them asked something
    Notification::assertNotSentTo($this->bystander, ClassPostActivity::class);
    Notification::assertNotSentTo($this->asker, ClassPostActivity::class);
});

it('tells the whole class when the teacher posts a task', function () {
    $this->actingAs($this->teacher)->post(route('classrooms.posts.store', $this->classroom), [
        'type' => 'task',
        'title' => 'Past paper 2023',
        'body' => 'Finish questions 1 to 5 before Friday.',
        'due_at' => now()->addDays(3)->format('Y-m-d H:i:s'),
    ]);

    Notification::assertSentTo($this->asker, ClassPostActivity::class);
    Notification::assertSentTo($this->bystander, ClassPostActivity::class);
});

it('tells the person who asked when the teacher replies, and nobody else', function () {
    $post = ClassroomPost::create([
        'classroom_id' => $this->classroom->id,
        'author_id' => $this->asker->id,
        'type' => 'question',
        'title' => 'Help',
        'body' => 'Why does this work?',
    ]);

    $this->actingAs($this->teacher)
        ->post(route('classrooms.posts.reply', [$this->classroom, $post]), [
            'body' => 'Think about the number line.',
        ]);

    Notification::assertSentTo($this->asker, ClassPostActivity::class);
    Notification::assertNotSentTo($this->bystander, ClassPostActivity::class);
});

it('tells everyone already in the thread about a later reply', function () {
    $post = ClassroomPost::create([
        'classroom_id' => $this->classroom->id,
        'author_id' => $this->asker->id,
        'type' => 'question',
        'title' => 'Help',
        'body' => 'Why does this work?',
    ]);

    // The bystander joins the conversation
    $this->actingAs($this->bystander)
        ->post(route('classrooms.posts.reply', [$this->classroom, $post]), ['body' => 'I wondered too.']);

    Notification::fake();

    // Now the teacher answers: both of them should hear about it
    $this->actingAs($this->teacher)
        ->post(route('classrooms.posts.reply', [$this->classroom, $post]), ['body' => 'Here is why.']);

    Notification::assertSentTo($this->asker, ClassPostActivity::class);
    Notification::assertSentTo($this->bystander, ClassPostActivity::class);
});

it('never notifies the person who just posted', function () {
    $post = ClassroomPost::create([
        'classroom_id' => $this->classroom->id,
        'author_id' => $this->asker->id,
        'type' => 'question',
        'title' => 'Help',
        'body' => 'Why?',
    ]);

    $this->actingAs($this->asker)
        ->post(route('classrooms.posts.reply', [$this->classroom, $post]), ['body' => 'Adding to this.']);

    Notification::assertNotSentTo($this->asker, ClassPostActivity::class);
});
