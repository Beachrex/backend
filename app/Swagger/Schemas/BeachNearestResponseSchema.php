<?php

namespace App\Swagger\Schemas;

/**
 * @OA\Schema(
 *     schema="BeachNearestResponseSchema",
 *     title="Beach Nearest Response Schema",
 *     description="Response schema for nearest beaches endpoint",
 *     type="object",
 *     @OA\Property(
 *         property="data",
 *         type="array",
 *         description="Array of beaches ordered by distance from reference point",
 *         @OA\Items(ref="#/components/schemas/SpatialBeach")
 *     ),
 *     @OA\Property(
 *         property="meta",
 *         type="object",
 *         description="Metadata about the response",
 *         @OA\Property(
 *             property="reference_point",
 *             type="object",
 *             description="The reference point used for distance calculation",
 *             @OA\Property(property="latitude", type="number", format="float", example=46.0569, description="Reference point latitude"),
 *             @OA\Property(property="longitude", type="number", format="float", example=14.5058, description="Reference point longitude")
 *         ),
 *         @OA\Property(property="limit", type="integer", example=10, description="Maximum number of beaches requested"),
 *         @OA\Property(property="count", type="integer", example=10, description="Number of beaches returned")
 *     )
 * )
 */
class BeachNearestResponseSchema {}

