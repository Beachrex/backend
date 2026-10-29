<?php

namespace App\Http\Middleware;

use App\Models\SiteSetting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * TASK-009 (owner 2026-10-29): the admin switch "AI trip planner (paid)" (System → Site settings, key
 * `ai_trip_planner_enabled`). Off → itineraries, itinerary plans, credit products and Stripe checkout answer 503
 * `ai_trip_planner_disabled`, for the website and the mobile app alike. Independent of the chat switch.
 */
class EnsureAiTripPlannerEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!SiteSetting::aiTripPlannerEnabled()) {
            return response()->json([
                'error' => 'ai_trip_planner_disabled',
                'message' => 'The AI trip planner is currently turned off.',
                'retry_after' => null,
            ], 503);
        }

        return $next($request);
    }
}
