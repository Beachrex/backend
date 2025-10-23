<?php

namespace App\Swagger\Schemas;

/**
 * @OA\Schema(
 *     schema="SpatialBeach",
 *     type="object",
 *     title="Spatial Beach",
 *     description="Beach with spatial distance information",
 *     @OA\Property(
 *         property="id",
 *         type="integer",
 *         description="Beach ID",
 *         example=1
 *     ),
 *     @OA\Property(
 *         property="name",
 *         type="string",
 *         description="Beach name",
 *         example="Copacabana Beach"
 *     ),
 *     @OA\Property(
 *         property="latitude",
 *         type="string",
 *         description="Beach latitude",
 *         example="-22.9711000"
 *     ),
 *     @OA\Property(
 *         property="longitude",
 *         type="string",
 *         description="Beach longitude",
 *         example="-43.1822000"
 *     ),
 *     @OA\Property(
 *         property="country_code",
 *         type="string",
 *         description="Two-letter country code",
 *         example="BR"
 *     ),
 *     @OA\Property(
 *         property="continent_code",
 *         type="string",
 *         description="Two-letter continent code",
 *         example="SA"
 *     ),
 *     @OA\Property(
 *         property="distance_km",
 *         type="number",
 *         format="float",
 *         description="Distance from reference point in kilometers",
 *         example=0
 *     )
 * )
 */

/**
 * @OA\Schema(
 *     schema="SpatialBeachesResponse",
 *     type="object",
 *     title="Spatial Beaches Response",
 *     description="Response containing beaches with spatial information",
 *     @OA\Property(
 *         property="data",
 *         type="array",
 *         description="Array of beaches with distance information",
 *         @OA\Items(ref="#/components/schemas/SpatialBeach")
 *     ),
 *     @OA\Property(
 *         property="meta",
 *         type="object",
 *         description="Metadata about the spatial query",
 *         @OA\Property(
 *             property="center",
 *             type="object",
 *             description="Center point of the search",
 *             @OA\Property(property="latitude", type="string", example="-22.9711"),
 *             @OA\Property(property="longitude", type="string", example="-43.1822")
 *         ),
 *         @OA\Property(
 *             property="radius_km",
 *             type="string",
 *             description="Search radius in kilometers (for radius queries)",
 *             example="1000"
 *         ),
 *         @OA\Property(
 *             property="count",
 *             type="integer",
 *             description="Number of beaches returned",
 *             example=1
 *         )
 *     )
 * )
 */

/**
 * @OA\Schema(
 *     schema="DistanceCalculationResponse",
 *     type="object",
 *     title="Distance Calculation Response",
 *     description="Response containing distance calculation between two points",
 *     @OA\Property(
 *         property="data",
 *         type="object",
 *         description="Distance calculation results",
 *         @OA\Property(
 *             property="distance_km",
 *             type="number",
 *             format="float",
 *             description="Distance in kilometers",
 *             example=10351.73
 *         ),
 *         @OA\Property(
 *             property="distance_miles",
 *             type="number",
 *             format="float",
 *             description="Distance in miles",
 *             example=6432.27
 *         )
 *     ),
 *     @OA\Property(
 *         property="meta",
 *         type="object",
 *         description="Metadata about the calculation",
 *         @OA\Property(
 *             property="point1",
 *             type="object",
 *             description="First point coordinates",
 *             @OA\Property(property="latitude", type="string", example="-22.9711"),
 *             @OA\Property(property="longitude", type="string", example="-43.1822")
 *         ),
 *         @OA\Property(
 *             property="point2",
 *             type="object",
 *             description="Second point coordinates",
 *             @OA\Property(property="latitude", type="string", example="36.3932"),
 *             @OA\Property(property="longitude", type="string", example="25.4615")
 *         )
 *     )
 * )
 */

/**
 * @OA\Schema(
 *     schema="BoundingBoxBeachesResponse",
 *     type="object",
 *     title="Bounding Box Beaches Response",
 *     description="Response containing beaches within a bounding box",
 *     @OA\Property(
 *         property="data",
 *         type="array",
 *         description="Array of beaches within the bounding box",
 *         @OA\Items(
 *             @OA\Property(property="id", type="integer", example=8),
 *             @OA\Property(property="name", type="string", example="Bled Lake Beach"),
 *             @OA\Property(property="latitude", type="string", example="46.3683000"),
 *             @OA\Property(property="longitude", type="string", example="14.1147000"),
 *             @OA\Property(property="country_code", type="string", example="SI"),
 *             @OA\Property(property="continent_code", type="string", example="EU")
 *         )
 *     ),
 *     @OA\Property(
 *         property="meta",
 *         type="object",
 *         description="Metadata about the bounding box query",
 *         @OA\Property(
 *             property="bounding_box",
 *             type="object",
 *             description="The bounding box coordinates",
 *             @OA\Property(property="min_latitude", type="string", example="35"),
 *             @OA\Property(property="min_longitude", type="string", example="10"),
 *             @OA\Property(property="max_latitude", type="string", example="50"),
 *             @OA\Property(property="max_longitude", type="string", example="30")
 *         ),
 *         @OA\Property(
 *             property="count",
 *             type="integer",
 *             description="Number of beaches found",
 *             example=3
 *         )
 *     )
 * )
 */

/**
 * @OA\Schema(
 *     schema="BeachStatisticsResponse",
 *     type="object",
 *     title="Beach Statistics Response",
 *     description="Response containing geographic statistics about beaches",
 *     @OA\Property(
 *         property="data",
 *         type="object",
 *         description="Statistical data about beach distribution",
 *         @OA\Property(
 *             property="total_beaches",
 *             type="integer",
 *             description="Total number of beaches",
 *             example=8
 *         ),
 *         @OA\Property(
 *             property="countries_with_beaches",
 *             type="integer",
 *             description="Number of countries with beaches",
 *             example=8
 *         ),
 *         @OA\Property(
 *             property="continents_with_beaches",
 *             type="integer",
 *             description="Number of continents with beaches",
 *             example=5
 *         ),
 *         @OA\Property(
 *             property="center_point",
 *             type="object",
 *             description="Geographic center of all beaches",
 *             @OA\Property(property="latitude", type="number", format="float", example=12.5979),
 *             @OA\Property(property="longitude", type="number", format="float", example=4.258275)
 *         ),
 *         @OA\Property(
 *             property="bounding_box",
 *             type="object",
 *             description="Bounding box containing all beaches",
 *             @OA\Property(property="min_latitude", type="number", format="float", example=-33.9553),
 *             @OA\Property(property="max_latitude", type="number", format="float", example=46.3683),
 *             @OA\Property(property="min_longitude", type="number", format="float", example=-157.8293),
 *             @OA\Property(property="max_longitude", type="number", format="float", example=151.2767)
 *         )
 *     )
 * )
 */

/**
 * @OA\Schema(
 *     schema="ValidationError",
 *     type="object",
 *     title="Validation Error",
 *     description="Validation error response",
 *     @OA\Property(
 *         property="message",
 *         type="string",
 *         description="Error message",
 *         example="The given data was invalid."
 *     ),
 *     @OA\Property(
 *         property="errors",
 *         type="object",
 *         description="Field-specific validation errors",
 *         @OA\Property(
 *             property="latitude",
 *             type="array",
 *             @OA\Items(type="string", example="The latitude field is required.")
 *         )
 *     )
 * )
 */
class SpatialSchemas
{
    // This class is used only for Swagger documentation
}
