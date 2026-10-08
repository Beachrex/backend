<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The backend (API, admin, docs) is never a page for search engines or AI crawlers, on any
 * environment. Owner, 2026-10-08: only the production website may be visible (TASK-004).
 */
class NoIndex
{
    public const HEADER_VALUE = 'noindex, nofollow, noarchive, nosnippet';

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $response->headers->set('X-Robots-Tag', self::HEADER_VALUE);

        return $response;
    }
}
