<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restrict a route to admin users (users.is_admin = true).
 *
 * Must run after auth:sanctum. Any member of the public can obtain a Sanctum
 * token via /register or Google sign-in, so data-mutating endpoints need this
 * on top of authentication.
 */
class EnsureUserIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->is_admin !== true) {
            return response()->json([
                'message' => 'Forbidden.',
            ], 403);
        }

        return $next($request);
    }
}
