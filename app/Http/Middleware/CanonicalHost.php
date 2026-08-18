<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Permanently redirects the "www." host to the canonical bare host defined by
 * config('app.url'), e.g. www.quizmitra.com -> quizmitra.com. This is a safety
 * net that guarantees the non-www canonical even on servers where the Apache
 * .htaccess rule does not apply (e.g. Nginx), fixing the www/non-www duplicate
 * the SEO audit reported.
 *
 * Deliberately conservative:
 *  - Only GET/HEAD are redirected, so payment IPN callbacks and form POSTs are
 *    never touched (a 301 can drop a POST body).
 *  - Only the exact "www." + canonical host is redirected, so staging domains,
 *    IPs and localhost are left completely alone (no accidental redirects and
 *    no redirect loops — the target host is never itself "www.").
 */
class CanonicalHost {
    public function handle(Request $request, Closure $next): Response {
        if (!$request->isMethod('GET') && !$request->isMethod('HEAD')) {
            return $next($request);
        }

        $canonicalHost = parse_url((string) config('app.url'), PHP_URL_HOST);

        // No canonical host, or a hostless/localhost dev URL: do nothing.
        if (!$canonicalHost || $canonicalHost === 'localhost' || str_starts_with($canonicalHost, 'www.')) {
            return $next($request);
        }

        // Only act on the exact www. variant of the canonical host.
        if (strcasecmp($request->getHost(), 'www.' . $canonicalHost) !== 0) {
            return $next($request);
        }

        $scheme = parse_url((string) config('app.url'), PHP_URL_SCHEME) ?: 'https';
        $target = $scheme . '://' . $canonicalHost . $request->getRequestUri();

        return redirect()->away($target, 301);
    }
}
