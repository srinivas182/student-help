<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Headers the browser enforces for us.
 *
 * The CSP is the valuable one: even if a contact-filter bypass ever let a
 * script tag through a message, the browser refuses to run it. Inline styles
 * are allowed because Tailwind and Inertia need them; inline scripts are not.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        // A per-request nonce lets the few inline scripts we genuinely need
        // (Ziggy's route list, Vite's preloader) run, while still refusing any
        // script an attacker manages to inject.
        // One nonce per request, shared with Vite so its injected tags match
        $nonce = \Illuminate\Support\Facades\Vite::useCspNonce(base64_encode(random_bytes(16)));
        $request->attributes->set('csp_nonce', $nonce);
        view()->share('cspNonce', $nonce);

        $response = $next($request);

        $headers = [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'SAMEORIGIN',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Cross-Origin-Opener-Policy' => 'same-origin',
            'Permissions-Policy' => 'camera=(), geolocation=(), payment=(), usb=(), microphone=(self)',
            'X-Permitted-Cross-Domain-Policies' => 'none',
        ];

        // Only over HTTPS, and never in local development
        if ($request->secure() && ! app()->environment('local', 'testing')) {
            $headers['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains';
        }

        if (! $response->headers->has('Content-Security-Policy')) {
            $headers['Content-Security-Policy'] = $this->contentSecurityPolicy($nonce);
        }

        foreach ($headers as $name => $value) {
            $response->headers->set($name, $value);
        }

        return $response;
    }

    private function contentSecurityPolicy(string $nonce): string
    {
        // Vite serves modules from the dev server while developing
        $scriptSrc = app()->environment('local')
            ? "'self' 'unsafe-inline' 'unsafe-eval' http://localhost:* http://127.0.0.1:*"
            : "'self' 'nonce-{$nonce}'";

        $connectSrc = app()->environment('local')
            ? "'self' ws://localhost:* ws://127.0.0.1:* http://localhost:* http://127.0.0.1:*"
            : "'self'";

        return implode('; ', [
            "default-src 'self'",
            "script-src {$scriptSrc}",
            // Tailwind and Inertia inject styles at runtime
            // Fonts are loaded from Bunny, a privacy-respecting CDN
            "style-src 'self' 'unsafe-inline' https://fonts.bunny.net",
            "font-src 'self' data: https://fonts.bunny.net",
            "img-src 'self' data: blob: https:",
            // Voice notes play from blob URLs created in the browser
            "media-src 'self' blob:",
            "connect-src {$connectSrc}",
            // PayFast checkout posts to their domain
            'form-action '."'self' https://www.payfast.co.za https://sandbox.payfast.co.za",
            "frame-ancestors 'self'",
            "base-uri 'self'",
            "object-src 'none'",
        ]);
    }
}
