<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user() ? [
                    'id' => $request->user()->id,
                    'name' => $request->user()->name,
                    'first_name' => $request->user()->first_name,
                    'last_name' => $request->user()->last_name,
                    'email' => $request->user()->email,
                    'role' => $request->user()->role,
                    'can_participate' => $request->user()->canParticipate(),
                    'is_minor' => $request->user()->isMinor(),
                    'can_review' => $request->user()->hasPermission('topics.review'),
                ] : null,
            ],
            'portal' => [
                'current' => app(\App\Domains\Identity\Portal::class)->current($request),
                'name' => config('portals.'.app(\App\Domains\Identity\Portal::class)->current($request).'.name'),
                'studentUrl' => app(\App\Domains\Identity\Portal::class)->urlFor('student'),
                'teacherUrl' => app(\App\Domains\Identity\Portal::class)->urlFor('teacher'),
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'warning' => fn () => $request->session()->get('warning'),
            ],
        ];
    }
}
