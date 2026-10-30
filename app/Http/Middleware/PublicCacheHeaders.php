<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * HTTP caching for public, anonymous GET endpoints (ADR 2026-10-30 "API performance: layered caching").
 *
 * Route usage: `->middleware('public.cache:60')` (seconds the API's nginx may serve the answer without PHP).
 *
 * A successful GET/HEAD without an Authorization header gets:
 * - `Cache-Control: public, max-age=60, s-maxage=<ttl>, stale-while-revalidate=<ttl>, stale-if-error=86400`
 *   (browsers keep it a minute; a shared cache may serve it a bit stale while it refreshes);
 * - `Vary: Accept-Language` (the language comes from that header);
 * - `X-Accel-Expires: <ttl>`: the micro-cache in the API's nginx (deploy/nginx/api.<env>.conf) stores only
 *   responses with this header, so no other route is ever cached there by accident. nginx does not pass it on;
 * - `ETag` and `304 Not Modified` for a matching `If-None-Match`.
 * Anything else (errors, signed-in requests, POST) keeps Laravel's default `no-cache, private`.
 */
class PublicCacheHeaders
{
    private const BROWSER_MAX_AGE = 60;

    private const STALE_IF_ERROR = 86400;

    public function handle(Request $request, Closure $next, string $ttl = '60'): Response
    {
        $response = $next($request);

        if (! $request->isMethodCacheable() || $request->headers->has('Authorization') || $response->getStatusCode() !== 200) {
            return $response;
        }

        $ttl = max(1, (int) $ttl);
        $response->headers->set('Cache-Control', sprintf(
            'public, max-age=%d, s-maxage=%d, stale-while-revalidate=%d, stale-if-error=%d',
            min(self::BROWSER_MAX_AGE, $ttl),
            $ttl,
            $ttl,
            self::STALE_IF_ERROR,
        ));
        $response->setVary('Accept-Language', false);
        $response->headers->set('X-Accel-Expires', (string) $ttl);

        $content = $response->getContent();
        if (is_string($content) && $content !== '') {
            $response->setEtag(md5($content));
            if ($response->isNotModified($request)) {
                // A 304 must never be stored by nginx (it would answer other visitors with an empty body).
                $response->headers->remove('X-Accel-Expires');
            }
        }

        return $response;
    }
}
