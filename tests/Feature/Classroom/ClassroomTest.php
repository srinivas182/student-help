<?php

use App\Domains\Classroom\Models\Classroom;
use App\Domains\Classroom\Models\ClassroomMember;
use App\Domains\Classroom\Services\ClassroomService;
use App\Domains\Curriculum\Models\CurriculumItem;
use App\Domains\Curriculum\Models\Institution;
use App\Domains\Identity\Models\GuardianConsent;
use App\Domains\Tutoring\Models\TutorProfile;
use App\Models\User;
use Database\Seeders\CurriculumSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->seed(CurriculumSeeder::class);
    $this->seed(SettingsSeeder::class);

    $this->subject = CurriculumItem::ofType(CurriculumItem::TYPE_SUBJECT)->first();

    $this->school = Institution::create([
        'name' => 'Durban High School', 'type' => 'school', 'sector' => 'public',
        'province' => 'KwaZulu-Natal', 'city' => 'Durban', 'is_verified' => true,
    ]);

    $this->teacher = User::factory()->create(['role' => User::ROLE_TUTOR, 'onboarding_completed_at' => now()]);
    TutorProfile::create([
        'user_id' => $this->teacher->id,
        'verification_status' => TutorProfile::STATUS_APPROVED,
        'is_available' => true,
    ])->subjects()->attach($this->subject->id);
    $this->teacher->subjects()->attach($this->subject->id, ['role' => 'subject']);

    $this->student = User::factory()->create([
        'role' => User::ROLE_STUDENT,
        'date_of_birth' => now()->subYears(20),
        'onboarding_completed_at' => now(),
    ]);

    $this->admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'onboarding_completed_at' => now()]);
});

function makeClass(array $overrides = []): Classroom
{
    return app(ClassroomService::class)->create(test()->teacher, array_merge([
        'name' => 'Grade 11 Mathematics',
        'type' => Classroom::TYPE_PERSONAL,
        'curriculum_item_id' => test()->subject->id,
    ], $overrides));
}

it('creates a personal class that works immediately', function () {
    $this->actingAs($this->teacher)->post(route('classrooms.store'), [
        'name' => 'Saturday revision group',
        'type' => Classroom::TYPE_PERSONAL,
    ])->assertRedirect();

    $classroom = Classroom::firstOrFail();

    expect($classroom->type)->toBe(Classroom::TYPE_PERSONAL)
        ->and($classroom->school_link_status)->toBeNull()
        ->and($classroom->canAcceptJoins())->toBeTrue()
        ->and(strlen($classroom->join_code))->toBeGreaterThanOrEqual(6);
});

it('holds the school name back until DX approves the link', function () {
    $classroom = makeClass([
        'type' => Classroom::TYPE_SCHOOL,
        'institution_id' => $this->school->id,
    ]);

    expect($classroom->school_link_status)->toBe(Classroom::LINK_PENDING)
        ->and($classroom->showsSchoolName())->toBeFalse()
        ->and($classroom->displayName())->toBe('Grade 11 Mathematics')
        // The class still works while it waits
        ->and($classroom->canAcceptJoins())->toBeTrue();

    $this->actingAs($this->admin)
        ->post(route('admin.schoolLinks.approve', $classroom))
        ->assertRedirect();

    $classroom->refresh();

    expect($classroom->showsSchoolName())->toBeTrue()
        ->and($classroom->displayName())->toBe('Durban High School · Grade 11 Mathematics');
});

it('requires an institution when the class is school linked', function () {
    $this->actingAs($this->teacher)->post(route('classrooms.store'), [
        'name' => 'Fake school class',
        'type' => Classroom::TYPE_SCHOOL,
    ])->assertSessionHasErrors('institution_id');
});

it('keeps the class running as a personal group when a school link is rejected', function () {
    $classroom = makeClass(['type' => Classroom::TYPE_SCHOOL, 'institution_id' => $this->school->id]);

    $this->actingAs($this->admin)->post(route('admin.schoolLinks.reject', $classroom), [
        'reason' => 'We could not confirm that you teach at this school.',
    ])->assertRedirect();

    $classroom->refresh();

    expect($classroom->type)->toBe(Classroom::TYPE_PERSONAL)
        ->and($classroom->school_link_status)->toBe(Classroom::LINK_REJECTED)
        ->and($classroom->showsSchoolName())->toBeFalse()
        ->and($classroom->canAcceptJoins())->toBeTrue();
});

it('lets a student join with the code and see the class', function () {
    $classroom = makeClass();

    $this->actingAs($this->student)
        ->post(route('classrooms.join'), ['join_code' => $classroom->join_code])
        ->assertRedirect(route('classrooms.show', $classroom));

    expect($classroom->students()->count())->toBe(1);

    $this->actingAs($this->student)
        ->get(route('classrooms.show', $classroom))
        ->assertInertia(fn ($page) => $page
            ->component('Classrooms/Show')
            ->where('isTeacher', false)
            // Students never see the join code
            ->where('classroom.joinCode', null));
});

