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
        $host = $request->getHost();

        foreach ([self::STUDENT, self::TEACHER] as $portal) {
            if ($this->hostFor($portal) === $host) {
                return $portal;
            }
        }

        // Development, staging or an IP address: default to the student portal.
        return self::STUDENT;
    }

    public function isSharedHost(Request $request): bool
    {
        if (! config('portals.allow_shared_host')) {
            return false;
        }

        $host = $request->getHost();

        return $host !== $this->hostFor(self::STUDENT) && $host !== $this->hostFor(self::TEACHER);
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
