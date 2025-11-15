<?php

namespace App\Swagger\Schemas;

/**
 * @OA\Schema(
 *     schema="BeachListResponseSchema",
 *     title="Beach List Response Schema",
 *     description="Response schema for beach list endpoints",
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
 *             property="center",
 *             type="object",
 *             description="Geographic center (centroid) of all filtered beaches, calculated using PostGIS ST_Centroid",
 *             @OA\Property(property="latitude", type="number", format="float", nullable=true, example=43.947149705303886, description="Center latitude"),
 *             @OA\Property(property="longitude", type="number", format="float", nullable=true, example=9.871903842707175, description="Center longitude")
 *         ),
 *         @OA\Property(property="total_beaches", type="integer", example=150, description="Total number of beaches returned (respects limit parameter)")
 *     )
 * )
 */
class BeachListResponseSchema {}
