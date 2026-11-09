<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Stops the browser from keeping copies of signed-in pages, so pressing Back
 * after logging out cannot show invoices, vendors or totals from the cache.
 * Also adds basic security headers to every page.
 */
class PreventBackHistory
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Security headers for every page.
        $response->headers->set('X-Frame-Options', 'DENY');                // can't be embedded in another site (clickjacking)
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'same-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        // Pages for signed-in users (and the login/register pages) must never be cached.
        // "no-store" also keeps them out of the browser's back/forward cache.
        if ($request->user() || $request->routeIs('login', 'register', 'logout')) {
            if (! $response instanceof BinaryFileResponse && ! $response instanceof StreamedResponse) {
                $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0, private');
                $response->headers->set('Pragma', 'no-cache');
                $response->headers->set('Expires', 'Sat, 01 Jan 2000 00:00:00 GMT');
            } else {
                $response->headers->set('Cache-Control', 'no-store, private');
            }
        }

        return $response;
    }
}
