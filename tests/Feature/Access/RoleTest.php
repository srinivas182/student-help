<?php

use App\Domains\Access\Models\ReviewerScope;
use App\Domains\Access\Models\Role;
use App\Domains\Access\Permissions;
use App\Domains\Curriculum\Models\CurriculumItem;
use App\Domains\Tutor\Models\Language;
use App\Models\User;
use Database\Seeders\CurriculumSeeder;
use Database\Seeders\LanguageSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->seed(CurriculumSeeder::class);
    $this->seed(LanguageSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->seed(SettingsSeeder::class);

    $this->superAdmin = User::factory()->create([
        'role' => User::ROLE_SUPER_ADMIN,
        'onboarding_completed_at' => now(),
    ]);

    $this->reviewer = User::factory()->create([
        'role' => User::ROLE_TUTOR,
        'onboarding_completed_at' => now(),
    ]);

    $this->maths = CurriculumItem::ofType(CurriculumItem::TYPE_SUBJECT)->where('name', 'Mathematics')->first()
        ?? CurriculumItem::ofType(CurriculumItem::TYPE_SUBJECT)->first();
    $this->zulu = Language::where('code', 'zu')->firstOrFail();
    $this->english = Language::where('code', 'en')->firstOrFail();
});

it('ships with the roles DX needs on day one', function () {
    expect(Role::count())->toBe(5)
        ->and(Role::where('slug', 'content-reviewer')->first()->permissions)
        ->toBe(['topics.review', 'topics.publish']);
});

it('gives a super administrator every permission implicitly', function () {
    foreach (array_keys(Permissions::all()) as $permission) {
        expect($this->superAdmin->hasPermission($permission))->toBeTrue();
    }
});

it('grants only what an assigned role allows', function () {
    $role = Role::where('slug', 'content-reviewer')->firstOrFail();
    $this->reviewer->roles()->attach($role);
    $this->reviewer->load('roles');

    expect($this->reviewer->hasPermission('topics.review'))->toBeTrue()
        ->and($this->reviewer->hasPermission('topics.publish'))->toBeTrue()
        // A reviewer must not reach users, settings or private conversations
        ->and($this->reviewer->hasPermission('users.manage'))->toBeFalse()
        ->and($this->reviewer->hasPermission('settings.manage'))->toBeFalse()
        ->and($this->reviewer->hasPermission('moderation.conversations'))->toBeFalse();
});

it('lets a reviewer with no scope review anything', function () {
    $this->reviewer->roles()->attach(Role::where('slug', 'content-reviewer')->firstOrFail());
    $this->reviewer->load(['roles', 'reviewerScopes']);

    expect($this->reviewer->mayReview($this->maths->id, $this->zulu->id))->toBeTrue();
});

it('limits a scoped reviewer to their subject and language', function () {
    $this->reviewer->roles()->attach(Role::where('slug', 'content-reviewer')->firstOrFail());

    ReviewerScope::create([
        'user_id' => $this->reviewer->id,
        'curriculum_item_id' => $this->maths->id,
        'language_id' => $this->zulu->id,
    ]);

    $other = CurriculumItem::ofType(CurriculumItem::TYPE_SUBJECT)
        ->where('id', '!=', $this->maths->id)->first();

    $this->reviewer->load(['roles', 'reviewerScopes']);

    expect($this->reviewer->mayReview($this->maths->id, $this->zulu->id))->toBeTrue()
        // Right subject, wrong language
        ->and($this->reviewer->mayReview($this->maths->id, $this->english->id))->toBeFalse()
        // Right language, wrong subject
        ->and($this->reviewer->mayReview($other->id, $this->zulu->id))->toBeFalse();
});

it('refuses review rights to someone without the permission at all', function () {
    $this->reviewer->load(['roles', 'reviewerScopes']);

    expect($this->reviewer->mayReview($this->maths->id, $this->english->id))->toBeFalse();
});

it('creates a custom role and ignores invented permissions', function () {
    $this->actingAs($this->superAdmin)->post(route('admin.roles.store'), [
        'name' => 'Language lead',
        'description' => 'Reviews isiZulu lessons only.',
        'permissions' => ['topics.review', 'not.a.real.permission'],
    ])->assertRedirect();

    $role = Role::where('slug', 'language-lead')->firstOrFail();

    expect($role->permissions)->toBe(['topics.review'])
        ->and($role->is_system)->toBeFalse();
});

it('protects built-in roles from deletion', function () {
    $system = Role::where('is_system', true)->firstOrFail();

    $this->actingAs($this->superAdmin)
        ->delete(route('admin.roles.destroy', $system))
        ->assertStatus(422);

    expect(Role::whereKey($system->id)->exists())->toBeTrue();
});

it('assigns and revokes a role, recording who did it', function () {
    $role = Role::where('slug', 'content-reviewer')->firstOrFail();

    $this->actingAs($this->superAdmin)->post(route('admin.roles.assign'), [
        'user_id' => $this->reviewer->id,
        'role_id' => $role->id,
    ])->assertRedirect();

    expect($this->reviewer->fresh()->roles)->toHaveCount(1)
        ->and(DB::table('role_user')->first()->assigned_by)->toBe($this->superAdmin->id)
        ->and(DB::table('audit_logs')->where('action', 'role.assigned')->count())->toBe(1);

    $this->actingAs($this->superAdmin)
        ->delete(route('admin.roles.revoke', [$role->id, $this->reviewer->id]))
        ->assertRedirect();

    expect($this->reviewer->fresh()->roles)->toHaveCount(0)
        ->and(DB::table('audit_logs')->where('action', 'role.revoked')->count())->toBe(1);
});

it('adds and removes a reviewer scope', function () {
    $this->actingAs($this->superAdmin)->post(route('admin.roles.scopes.add'), [
        'user_id' => $this->reviewer->id,
        'curriculum_item_id' => $this->maths->id,
        'language_id' => $this->zulu->id,
    ])->assertRedirect();

    $scope = ReviewerScope::firstOrFail();

    $this->actingAs($this->superAdmin)
        ->delete(route('admin.roles.scopes.remove', $scope))
        ->assertRedirect();

    expect(ReviewerScope::count())->toBe(0);
});

it('shows access and content decisions in the activity log', function () {
    $role = Role::where('slug', 'content-reviewer')->firstOrFail();

    $this->actingAs($this->superAdmin)->post(route('admin.roles.assign'), [
        'user_id' => $this->reviewer->id,
        'role_id' => $role->id,
    ]);

    $this->actingAs($this->superAdmin)
        ->get(route('admin.roles.activity'))
        ->assertInertia(fn ($page) => $page
            ->component('Admin/AccessActivity')
            ->has('entries.data', 1)
            ->where('entries.data.0.action', 'role.assigned'));
});

it('keeps role management away from everyone else', function () {
    $student = User::factory()->create(['role' => User::ROLE_STUDENT, 'onboarding_completed_at' => now()]);

    $this->actingAs($student)->get(route('admin.roles.index'))->assertForbidden();
    $this->actingAs($this->reviewer)->get(route('admin.roles.index'))->assertForbidden();
});
