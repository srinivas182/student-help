<?php

use App\Domains\Curriculum\Models\CurriculumItem;
use App\Domains\Tutoring\Models\HelpRequest;
use App\Domains\Tutoring\Models\HelpRequestOffer;
use App\Domains\Tutoring\Models\TutorProfile;
use App\Domains\Tutoring\Services\HelpRequestService;
use App\Models\User;
use Database\Seeders\CurriculumSeeder;
use Database\Seeders\SettingsSeeder;

function tutorFor(CurriculumItem $subject, string $status = TutorProfile::STATUS_APPROVED, bool $available = true): User
{
    $tutor = User::factory()->create([
        'role' => User::ROLE_TUTOR,
        'date_of_birth' => now()->subYears(30),
        'onboarding_completed_at' => now(),
    ]);

    $profile = TutorProfile::create([
        'user_id' => $tutor->id,
        'verification_status' => $status,
        'is_available' => $available,
    ]);

    $profile->subjects()->attach($subject->id);

    return $tutor;
}

function ask(User $student, CurriculumItem $subject, string $topic = 'Sample topic'): HelpRequest
{
    return app(HelpRequestService::class)->create($student, [
        'subject_id' => $subject->id,
        'topic' => $topic,
        'description' => 'I am stuck on this problem and would like to understand the method properly.',
    ]);
}

beforeEach(function () {
    $this->seed(CurriculumSeeder::class);
    $this->seed(SettingsSeeder::class);

    $this->subject = CurriculumItem::ofType(CurriculumItem::TYPE_SUBJECT)->first();

    $this->student = User::factory()->create([
        'role' => User::ROLE_STUDENT,
        'date_of_birth' => now()->subYears(20),
        'onboarding_completed_at' => now(),
    ]);
    $this->student->subjects()->attach($this->subject->id, ['role' => 'subject']);

    $this->tutor = tutorFor($this->subject);
});

it('creates a request and offers it to matching tutors', function () {
    $this->actingAs($this->student)
        ->post(route('requests.store'), [
            'subject_id' => $this->subject->id,
            'topic' => 'Factorising trinomials',
            'description' => 'I get stuck when the leading coefficient is not one. Could you show me the method?',
            'academic_honesty' => true,
        ])->assertRedirect();

    $request = HelpRequest::firstOrFail();

    expect($request->status)->toBe(HelpRequest::STATUS_OPEN)
        ->and($request->offers()->count())->toBe(1)
        ->and($request->offers()->first()->tutor_id)->toBe($this->tutor->id);
});

it('does not offer a request to unverified, unavailable or unrelated tutors', function () {
    tutorFor($this->subject, TutorProfile::STATUS_PENDING);
    tutorFor($this->subject, TutorProfile::STATUS_APPROVED, available: false);

    $otherSubject = CurriculumItem::ofType(CurriculumItem::TYPE_SUBJECT)
        ->where('id', '!=', $this->subject->id)->first();
    tutorFor($otherSubject);

    ask($this->student, $this->subject);

    expect(HelpRequest::firstOrFail()->offers()->count())->toBe(1);
});

it('blocks a minor without guardian consent from raising a request', function () {
    $minor = User::factory()->create([
        'role' => User::ROLE_STUDENT,
        'date_of_birth' => now()->subYears(15),
        'onboarding_completed_at' => now(),
    ]);
    $minor->subjects()->attach($this->subject->id, ['role' => 'subject']);

    $this->actingAs($minor)
        ->post(route('requests.store'), [
            'subject_id' => $this->subject->id,
            'topic' => 'Needs consent first',
            'description' => 'I would like help understanding this topic before my test next week.',
            'academic_honesty' => true,
        ])->assertSessionHasErrors('consent');

    expect(HelpRequest::count())->toBe(0);
});

it('enforces the open request limit', function () {
    foreach (range(1, 3) as $i) {
        ask($this->student, $this->subject, "Question {$i}");
    }

    expect(fn () => ask($this->student, $this->subject, 'One too many'))
        ->toThrow(Illuminate\Validation\ValidationException::class);
});

