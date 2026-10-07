<?php

use App\Domains\Tutor\Models\TopicVersion;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);

    $this->admin = User::where('email', 'admin@dxstudenthelp.co.za')->firstOrFail();
    $this->moderator = User::where('email', 'moderator@dxstudenthelp.co.za')->firstOrFail();
    $this->version = TopicVersion::where('status', TopicVersion::STATUS_PUBLISHED)->firstOrFail();
});

it('shows an administrator the lesson a student actually reads', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.topics.preview', $this->version))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Topics/Preview')
            ->has('segments')
            ->has('questions')
            ->has('flashcards')
            ->where('version.status', 'published'));
});

it('includes the misconception shown for each wrong answer', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.topics.preview', $this->version))
        ->assertInertia(function ($page) {
            $questions = collect($page->toArray()['props']['questions']);

            expect($questions)->not->toBeEmpty();

            $wrongOptions = collect($questions->first()['options'])
                ->reject(fn ($option, $index) => $index === $questions->first()['correctIndex']);

            expect($wrongOptions->pluck('misconception')->filter())->not->toBeEmpty();
        });
});

it('names who reviewed the lesson', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.topics.preview', $this->version))
        ->assertInertia(fn ($page) => $page->where('version.reviewer', 'Kgomotso Sithole'));
});

it('keeps the preview away from moderators', function () {
    $this->actingAs($this->moderator)
        ->get(route('admin.topics.preview', $this->version))
        ->assertForbidden();
});
