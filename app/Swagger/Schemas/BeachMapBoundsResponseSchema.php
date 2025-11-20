<?php

namespace App\Swagger\Schemas;

/**
 * @OA\Schema(
 *     schema="BeachMapBoundsResponse",
 *     title="Beach Map Bounds Response",
 *     description="Response schema for beach map bounds endpoint - returns geographic bounds and center of all active beaches without beach data. Used for initial map load.",
 *     type="object",
 *     @OA\Property(
 *         property="center",
 *         type="object",
 *         description="Geographic center (centroid) of all active beaches, calculated using PostGIS ST_Centroid function",
 *         required={"latitude", "longitude"},
 *         @OA\Property(
 *             property="latitude",
 *             type="number",
 *             format="float",
 *             nullable=true,
 *             description="Center latitude coordinate",
 *             example=43.947149705303886
 *         ),
 *         @OA\Property(
 *             property="longitude",
 *             type="number",
 *             format="float",
 *             nullable=true,
 *             description="Center longitude coordinate",
 *             example=9.871903842707175
 *         )
 *     ),
 *     @OA\Property(
 *         property="bounding_box",
 *         type="object",
 *         description="Bounding box containing all active beaches (min/max latitude and longitude)",
 *         required={"min_latitude", "min_longitude", "max_latitude", "max_longitude"},
 *         @OA\Property(
 *             property="min_latitude",
 *             type="number",
 *             format="float",
 *             nullable=true,
 *             description="Minimum latitude (southern boundary)",
 *             example=42.5
 *         ),
 *         @OA\Property(
 *             property="min_longitude",
 *             type="number",
 *             format="float",
 *             nullable=true,
 *             description="Minimum longitude (western boundary)",
 *             example=8.2
 *         ),
 *         @OA\Property(
 *             property="max_latitude",
 *             type="number",
 *             format="float",
 *             nullable=true,
 *             description="Maximum latitude (northern boundary)",
 *             example=45.8
 *         ),
 *         @OA\Property(
 *             property="max_longitude",
 *             type="number",
 *             format="float",
 *             nullable=true,
 *             description="Maximum longitude (eastern boundary)",
 *             example=15.6
 *         )
 *     ),
 *     @OA\Property(
 *         property="total_beaches",
 *         type="integer",
 *         description="Total count of active beaches (status_id=1)",
 *         example=1500
 *     )
 * )
 */
class BeachMapBoundsResponseSchema
{
    // This class is used only for Swagger documentation
}

