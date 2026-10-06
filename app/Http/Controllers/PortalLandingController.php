<?php

namespace App\Http\Controllers;

use App\Domains\Identity\Portal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The public landing page. Which one a visitor sees depends on the domain they
 * arrived at, and each page links clearly to the other door.
 */
class PortalLandingController extends Controller
{
    public function __construct(private readonly Portal $portal)
    {
    }

    public function __invoke(Request $request): Response|RedirectResponse
    {
        if ($user = $request->user()) {
            $home = config('portals.'.$this->portal->forUser($user).'.home');

            return redirect()->route($home);
        }

        $current = $this->portal->current($request);
        $other = $current === Portal::STUDENT ? Portal::TEACHER : Portal::STUDENT;

        return Inertia::render('Welcome', [
            'portal' => $current,
            'name' => config("portals.{$current}.name"),
            'tagline' => config("portals.{$current}.tagline"),
            'otherPortal' => [
                'key' => $other,
                'name' => config("portals.{$other}.name"),
                'url' => $this->portal->urlFor($other),
            ],
        ]);
    }
}