it('blocks a minor without guardian consent from joining', function () {
    $classroom = makeClass();

    $minor = User::factory()->create([
        'role' => User::ROLE_STUDENT,
        'date_of_birth' => now()->subYears(14),
        'onboarding_completed_at' => now(),
    ]);
    GuardianConsent::create([
        'user_id' => $minor->id,
        'guardian_name' => 'Parent',
        'guardian_email' => 'parent@example.co.za',
        'token' => Str::random(48),
        'status' => GuardianConsent::STATUS_PENDING,
        'requested_at' => now(),
    ]);

    $this->actingAs($minor)
        ->post(route('classrooms.join'), ['join_code' => $classroom->join_code])
        ->assertSessionHasErrors('join_code');

    expect($classroom->students()->count())->toBe(0);
});

it('stops a student joining a school class for a different school', function () {
    $other = Institution::create([
        'name' => 'Westville Boys High School', 'type' => 'school', 'sector' => 'public',
        'province' => 'KwaZulu-Natal', 'city' => 'Durban', 'is_verified' => true,
    ]);

    $classroom = makeClass(['type' => Classroom::TYPE_SCHOOL, 'institution_id' => $this->school->id]);
    app(ClassroomService::class)->approveSchoolLink($classroom, $this->admin);

    $this->student->update(['institution_id' => $other->id]);

    $this->actingAs($this->student)
        ->post(route('classrooms.join'), ['join_code' => $classroom->join_code])
        ->assertSessionHasErrors('join_code');
});

it('rejects a wrong code and respects capacity', function () {
    $this->actingAs($this->student)
        ->post(route('classrooms.join'), ['join_code' => 'NOPE99'])
        ->assertSessionHasErrors('join_code');

    $classroom = makeClass(['capacity' => 1]);
    app(ClassroomService::class)->join($classroom, $this->student);

    $second = User::factory()->create(['role' => User::ROLE_STUDENT, 'onboarding_completed_at' => now()]);

    $this->actingAs($second)
        ->post(route('classrooms.join'), ['join_code' => $classroom->join_code])
        ->assertSessionHasErrors('join_code');
});

it('rotates the join code so a leaked one stops working', function () {
    $classroom = makeClass();
    $original = $classroom->join_code;

    $this->actingAs($this->teacher)->post(route('classrooms.code', $classroom))->assertRedirect();

    expect($classroom->fresh()->join_code)->not->toBe($original);

    $this->actingAs($this->student)
        ->post(route('classrooms.join'), ['join_code' => $original])
        ->assertSessionHasErrors('join_code');
});

it('lets the teacher post notes and tasks, and students mark tasks done', function () {
    $classroom = makeClass();
    app(ClassroomService::class)->join($classroom, $this->student);

    $this->actingAs($this->teacher)->post(route('classrooms.posts.store', $classroom), [
        'type' => 'task',
        'title' => 'Exercise 4.2',
        'body' => 'Complete questions 1 to 10 before Friday.',
        'due_at' => now()->addDays(3)->format('Y-m-d H:i:s'),
    ])->assertRedirect();

    $post = $classroom->posts()->firstOrFail();

    $this->actingAs($this->student)
        ->post(route('classrooms.posts.complete', [$classroom, $post]))
        ->assertRedirect();

    expect($post->completedBy()->count())->toBe(1);
});

it('keeps non-members and other teachers out of a class', function () {
    $classroom = makeClass();

    $stranger = User::factory()->create(['role' => User::ROLE_STUDENT, 'onboarding_completed_at' => now()]);
    $otherTeacher = User::factory()->create(['role' => User::ROLE_TUTOR, 'onboarding_completed_at' => now()]);

    $this->actingAs($stranger)->get(route('classrooms.show', $classroom))->assertForbidden();
    $this->actingAs($otherTeacher)->get(route('classrooms.show', $classroom))->assertForbidden();
    $this->actingAs($otherTeacher)->post(route('classrooms.posts.store', $classroom), [
        'type' => 'note', 'title' => 'Not mine', 'body' => 'Should not be allowed.',
    ])->assertForbidden();
});

it('removes a student and lets a student leave', function () {
    $classroom = makeClass();
    app(ClassroomService::class)->join($classroom, $this->student);

    $this->actingAs($this->teacher)
        ->post(route('classrooms.members.remove', [$classroom, $this->student]))
        ->assertRedirect();

    expect($classroom->students()->count())->toBe(0)
        ->and(ClassroomMember::where('classroom_id', $classroom->id)->first()->status)
        ->toBe(ClassroomMember::STATUS_REMOVED);
});

it('does not let an unverified teacher create a class', function () {
    $pending = User::factory()->create(['role' => User::ROLE_TUTOR, 'onboarding_completed_at' => now()]);
    TutorProfile::create(['user_id' => $pending->id, 'verification_status' => TutorProfile::STATUS_PENDING]);

    $this->actingAs($pending)->post(route('classrooms.store'), [
        'name' => 'Not verified',
        'type' => Classroom::TYPE_PERSONAL,
    ])->assertForbidden();
});
