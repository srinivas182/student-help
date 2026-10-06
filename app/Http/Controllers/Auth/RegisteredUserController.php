<?php

namespace App\Http\Controllers\Auth;

use App\Domains\Identity\Services\RegistrationService;
use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterRequest;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/Register', [
            'minimumAge' => (int) setting('minimum_registration_age', 13),
            'roles' => [
                ['value' => User::ROLE_STUDENT, 'label' => 'Student'],
                ['value' => User::ROLE_TUTOR, 'label' => 'Tutor'],
            ],
        ]);
    }

    public function store(RegisterRequest $request, RegistrationService $registration): RedirectResponse
    {
        $user = $registration->register($request->validated());

        event(new Registered($user));
        Auth::login($user);

        return redirect()->route('onboarding.show');
    }
}
