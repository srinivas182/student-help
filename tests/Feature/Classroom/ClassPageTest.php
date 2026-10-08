<?php

use App\Domains\Classroom\Models\Classroom;
use Database\Seeders\DatabaseSeeder;

/**
 * The class page returned 500 because ClassroomMember extended Model rather
 * than Pivot, so joined_at came back as a raw string and every date call threw.
 */
beforeEach(fn () => $this->seed(DatabaseSeeder::class));

it('opens every seeded class without erroring', function () {
    foreach (Classroom::with('teacher')->get() as $classroom) {
        $this->actingAs($classroom->teacher)
            ->get(route('classrooms.show', $classroom))
            ->assertOk();
    }
});

it('casts the joined date on the member pivot', function () {
    $classroom = Classroom::has('students')->firstOrFail();
    $student = $classroom->students()->firstOrFail();

    expect($student->pivot->joined_at)->toBeInstanceOf(Illuminate\Support\Carbon::class);
});
