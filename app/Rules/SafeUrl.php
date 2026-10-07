<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Laravel's `url` rule accepts any scheme, so javascript: and data: get through
 * and end up in an href that a learner clicks. Only http and https belong in a
 * link we show to students.
 *
 * Also blocks private and loopback hosts, so a stored link cannot be used to
 * probe the server's own network from an administrator's browser.
 */
class SafeUrl implements ValidationRule
{
    private const BLOCKED_HOSTS = ['localhost', '127.0.0.1', '0.0.0.0', '::1', 'metadata.google.internal'];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (blank($value)) {
            return;
        }

        $parts = parse_url((string) $value);

        if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
            $fail('Enter a complete web address starting with https://.');

            return;
        }

        if (! in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
            $fail('Links must start with http:// or https://.');

            return;
        }

        $host = strtolower($parts['host']);

        if (in_array($host, self::BLOCKED_HOSTS, true)) {
            $fail('That address cannot be used here.');

            return;
        }

        // Private ranges: 10.x, 172.16-31.x, 192.168.x, 169.254.x
        if (filter_var($host, FILTER_VALIDATE_IP) && ! filter_var(
            $host,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        )) {
            $fail('That address cannot be used here.');
        }
    }
}
