<?php

use App\Models\User;
use Database\Seeders\DatabaseSeeder;

/**
 * Admin routes were behind a single 'staff' middleware, so a moderator could
 * reach platform settings, gateways, roles and the security screen. The
 * permission system existed; nothing was using it.
 */
beforeEach(function () {
    $this->seed(DatabaseSeeder::class);

    $this->superAdmin = User::where('email', 'superadmin@dxstudenthelp.co.za')->firstOrFail();
    $this->admin = User::where('email', 'admin@dxstudenthelp.co.za')->firstOrFail();
    $this->moderator = User::where('email', 'moderator@dxstudenthelp.co.za')->firstOrFail();
});

it('keeps moderators out of platform configuration', function () {
    foreach (['admin.settings', 'admin.security', 'admin.gateways', 'admin.roles.index'] as $route) {
        $this->actingAs($this->moderator)
            ->get(route($route))
            ->assertForbidden();
    }
});

it('keeps moderators out of user and curriculum management', function () {
    foreach (['admin.users.index', 'admin.curriculum.index', 'admin.topics.index'] as $route) {
        $this->actingAs($this->moderator)
            ->get(route($route))
            ->assertForbidden();
    }
});

it('lets moderators do the job they are for', function () {
    foreach (['moderation.index', 'moderation.groups', 'moderation.voice', 'admin.resources.index'] as $route) {
        $this->actingAs($this->moderator)->get(route($route))->assertOk();
    }
});

it('keeps administrators out of settings reserved for the super admin', function () {
    foreach (['admin.settings', 'admin.security', 'admin.gateways', 'admin.roles.index'] as $route) {
        $this->actingAs($this->admin)->get(route($route))->assertForbidden();
    }
});

it('lets administrators run the platform day to day', function () {
    foreach ([
        'admin.users.index', 'admin.curriculum.index', 'admin.topics.index',
        'admin.verification.index', 'admin.assistant', 'admin.audit',
    ] as $route) {
        $this->actingAs($this->admin)->get(route($route))->assertOk();
    }
});

it('gives the super admin everything', function () {
    foreach ([
        'admin.settings', 'admin.security', 'admin.gateways', 'admin.roles.index',
        'admin.users.index', 'admin.topics.index', 'moderation.index',
    ] as $route) {
        $this->actingAs($this->superAdmin)->get(route($route))->assertOk();
    }
});
