<?php

namespace App\Swagger\Schemas;

/**
 * @OA\Schema(
 *     schema="NearbyCitiesResponseSchema",
 *     title="Nearby Cities Response Schema",
 *     description="Response schema for nearby cities endpoint",
 *     type="object",
 *     @OA\Property(
 *         property="data",
 *         type="array",
 *         description="Array of cities ordered by distance from reference point",
 *         @OA\Items(
 *             type="object",
 *             @OA\Property(property="id", type="integer", description="City ID", example=1),
 *             @OA\Property(property="name", type="string", description="City name (translated)", example="Split"),
 *             @OA\Property(property="slug", type="string", description="City slug (translated)", example="split"),
 *             @OA\Property(property="country_code", type="string", description="Two-letter country code", example="HR"),
 *             @OA\Property(property="country_name", type="string", description="Country name (translated)", example="Croatia"),
 *             @OA\Property(property="country_slug", type="string", nullable=true, description="Country slug (translated)", example="croatia"),
 *             @OA\Property(property="continent_slug", type="string", nullable=true, description="Continent slug (translated)", example="europe"),
 *             @OA\Property(property="region_slug", type="string", nullable=true, description="Region slug (translated)", example="dalmatia"),
 *             @OA\Property(property="island_slug", type="string", nullable=true, description="Island slug (translated)"),
 *             @OA\Property(property="latitude", type="string", description="City latitude", example="43.5081000"),
 *             @OA\Property(property="longitude", type="string", description="City longitude", example="16.4402000"),
 *             @OA\Property(property="beaches_count", type="integer", description="Number of active beaches in this city", example=5),
 *             @OA\Property(property="main_image", type="string", nullable=true, description="Main image path for the city", example="img/cities/split/main.jpg"),
 *             @OA\Property(property="distance_km", type="number", format="float", description="Distance from reference point in kilometers", example=12.34)
 *         )
 *     ),
 *     @OA\Property(
 *         property="meta",
 *         type="object",
 *         description="Metadata about the response",
 *         @OA\Property(
 *             property="reference_point",
 *             type="object",
 *             description="The reference point used for distance calculation",
 *             @OA\Property(property="latitude", type="number", format="float", example=43.5, description="Reference point latitude"),
 *             @OA\Property(property="longitude", type="number", format="float", example=16.4, description="Reference point longitude")
 *         ),
 *         @OA\Property(property="radius_km", type="number", format="float", example=50, description="Search radius in kilometers"),
 *         @OA\Property(property="limit", type="integer", example=10, description="Maximum number of cities requested"),
 *         @OA\Property(property="count", type="integer", example=5, description="Number of cities returned")
 *     )
 * )
 */
class NearbyCitiesResponseSchema {}

