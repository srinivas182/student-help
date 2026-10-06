<?php

use App\Domains\Curriculum\Models\CurriculumItem;
use App\Domains\Identity\Models\GuardianConsent;
use App\Domains\StudyGroup\Models\StudyGroup;
use App\Domains\StudyGroup\Models\StudyGroupMember;
use App\Domains\StudyGroup\Models\StudyGroupMessage;
use App\Domains\StudyGroup\Services\StudyGroupService;
use App\Models\User;
use Database\Seeders\CurriculumSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->seed(CurriculumSeeder::class);
    $this->seed(SettingsSeeder::class);

    $this->subject = CurriculumItem::ofType(CurriculumItem::TYPE_SUBJECT)->first();

    $this->owner = User::factory()->create([
        'role' => User::ROLE_STUDENT,
        'date_of_birth' => now()->subYears(17),
        'onboarding_completed_at' => now(),
    ]);
    GuardianConsent::create([
        'user_id' => $this->owner->id,
        'guardian_name' => 'Parent',
        'guardian_email' => 'parent@example.co.za',
        'token' => Str::random(48),
        'status' => GuardianConsent::STATUS_APPROVED,
        'requested_at' => now()->subDays(2),
        'decided_at' => now()->subDay(),
    ]);
    $this->owner->subjects()->attach($this->subject->id, ['role' => 'subject']);

    $this->classmate = User::factory()->create([
        'role' => User::ROLE_STUDENT,
        'date_of_birth' => now()->subYears(19),
        'onboarding_completed_at' => now(),
    ]);

    $this->moderator = User::factory()->create(['role' => User::ROLE_MODERATOR, 'onboarding_completed_at' => now()]);
});

function makeGroup(array $overrides = []): StudyGroup
{
    return app(StudyGroupService::class)->create(test()->owner, array_merge([
        'name' => 'Grade 11 Maths crew',
        'curriculum_item_id' => test()->subject->id,
    ], $overrides));
}

it('creates a group with the creator as owner', function () {
    $this->actingAs($this->owner)->post(route('studyGroups.store'), [
        'name' => 'Saturday study crew',
        'curriculum_item_id' => $this->subject->id,
    ])->assertRedirect();

    $group = StudyGroup::firstOrFail();

    expect($group->owner_id)->toBe($this->owner->id)
        ->and($group->members()->count())->toBe(1)
        ->and($group->capacity)->toBe(30);
});

it('only allows a subject the student actually studies', function () {
    $other = CurriculumItem::ofType(CurriculumItem::TYPE_SUBJECT)
        ->where('id', '!=', $this->subject->id)->first();

    $this->actingAs($this->owner)->post(route('studyGroups.store'), [
        'name' => 'Not my subject',
        'curriculum_item_id' => $other->id,
    ])->assertSessionHasErrors('curriculum_item_id');
});

it('blocks a minor without guardian consent from creating or joining', function () {
    $minor = User::factory()->create([
        'role' => User::ROLE_STUDENT,
        'date_of_birth' => now()->subYears(14),
        'onboarding_completed_at' => now(),
    ]);
    GuardianConsent::create([
        'user_id' => $minor->id,
        'guardian_name' => 'Parent',
        'guardian_email' => 'p@example.co.za',
        'token' => Str::random(48),
        'status' => GuardianConsent::STATUS_PENDING,
        'requested_at' => now(),
    ]);

    $group = makeGroup();

    $this->actingAs($minor)
        ->post(route('studyGroups.join'), ['join_code' => $group->join_code])
        ->assertSessionHasErrors('join_code');

    expect($group->members()->count())->toBe(1);
});

it('lets a classmate join with the code and caps the group at 30', function () {
    $group = makeGroup();

    $this->actingAs($this->classmate)
        ->post(route('studyGroups.join'), ['join_code' => $group->join_code])
        ->assertRedirect(route('studyGroups.show', $group));

    expect($group->members()->count())->toBe(2);

    $group->update(['capacity' => 2]);

    $third = User::factory()->create(['role' => User::ROLE_STUDENT, 'onboarding_completed_at' => now()]);

    $this->actingAs($third)
        ->post(route('studyGroups.join'), ['join_code' => $group->join_code])
        ->assertSessionHasErrors('join_code');
});

it('masks contact details in group messages and keeps the original for moderators', function () {
    $group = makeGroup();

    $this->actingAs($this->owner)->post(route('studyGroups.post', $group), [
        'body' => 'Add me on WhatsApp 082 123 4567 and we can chat there',
    ])->assertRedirect();

    $message = StudyGroupMessage::firstOrFail();

    expect($message->body)->not->toContain('082 123 4567')
        ->and($message->body_original)->toContain('082 123 4567')
        ->and($message->is_flagged)->toBeTrue()
        ->and($message->toArray())->not->toHaveKey('body_original');
});

