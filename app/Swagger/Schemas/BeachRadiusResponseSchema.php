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
 *         @OA\Items(ref="#/components/schemas/BeachSchema")
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
 *         @OA\Property(property="count", type="integer", example=25, description="Number of beaches found")
 *     )
 * )
 */
class BeachRadiusResponseSchema {}
