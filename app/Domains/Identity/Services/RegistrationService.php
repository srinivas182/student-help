<?php

namespace App\Domains\Identity\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;


class RegistrationService
{
    public function __construct(private readonly ConsentService $consent)
    {
    }

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
                // Creates the consent record and emails the guardian (CON-02).
                $this->consent->request($user, $data);
            }

            audit('user.registered', $user, ['role' => $user->role, 'is_minor' => $user->isMinor()]);

            return $user;
        });
    }
}
