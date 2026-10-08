<?php

use App\Models\User;
use Database\Seeders\DatabaseSeeder;

/**
 * Signing in at the wrong door used to authenticate, then redirect across
 * domains where the session cookie does not follow — so the person landed back
 * on a login page with no explanation and assumed the site was broken.
 */
beforeEach(fn () => $this->seed(DatabaseSeeder::class));

it('tells a student they are at the teacher door, and does not sign them in', function () {
    $response = $this->from('http://x-teacher-help.mcs.bz/login')
        ->post('http://x-teacher-help.mcs.bz/login', [
            'email' => 'student@dxstudenthelp.co.za',
            'password' => 'password',
        ]);

    $response->assertSessionHasErrors('email');

    expect($response->getSession()->get('errors')->first('email'))
        ->toContain('x-student-help')
        ->and(auth()->check())->toBeFalse();
});

it('tells a tutor they are at the student door', function () {
    $response = $this->from('http://x-student-help.mcs.bz/login')
        ->post('http://x-student-help.mcs.bz/login', [
            'email' => 'tutor@dxstudenthelp.co.za',
            'password' => 'password',
        ]);

    $response->assertSessionHasErrors('email');

    expect($response->getSession()->get('errors')->first('email'))->toContain('x-teacher-help');
});

it('signs people in at the right door', function () {
    $this->post('http://x-student-help.mcs.bz/login', [
        'email' => 'student@dxstudenthelp.co.za',
        'password' => 'password',
    ])->assertRedirect();

    expect(auth()->check())->toBeTrue();
});
