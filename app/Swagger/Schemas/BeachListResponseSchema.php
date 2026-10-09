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
 *         @OA\Property(
 *             property="bounding_box",
 *             type="object",
 *             description="Bounding box of all filtered beaches (min/max coordinates)",
 *             @OA\Property(property="min_latitude", type="number", format="float", nullable=true, example=42.5, description="Minimum latitude"),
 *             @OA\Property(property="min_longitude", type="number", format="float", nullable=true, example=8.2, description="Minimum longitude"),
 *             @OA\Property(property="max_latitude", type="number", format="float", nullable=true, example=45.8, description="Maximum latitude"),
 *             @OA\Property(property="max_longitude", type="number", format="float", nullable=true, example=15.6, description="Maximum longitude")
 *         ),
 *         @OA\Property(property="total_beaches", type="integer", example=150, description="GET /beaches: number of beaches matching the filters (all pages); other endpoints: number returned"),
 *         @OA\Property(property="total", type="integer", example=150, description="GET /beaches: beaches matching the filters (all pages)"),
 *         @OA\Property(property="current_page", type="integer", example=1, description="GET /beaches: this page"),
 *         @OA\Property(property="per_page", type="integer", example=20, description="GET /beaches: page size"),
 *         @OA\Property(property="last_page", type="integer", example=8, description="GET /beaches: last page number"),
 *         @OA\Property(property="sort", type="string", enum={"popular","newest"}, example="popular", description="GET /beaches: order used")
 *     )
 * )
 */
class BeachListResponseSchema {}
