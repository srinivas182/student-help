<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\Horizon;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    public function boot(): void
    {
        parent::boot();

        Horizon::night();
    }

    /** The queue dashboard shows job payloads, so it is administrators only. */
    protected function gate(): void
    {
        Gate::define('viewHorizon', fn (?User $user) => $user !== null
            && in_array($user->role, [User::ROLE_ADMIN, User::ROLE_SUPER_ADMIN], true));
    }
}