it('flags a group automatically once concerns accumulate', function () {
    $group = makeGroup();
    app(StudyGroupService::class)->join($group, $this->classmate);

    foreach (range(1, 3) as $i) {
        $this->actingAs($this->owner)->post(route('studyGroups.post', $group), [
            'body' => "Call me on 08{$i} 123 456{$i} after school",
        ]);
    }

    $group->refresh();

    expect($group->report_count)->toBeGreaterThanOrEqual(StudyGroup::AUTO_FLAG_THRESHOLD)
        ->and($group->is_flagged)->toBeTrue();
});

it('counts a member report towards the group concern threshold', function () {
    $group = makeGroup();
    app(StudyGroupService::class)->join($group, $this->classmate);

    $this->actingAs($this->owner)->post(route('studyGroups.post', $group), ['body' => 'Anyone done question 4?']);
    $message = StudyGroupMessage::firstOrFail();

    $this->actingAs($this->classmate)
        ->post(route('studyGroups.report', [$group, $message]), ['reason' => 'harassment'])
        ->assertRedirect();

    expect($group->fresh()->report_count)->toBe(1)
        ->and(DB::table('reports')->count())->toBe(1);
});

it('keeps non-members out of a group entirely', function () {
    $group = makeGroup();
    $stranger = User::factory()->create(['role' => User::ROLE_STUDENT, 'onboarding_completed_at' => now()]);

    $this->actingAs($stranger)->get(route('studyGroups.show', $group))->assertForbidden();
    $this->actingAs($stranger)->get(route('studyGroups.poll', $group))->assertForbidden();
    $this->actingAs($stranger)->post(route('studyGroups.post', $group), ['body' => 'Hello'])
        ->assertSessionHasErrors('body');
});

it('lets a moderator close a group, which stops posting but keeps it readable', function () {
    $group = makeGroup();
    app(StudyGroupService::class)->join($group, $this->classmate);

    $this->actingAs($this->moderator)->post(route('moderation.groups.lock', $group), [
        'reason' => 'Repeated attempts to move the conversation off the platform.',
    ])->assertRedirect();

    $group->refresh();

    expect($group->is_locked)->toBeTrue();

    $this->actingAs($this->classmate)->post(route('studyGroups.post', $group), ['body' => 'Still here?'])
        ->assertSessionHasErrors('body');

    $this->actingAs($this->classmate)->get(route('studyGroups.show', $group))->assertOk();

    $this->actingAs($this->moderator)->post(route('moderation.groups.unlock', $group));

    expect($group->fresh()->is_locked)->toBeFalse()
        ->and($group->fresh()->report_count)->toBe(0);
});

it('gives moderators the unmasked thread and records the access', function () {
    $group = makeGroup();

    $this->actingAs($this->owner)->post(route('studyGroups.post', $group), [
        'body' => 'My number is 083 111 2222 if you need me',
    ]);

    $this->actingAs($this->moderator)
        ->get(route('moderation.groups.show', $group))
        ->assertInertia(fn ($page) => $page
            ->component('Moderation/StudyGroupThread')
            ->where('messages.0.wasMasked', true));

    expect(DB::table('audit_logs')->where('action', 'study_group.viewed_by_moderator')->count())->toBe(1);
});

it('hands the group to another member when the owner leaves', function () {
    $group = makeGroup();
    app(StudyGroupService::class)->join($group, $this->classmate);

    $this->actingAs($this->owner)->post(route('studyGroups.leave', $group))->assertRedirect();

    $group->refresh();

    expect($group->owner_id)->toBe($this->classmate->id)
        ->and($group->members()->count())->toBe(1);
});

it('does not let a member remove the owner, but the owner can remove members', function () {
    $group = makeGroup();
    app(StudyGroupService::class)->join($group, $this->classmate);

    $this->actingAs($this->classmate)
        ->post(route('studyGroups.members.remove', [$group, $this->owner]))
        ->assertForbidden();

    $this->actingAs($this->owner)
        ->post(route('studyGroups.members.remove', [$group, $this->classmate]))
        ->assertRedirect();

    expect($group->fresh()->members()->count())->toBe(1)
        ->and(StudyGroupMember::where('user_id', $this->classmate->id)->first()->status)
        ->toBe(StudyGroupMember::STATUS_REMOVED);
});

it('keeps students out of the moderation views', function () {
    $this->actingAs($this->owner)->get(route('moderation.groups'))->assertForbidden();
});
