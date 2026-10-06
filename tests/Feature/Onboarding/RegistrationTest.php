<?php

use App\Domains\Identity\Models\GuardianConsent;
use App\Models\User;

function registrationPayload(array $overrides = []): array
{
    return array_merge([
        'first_name' => 'Lerato',
        'last_name' => 'Mokoena',
        'email' => 'lerato@example.co.za',
        'date_of_birth' => now()->subYears(22)->format('Y-m-d'),
        'role' => User::ROLE_STUDENT,
        'password' => 'Str0ngPassw0rd!2026',
        'password_confirmation' => 'Str0ngPassw0rd!2026',
        'terms' => true,
    ], $overrides);
}

it('registers an adult student without requiring guardian consent', function () {
    $this->post('/register', registrationPayload())
        ->assertRedirect(route('onboarding.show'));

    $user = User::where('email', 'lerato@example.co.za')->firstOrFail();

    expect($user->isMinor())->toBeFalse()
        ->and($user->canParticipate())->toBeTrue()
        ->and($user->guardianConsent)->toBeNull();
});

it('requires guardian details for a learner under 18', function () {
    $this->post('/register', registrationPayload([
        'email' => 'sipho@example.co.za',
        'date_of_birth' => now()->subYears(15)->format('Y-m-d'),
    ]))->assertSessionHasErrors(['guardian_name', 'guardian_email', 'guardian_mobile']);

    expect(User::where('email', 'sipho@example.co.za')->exists())->toBeFalse();
});

it('creates a pending consent record and restricts participation for a minor', function () {
    $this->post('/register', registrationPayload([
        'email' => 'sipho@example.co.za',
        'date_of_birth' => now()->subYears(15)->format('Y-m-d'),
        'guardian_name' => 'Thandi Ndlovu',
        'guardian_email' => 'thandi@example.co.za',
        'guardian_mobile' => '+27820000000',
    ]))->assertRedirect(route('onboarding.show'));

    $user = User::where('email', 'sipho@example.co.za')->firstOrFail();

    expect($user->isMinor())->toBeTrue()
        ->and($user->guardianConsent->status)->toBe(GuardianConsent::STATUS_PENDING)
        ->and($user->canParticipate())->toBeFalse();

    $user->guardianConsent->update(['status' => GuardianConsent::STATUS_APPROVED, 'decided_at' => now()]);

    expect($user->fresh()->canParticipate())->toBeTrue();
});

it('rejects registration below the minimum age', function () {
    $this->post('/register', registrationPayload([
        'email' => 'young@example.co.za',
        'date_of_birth' => now()->subYears(10)->format('Y-m-d'),
    ]))->assertSessionHasErrors('date_of_birth');
});

it('does not allow self-registration as an administrator', function () {
    $this->post('/register', registrationPayload([
        'email' => 'notadmin@example.co.za',
        'role' => User::ROLE_ADMIN,
    ]))->assertSessionHasErrors('role');
});