it('requires the academic honesty confirmation', function () {
    $this->actingAs($this->student)
        ->post(route('requests.store'), [
            'subject_id' => $this->subject->id,
            'topic' => 'Please do my homework',
            'description' => 'Here is my assignment, please complete it for me before Friday.',
            'academic_honesty' => false,
        ])->assertSessionHasErrors('academic_honesty');
});

it('rejects a subject the student has not selected', function () {
    $other = CurriculumItem::ofType(CurriculumItem::TYPE_SUBJECT)
        ->where('id', '!=', $this->subject->id)->first();

    $this->actingAs($this->student)
        ->post(route('requests.store'), [
            'subject_id' => $other->id,
            'topic' => 'Not my subject',
            'description' => 'I would like help with a subject that is not on my profile at all.',
            'academic_honesty' => true,
        ])->assertSessionHasErrors('subject_id');
});

it('lets the first tutor accept and locks out the rest', function () {
    $secondTutor = tutorFor($this->subject);
    $request = ask($this->student, $this->subject, 'Who takes it');

    $this->actingAs($this->tutor)
        ->post(route('tutor.requests.accept', $request))
        ->assertRedirect(route('tutor.queue'));

    $request->refresh();

    expect($request->status)->toBe(HelpRequest::STATUS_ASSIGNED)
        ->and($request->tutor_id)->toBe($this->tutor->id);

    $this->actingAs($secondTutor)
        ->post(route('tutor.requests.accept', $request))
        ->assertSessionHasErrors('request');
});

it('never re-offers a request a tutor declined', function () {
    $request = ask($this->student, $this->subject, 'Declined request');

    $this->actingAs($this->tutor)
        ->post(route('tutor.requests.decline', $request), ['reason' => 'Not my area']);

    expect($request->offers()->first()->status)->toBe(HelpRequestOffer::STATUS_DECLINED);

    $this->actingAs($this->tutor)
        ->get(route('tutor.queue'))
        ->assertInertia(fn ($page) => $page->has('available', 0));
});

it('escalates a request nobody accepted and auto-closes a stale resolved one', function () {
    $service = app(HelpRequestService::class);

    $stale = ask($this->student, $this->subject, 'Nobody took it');
    $stale->forceFill(['created_at' => now()->subHours(30)])->save();

    $resolved = ask($this->student, $this->subject, 'Answered a while ago');
    $service->accept($resolved, $this->tutor);
    $service->resolve($resolved, $this->tutor);
    $resolved->forceFill(['resolved_at' => now()->subHours(80)])->save();

    $this->artisan('requests:maintain')->assertSuccessful();

    expect($stale->fresh()->status)->toBe(HelpRequest::STATUS_ESCALATED)
        ->and($resolved->fresh()->status)->toBe(HelpRequest::STATUS_CLOSED);
});

it('walks the full lifecycle from request to closure', function () {
    $request = ask($this->student, $this->subject, 'Full lifecycle');
    expect($request->status)->toBe(HelpRequest::STATUS_OPEN);

    $this->actingAs($this->tutor)->post(route('tutor.requests.accept', $request));
    expect($request->fresh()->status)->toBe(HelpRequest::STATUS_ASSIGNED);

    $this->actingAs($this->tutor)->post(route('tutor.requests.resolve', $request));
    expect($request->fresh()->status)->toBe(HelpRequest::STATUS_RESOLVED);

    $this->actingAs($this->student)->post(route('requests.reopen', $request));
    expect($request->fresh()->status)->toBe(HelpRequest::STATUS_ASSIGNED);

    $this->actingAs($this->tutor)->post(route('tutor.requests.resolve', $request));
    $this->actingAs($this->student)->post(route('requests.confirm', $request));

    expect($request->fresh()->status)->toBe(HelpRequest::STATUS_CLOSED)
        ->and($this->tutor->tutorProfile->fresh()->resolved_count)->toBe(1);
});

it('stops a student seeing another student request', function () {
    $request = ask($this->student, $this->subject, 'Private');

    $other = User::factory()->create([
        'role' => User::ROLE_STUDENT,
        'onboarding_completed_at' => now(),
    ]);

    $this->actingAs($other)->get(route('requests.show', $request))->assertForbidden();
});
