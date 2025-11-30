<?php

namespace App\Swagger\Schemas;

/**
 * @OA\Schema(
 *     schema="BeachMostVisitedResponse",
 *     title="Beach Most Visited Response",
 *     description="Response schema for most visited beaches endpoint - returns beaches ordered by beach_views counter with optional geographic filtering. Cached for 24 hours.",
 *     type="object",
 *     required={"success", "data", "message", "meta"},
 *     @OA\Property(
 *         property="success",
 *         type="boolean",
 *         description="Indicates if the request was successful",
 *         example=true
 *     ),
 *     @OA\Property(
 *         property="data",
 *         type="array",
 *         description="Array of beach objects ordered by beach_views (descending)",
 *         @OA\Items(ref="#/components/schemas/BeachSchema")
 *     ),
 *     @OA\Property(
 *         property="message",
 *         type="string",
 *         description="Success message",
 *         example="Most visited beaches retrieved successfully"
 *     ),
 *     @OA\Property(
 *         property="meta",
 *         type="object",
 *         description="Metadata about the response",
 *         required={"total", "limit"},
 *         @OA\Property(
 *             property="total",
 *             type="integer",
 *             description="Number of beaches returned in this response",
 *             example=20
 *         ),
 *         @OA\Property(
 *             property="limit",
 *             type="integer",
 *             description="Maximum number of beaches requested (default: 20, max: 100)",
 *             example=20
 *         )
 *     )
 * )
 */
class BeachMostVisitedResponseSchema
{
    // This class is used only for Swagger documentation
}

