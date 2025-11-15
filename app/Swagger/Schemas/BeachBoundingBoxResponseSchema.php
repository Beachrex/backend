<?php

namespace App\Swagger\Schemas;

/**
 * @OA\Schema(
 *     schema="BeachBoundingBoxResponseSchema",
 *     title="Beach Bounding Box Response Schema",
 *     description="Response schema for beaches in bounding box endpoint",
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
 *             property="bounding_box",
 *             type="object",
 *             description="The bounding box coordinates (user-provided)",
 *             @OA\Property(property="min_latitude", type="number", format="float", example=45.5, description="Minimum latitude"),
 *             @OA\Property(property="min_longitude", type="number", format="float", example=13.5, description="Minimum longitude"),
 *             @OA\Property(property="max_latitude", type="number", format="float", example=46.5, description="Maximum latitude"),
 *             @OA\Property(property="max_longitude", type="number", format="float", example=16.5, description="Maximum longitude")
 *         ),
 *         @OA\Property(
 *             property="center",
 *             type="object",
 *             description="Geographic center (centroid) of all beaches found in the bounding box, calculated using PostGIS ST_Centroid",
 *             @OA\Property(property="latitude", type="number", format="float", nullable=true, example=46.0, description="Center latitude"),
 *             @OA\Property(property="longitude", type="number", format="float", nullable=true, example=15.0, description="Center longitude")
 *         ),
 *         @OA\Property(property="count", type="integer", example=42, description="Number of beaches found")
 *     )
 * )
 */
class BeachBoundingBoxResponseSchema {}
