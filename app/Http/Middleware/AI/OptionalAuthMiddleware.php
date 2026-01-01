<?php

namespace App\Http\Middleware\AI;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Optional Authentication Middleware
 *
 * Attempts to authenticate the user via Sanctum if a Bearer token is provided,
 * but allows the request to proceed even if no token is present (anonymous access).
 *
 * This enables the same endpoint to serve both anonymous (free tier) and
 * authenticated (paid tier) users.
 */
class OptionalAuthMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check if Authorization header is present
        if ($request->bearerToken()) {
            // Attempt to authenticate via Sanctum
            $user = Auth::guard('sanctum')->user();

            // Set the authenticated user on the auth guard
            if ($user) {
                Auth::setUser($user);
            }
        }

        // Continue with request (user may be authenticated or null)
        return $next($request);
    }
}

