<?php

namespace App\Swagger\Schemas\AI;

/**
 * @OA\Schema(
 *     schema="AiBeachRecommendation",
 *     title="AI Beach Recommendation",
 *     description="Beach recommendation from AI agent",
 *     type="object",
 *     required={"id", "name", "slug", "country_code", "surface", "facilities", "match_score"},
 *     @OA\Property(
 *         property="id",
 *         type="integer",
 *         description="Beach ID",
 *         example=123
 *     ),
 *     @OA\Property(
 *         property="name",
 *         type="string",
 *         description="Beach name",
 *         example="Bačvice Beach"
 *     ),
 *     @OA\Property(
 *         property="slug",
 *         type="string",
 *         description="Beach slug for URL",
 *         example="bacvice-beach"
 *     ),
 *     @OA\Property(
 *         property="country_code",
 *         type="string",
 *         description="ISO 3166-1 alpha-2 country code",
 *         example="HR"
 *     ),
 *     @OA\Property(
 *         property="city",
 *         type="string",
 *         nullable=true,
 *         description="City name",
 *         example="Split"
 *     ),
 *     @OA\Property(
 * property="continent_slug",
 * type="string",
 * nullable=true,
 * description="Continent slug for URL building",
 * example="europe"
 * ),
 * @OA\Property(
 * property="country_slug",
 * type="string",
 * nullable=true,
 * description="Country slug for URL building",
 * example="croatia"
 * ),
 * @OA\Property(
 * property="region_slug",
 * type="string",
 * nullable=true,
 * description="Region slug for URL building (optional)",
 * example="dalmatia"
 * ),
 * @OA\Property(
 * property="island_slug",
 * type="string",
 * nullable=true,
 * description="Island slug for URL building (optional)",
 * example="island-hvar"
 * ),
 * @OA\Property(
 * property="city_slug",
 * type="string",
 * nullable=true,
 * description="City slug for URL building (optional)",
 * example="zadar"
 * ),
 * @OA\Property(
 * property="main_image",
 * type="string",
 * nullable=true,
 * description="Primary beach image path (relative, without CDN/domain prefix)",
 * example="img/beaches/hr-315-k-zadar/hr-315-k-zadar-sl1.jpg"
 * ),
 *     @OA\Property(
 *         property="surface",
 *         type="string",
 *         description="Beach surface type",
 *         example="sand"
 *     ),
 *     @OA\Property(
 *         property="facilities",
 *         type="array",
 *         description="Available facilities",
 *         @OA\Items(type="string"),
 *         example={"parking", "playground", "showers"}
 *     ),
 *     @OA\Property(
 *         property="distance_km",
 *         type="number",
 *         format="float",
 *         nullable=true,
 *         description="Distance from user location in kilometers (if location provided)",
 *         example=12.5
 *     ),
 *     @OA\Property(
 *         property="match_score",
 *         type="number",
 *         format="float",
 *         minimum=0,
 *         maximum=1,
 *         description="Relevance score (0-1)",
 *         example=0.95
 *     )
 * )
 */
class AiBeachRecommendationSchema {}

