<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\BeachViewCounter;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

/**
 * Counts a visitor's view of a beach page.
 *
 * The website serves beach pages from a cache (ISR), so its server reads `/beach-details` about once an hour and
 * cannot count visitors. The browser reports the view here instead. Public on purpose (like the newsletter
 * sign-up): it only increments a counter, is rate limited per IP, and counts one view per visitor (IP) and beach
 * per 30 minutes, so reloads and bots cannot inflate the ranking.
 */
class BeachViewController extends Controller
{
    /** One counted view per visitor and beach in this window. */
    public const DEDUPE_SECONDS = 1800;

    /**
     * @OA\Post(
     *     path="/api/v1/beaches/{id}/view",
     *     summary="Count a beach page view",
     *     description="Called by the browser when a visitor opens a beach page. One view per visitor (IP) and beach per 30 minutes is counted; repeated calls return 204 without counting. Rate limited (60/min per IP).",
     *     operationId="countBeachView",
     *     tags={"Beaches"},
     *     @OA\Parameter(name="id", in="path", required=true, description="Beach ID", @OA\Schema(type="integer", example=123)),
     *     @OA\Response(response=204, description="Counted (or already counted for this visitor)"),
     *     @OA\Response(response=404, description="No active beach with this ID"),
     *     @OA\Response(response=429, description="Too many requests")
     * )
     */
    public function store(Request $request, int $id): Response
    {
        $visitor = hash('sha256', (string) $request->ip());
        $key = "beach_view:{$id}:{$visitor}";

        if (! Cache::add($key, 1, self::DEDUPE_SECONDS)) {
            return response()->noContent();
        }

        if (! BeachViewCounter::increment($id)) {
            Cache::forget($key);

            return response()->noContent(404);
        }

        return response()->noContent();
    }
}
