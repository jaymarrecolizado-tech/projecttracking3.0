<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        // Shared before the request runs: Blade (incl. the Ziggy @routes
        // inline script) may render inside $next, so the nonce must exist
        // before the view is built, while the header can only be set after.
        $nonce = base64_encode(random_bytes(16));
        view()->share('cspNonce', $nonce);

        /** @var Response $response */
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        // Production only: Vite dev serves HMR over ws + inline bootstraps
        // that a strict policy would block, and HSTS on local http would
        // poison browsers against the dev server.
        if (app()->isProduction()) {
            $response->headers->set('Content-Security-Policy', implode('; ', [
                "default-src 'self'",
                "script-src 'self' 'nonce-{$nonce}'",
                "style-src 'self' https://fonts.bunny.net",
                'font-src https://fonts.bunny.net',
                "img-src 'self' data: https://*.basemaps.cartocdn.com",
                "connect-src 'self'",
                "form-action 'self'",
                "frame-ancestors 'self'",
                "object-src 'none'",
                "base-uri 'self'",
                'upgrade-insecure-requests',
            ]));
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
