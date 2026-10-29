<?php

namespace App\Http\Middleware;

use App\Models\SiteSetting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * TASK-009: the admin switch "AI guide on" (System → Site settings). Off → every AI route and the purchase of AI
 * credits answer 503 `ai_disabled`, for the website and the mobile app alike.
 */
class EnsureAiAssistantEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!SiteSetting::aiAssistantEnabled()) {
            return response()->json([
                'error' => 'ai_disabled',
                'message' => 'The AI beach guide is currently turned off.',
                'retry_after' => null,
            ], 503);
        }

        return $next($request);
    }
}
