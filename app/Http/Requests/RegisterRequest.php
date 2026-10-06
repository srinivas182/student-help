<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Registration (SRS: AUTH-01 – AUTH-04, CON-01).
 *
 * Only Student and Tutor may self-register (BRL-01); administrator accounts
 * are created by invitation only.
 */
class RegisterRequest extends FormRequest
{
    public function rules(): array
    {
        $minimumAge = (int) setting('minimum_registration_age', 13);
        $latestAllowedBirthDate = Carbon::now()->subYears($minimumAge)->format('Y-m-d');

        $rules = [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'date_of_birth' => ['required', 'date', 'before_or_equal:'.$latestAllowedBirthDate, 'after:'.Carbon::now()->subYears(100)->format('Y-m-d')],
            'role' => ['required', Rule::in([User::ROLE_STUDENT, User::ROLE_TUTOR])],
            'mobile' => ['nullable', 'string', 'max:32'],
            'password' => ['required', 'confirmed', Password::defaults()->min(10)->mixedCase()->numbers()->uncompromised()],
            'terms' => ['accepted'],
        ];

        if ($this->isMinor()) {
            $rules['guardian_name'] = ['required', 'string', 'max:150'];
            $rules['guardian_email'] = ['required', 'email', 'max:255', 'different:email'];
            $rules['guardian_mobile'] = ['required', 'string', 'max:32'];
        }

        return $rules;
    }

    public function messages(): array
    {
        $minimumAge = (int) setting('minimum_registration_age', 13);

        return [
            'date_of_birth.before_or_equal' => "You need to be at least {$minimumAge} years old to register.",
            'guardian_name.required' => 'Because you are under 18, we need a parent or guardian to approve your account.',
            'guardian_email.required' => 'We will email your parent or guardian to ask for their approval.',
            'terms.accepted' => 'Please accept the terms of use and privacy policy.',
        ];
    }

    public function isMinor(): bool
    {
        $dob = $this->input('date_of_birth');

        return $dob ? Carbon::parse($dob)->age < User::MINOR_AGE : false;
    }
}
