<?php

namespace App\Swagger\Schemas;

/**
 * @OA\Schema(
 *     schema="SpatialBeach",
 *     type="object",
 *     title="Spatial Beach",
 *     description="Beach with spatial distance information, returned by spatial query endpoints (within-radius, nearest, bounding-box)",
 *     @OA\Property(property="id", type="integer", example=793, description="Beach ID"),
 *     @OA\Property(property="name", type="string", example="Copacabana Beach", description="Beach name (with English fallback if translation missing)"),
 *     @OA\Property(property="slug", type="string", example="copacabana-beach", description="URL-friendly slug (with English fallback if translation missing)"),
 *     @OA\Property(property="description", type="string", nullable=true, example="A famous beach in Rio de Janeiro", description="Beach description (translated, nullable)"),
 *     @OA\Property(property="latitude", type="string", example="-22.9711000", description="Beach latitude"),
 *     @OA\Property(property="longitude", type="string", example="-43.1822000", description="Beach longitude"),
 *     @OA\Property(property="country_code", type="string", example="BR", description="ISO country code"),
 *     @OA\Property(property="country_name", type="string", example="Brazil", description="Country name (translated based on Accept-Language header)"),
 *     @OA\Property(property="country_slug", type="string", nullable=true, example="brazil", description="Country slug (translated based on Accept-Language header)"),
 *     @OA\Property(property="continent_code", type="string", example="SA", description="Continent code"),
 *     @OA\Property(property="continent_slug", type="string", nullable=true, example="south-america", description="Continent slug (translated based on Accept-Language header)"),
 *     @OA\Property(property="region_slug", type="string", nullable=true, example="rio-de-janeiro", description="Region slug (translated based on Accept-Language header)"),
 *     @OA\Property(property="island_slug", type="string", nullable=true, example="island-hvar", description="Island slug (translated based on Accept-Language header)"),
 *     @OA\Property(property="city_name", type="string", nullable=true, example="Rio de Janeiro", description="City name (translated based on Accept-Language header, with English fallback)"),
 *     @OA\Property(property="city_slug", type="string", nullable=true, example="rio-de-janeiro", description="City slug (translated based on Accept-Language header)"),
 *     @OA\Property(property="main_image", type="string", nullable=true, example="img/beaches/br-1-copacabana/br-1-copacabana-sl1.jpg", description="Primary beach image path (relative, without CDN/domain prefix). Frontend constructs Imagor URLs by prepending IMAGOR_ENDPOINT + transformation params."),
 *     @OA\Property(
 *         property="media",
 *         type="object",
 *         description="Media collections for the beach. Only non-empty collections are included (except embeds which is always present).",
 *         @OA\Property(
 *             property="gallery",
 *             type="array",
 *             description="Beach gallery images (only present if beach has gallery images)",
 *             @OA\Items(
 *                 type="object",
 *                 @OA\Property(property="url", type="string", example="img/beaches/br-1-copacabana/br-1-copacabana-sl1.jpg", description="Relative path to image file"),
 *                 @OA\Property(property="is_primary", type="boolean", example=true, description="Whether this is the primary image"),
 *                 @OA\Property(property="order", type="integer", example=1, description="Display order")
 *             )
 *         ),
 *         @OA\Property(
 *             property="videos",
 *             type="array",
 *             description="Beach videos (only present if beach has videos)",
 *             @OA\Items(
 *                 type="object",
 *                 @OA\Property(property="url", type="string", example="img/beaches/br-1-copacabana/beach-video.mp4", description="Relative path to video file"),
 *                 @OA\Property(property="is_primary", type="boolean", example=false, description="Whether this is the primary video"),
 *                 @OA\Property(property="order", type="integer", example=1, description="Display order")
 *             )
 *         ),
 *         @OA\Property(
 *             property="spheres_360",
 *             type="array",
 *             description="360-degree sphere tours (only present if beach has 360 degree spheres)",
 *             @OA\Items(
 *                 type="object",
 *                 @OA\Property(property="tour_url", type="string", example="img/beaches/br-1-copacabana/vtour/tour.html", description="URL to 360 degree virtual tour")
 *             )
 *         ),
 *         @OA\Property(
 *             property="embeds",
 *             type="object",
 *             description="External embeds for the beach (always present)",
 *             @OA\Property(property="video_url", type="string", nullable=true, example="https://youtu.be/k_pSc9aWyz0", description="YouTube video URL"),
 *             @OA\Property(property="webcam", type="string", nullable=true, example=null, description="Webcam embed code")
 *         )
 *     ),
 *     @OA\Property(
 *         property="features",
 *         type="object",
 *         description="Card icons (QA #45): beach type, user types and parking. family / adventurer / youth (party) = the beach has that user type; parking = infrastructure `parking` or a parking capacity other than none/private.",
 *         @OA\Property(
 *             property="beach_type",
 *             type="object",
 *             nullable=true,
 *             @OA\Property(property="code", type="string", example="sand"),
 *             @OA\Property(property="name", type="string", example="Sand", description="Translated via Accept-Language")
 *         ),
 *         @OA\Property(property="family", type="boolean", example=true),
 *         @OA\Property(property="adventurer", type="boolean", example=false),
 *         @OA\Property(property="youth", type="boolean", example=false, description="Party / youth beach"),
 *         @OA\Property(property="parking", type="boolean", example=false)
 *     ),
 *     @OA\Property(
 *         property="tags",
 *         type="array",
 *         description="Up to 4 descriptive tags for the beach (sporting activities, culinary offers, natural conditions, occupancy rate) translated based on Accept-Language header. Empty array if none available.",
 *         @OA\Items(type="string", example="Diving")
 *     ),
 *     @OA\Property(property="distance_km", type="number", format="float", nullable=true, description="Distance from reference point in kilometers (null for bounding-box queries)", example=2.45)
 * )
 */
class SpatialBeachSchema {}

