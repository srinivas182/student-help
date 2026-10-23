<?php

namespace App\Domains\Identity;

use App\Models\User;
use Illuminate\Http\Request;

/**
 * Resolves which portal a request belongs to, and where a given user belongs.
 */
class Portal
{
    public const STUDENT = 'student';
    public const TEACHER = 'teacher';

    public function current(Request $request): string
    {
        $host = $this->normalise($request->getHost());

        foreach ([self::STUDENT, self::TEACHER] as $portal) {
            if ($this->normalise($this->hostFor($portal)) === $host) {
                return $portal;
            }
        }

        // Development, staging or an IP address: default to the student portal.
        return self::STUDENT;
    }

    /**
     * www.example.com and example.com are the same site.
     *
     * Matching the host exactly meant the www address fell through to the
     * default, so a teacher arriving at www.theirdomain saw the student
     * portal — with nothing to indicate why.
     */
    private function normalise(string $host): string
    {
        return strtolower(preg_replace('/^www\./i', '', trim($host)) ?? $host);
    }

    public function isSharedHost(Request $request): bool
    {
        if (! config('portals.allow_shared_host')) {
            return false;
        }

        $host = $this->normalise($request->getHost());

        return $host !== $this->normalise($this->hostFor(self::STUDENT))
            && $host !== $this->normalise($this->hostFor(self::TEACHER));
    }

    /** Which portal this user belongs to. */
    public function forUser(User $user): string
    {
        return in_array($user->role, config('portals.teacher.roles'), true)
            ? self::TEACHER
            : self::STUDENT;
    }

    public function hostFor(string $portal): string
    {
        return (string) config("portals.{$portal}.host");
    }

    public function urlFor(string $portal, ?string $path = null): string
    {
        $scheme = request()->isSecure() ? 'https' : 'http';

        return $scheme.'://'.$this->hostFor($portal).'/'.ltrim($path ?? '', '/');
    }

    /** @return array<string, mixed> */
    public function config(string $portal): array
    {
        return (array) config("portals.{$portal}");
    }
}
