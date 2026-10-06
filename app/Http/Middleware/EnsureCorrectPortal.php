<?php

namespace App\Http\Middleware;

use App\Domains\Identity\Portal;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps the two front doors separate: a student who signs in at the teacher
 * domain is sent to the student domain, and the other way round.
 */
class EnsureCorrectPortal
{
    public function __construct(private readonly Portal $portal)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || $this->portal->isSharedHost($request)) {
            return $next($request);
        }

        $belongsTo = $this->portal->forUser($user);

        if ($belongsTo !== $this->portal->current($request)) {
            return redirect()->away(
                $this->portal->urlFor($belongsTo, $request->getRequestUri()),
            );
        }

        return $next($request);
    }
}
