<?php

namespace App\Http\Middleware;

use App\Domains\Identity\Portal;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

        $arrivedAt = $this->portal->current($request);

        if ($belongsTo !== $arrivedAt) {
            $this->record($request, $arrivedAt, $belongsTo);

            return redirect()->away(
                $this->portal->urlFor($belongsTo, $request->getRequestUri()),
            );
        }

        return $next($request);
    }

    private function record(Request $request, string $from, string $to): void
    {
        DB::table('portal_redirects')->insert([
            'user_id' => $request->user()->id,
            'from_portal' => $from,
            'to_portal' => $to,
            'path' => mb_substr($request->path(), 0, 255),
            'role' => $request->user()->role,
            'created_at' => now(),
        ]);
    }
}
