<?php

namespace App\Domains\Identity\Services;

use App\Domains\Identity\Models\GuardianConsent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class RegistrationService
{
    /**
     * Create the account and, for a learner under 18, raise the guardian
     * consent record that gates participation (SRS: CON-01 – CON-04).
     *
     * @param  array<string, mixed>  $data
     */
    public function register(array $data): User
    {
        return DB::transaction(function () use ($data) {
            $user = User::create([
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'name' => trim($data['first_name'].' '.$data['last_name']),
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'date_of_birth' => $data['date_of_birth'],
                'role' => $data['role'],
                'mobile' => $data['mobile'] ?? null,
                'status' => 'active',
            ]);

            if ($user->isMinor()) {
                GuardianConsent::create([
                    'user_id' => $user->id,
                    'guardian_name' => $data['guardian_name'],
                    'guardian_email' => $data['guardian_email'],
                    'guardian_mobile' => $data['guardian_mobile'] ?? null,
                    'token' => Str::random(64),
                    'status' => GuardianConsent::STATUS_PENDING,
                    'requested_at' => now(),
                    'policy_version' => (string) setting('policy_version', '1.0'),
                ]);
            }

            audit('user.registered', $user, ['role' => $user->role, 'is_minor' => $user->isMinor()]);

            return $user;
        });
    }
}
