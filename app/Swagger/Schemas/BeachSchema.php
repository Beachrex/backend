<?php

namespace App\Swagger\Schemas;

/**
 * @OA\Schema(
 *     schema="BeachSchema",
 *     title="Beach Schema",
 *     description="Beach API Resource",
 *     type="object",
 *     @OA\Property(property="id", type="integer", example=793, description="Beach ID"),
 *     @OA\Property(property="name", type="string", example="Beach Bucanj", description="Beach name (with English fallback if translation missing)"),
 *     @OA\Property(property="slug", type="string", example="beach-bucanj", description="URL-friendly slug (with English fallback if translation missing)"),
 *     @OA\Property(property="description", type="string", nullable=true, example="Beautiful beach with crystal clear water", description="Beach description (with English fallback if translation missing)"),
 *     @OA\Property(property="latitude", type="string", example="42.5996000", description="Beach latitude"),
 *     @OA\Property(property="longitude", type="string", example="18.2302000", description="Beach longitude"),
 *     @OA\Property(property="country_code", type="string", example="HR", description="ISO country code"),
 *     @OA\Property(property="country_name", type="string", example="Croatia", description="Country name (translated based on Accept-Language header)"),
 *     @OA\Property(property="country_slug", type="string", nullable=true, example="croatia", description="Country slug (translated based on Accept-Language header)"),
 *     @OA\Property(property="continent_code", type="string", example="EU", description="Continent code"),
 *     @OA\Property(property="continent_slug", type="string", nullable=true, example="europe", description="Continent slug (translated based on Accept-Language header)"),
 *     @OA\Property(property="region_slug", type="string", nullable=true, example="dalmatia", description="Region slug (translated based on Accept-Language header)"),
 *     @OA\Property(property="city_slug", type="string", nullable=true, example="split", description="City slug (translated based on Accept-Language header)"),
 *     @OA\Property(property="main_image", type="string", nullable=true, example="/img/beaches/hr-779-k-jdalmacija/hr-779-k-jdalmacija-sl4.jpg", description="Primary beach image path (prepend CDN URL on frontend)")
 * )
 */
class BeachSchema {}
