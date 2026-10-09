<?php

namespace App\Swagger\Schemas\AI;

/**
 * @OA\Schema(
 *     schema="ItineraryPlanSummary",
 *     type="object",
 *     description="A background itinerary (AI-3)",
 *     @OA\Property(property="id", type="string", format="uuid"),
 *     @OA\Property(property="status", type="string", enum={"queued", "running", "done", "failed"}),
 *     @OA\Property(property="country_code", type="string", example="HR"),
 *     @OA\Property(property="region_slug", type="string", nullable=true, example="istra"),
 *     @OA\Property(property="duration_days", type="integer", example=7),
 *     @OA\Property(property="title", type="string", nullable=true),
 *     @OA\Property(property="error", type="string", nullable=true, enum={"not_enough_beaches", "error", "timeout"}, description="Why a failed plan failed (its credit was given back)"),
 *     @OA\Property(property="credit_used", type="boolean", description="false when the credit was given back"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="finished_at", type="string", format="date-time", nullable=true)
 * )
 */
class ItineraryPlanSummarySchema {}
