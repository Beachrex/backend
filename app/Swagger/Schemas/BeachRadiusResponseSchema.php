<?php

namespace App\Swagger\Schemas;

/**
 * @OA\Schema(
 *     schema="BeachRadiusResponseSchema",
 *     title="Beach Radius Response Schema",
 *     description="Response schema for beaches within radius endpoint",
 *     type="object",
 *     @OA\Property(
 *         property="data",
 *         type="array",
 *         description="Array of beaches",
 *         @OA\Items(ref="#/components/schemas/SpatialBeach")
 *     ),
 *     @OA\Property(
 *         property="meta",
 *         type="object",
 *         description="Metadata about the response",
 *         @OA\Property(
 *             property="search_center",
 *             type="object",
 *             description="The center point of the search radius (user-provided)",
 *             @OA\Property(property="latitude", type="number", format="float", example=46.0569, description="Search center latitude"),
 *             @OA\Property(property="longitude", type="number", format="float", example=14.5058, description="Search center longitude")
 *         ),
 *         @OA\Property(
 *             property="results_center",
 *             type="object",
 *             description="Geographic center (centroid) of all beaches found within the radius, calculated using PostGIS ST_Centroid",
 *             @OA\Property(property="latitude", type="number", format="float", nullable=true, example=46.1234, description="Results center latitude"),
 *             @OA\Property(property="longitude", type="number", format="float", nullable=true, example=14.6789, description="Results center longitude")
 *         ),
 *         @OA\Property(property="radius_km", type="number", format="float", example=50, description="Search radius in kilometers"),
 *         @OA\Property(property="count", type="integer", example=25, description="Number of beaches found"),
 *         @OA\Property(
 *             property="location",
 *             type="object",
 *             description="Geographic location context for the search center, determined by finding the nearest city with active beaches (within 100km). Useful for displaying contextual headers like 'Beaches near Split, Croatia'.",
 *             @OA\Property(property="city_name", type="string", nullable=true, example="Split", description="Translated name of the nearest city"),
 *             @OA\Property(property="city_slug", type="string", nullable=true, example="split", description="Localized URL slug of the nearest city"),
 *             @OA\Property(property="region_slug", type="string", nullable=true, example="dalmatia-split", description="Localized URL slug of the region"),
 *             @OA\Property(property="country_name", type="string", nullable=true, example="Croatia", description="Translated name of the country"),
 *             @OA\Property(property="country_slug", type="string", nullable=true, example="croatia", description="Localized URL slug of the country"),
 *             @OA\Property(property="country_code", type="string", nullable=true, example="HR", description="ISO 3166-1 alpha-2 country code"),
 *             @OA\Property(property="continent_slug", type="string", nullable=true, example="europe", description="Localized URL slug of the continent")
 *         )
 *     )
 * )
 */
class BeachRadiusResponseSchema {}
