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
 *     @OA\Property(property="slug", type="string", example="bucanj", description="URL-friendly slug (with English fallback if translation missing)"),
 *     @OA\Property(property="latitude", type="string", example="42.5996000", description="Beach latitude"),
 *     @OA\Property(property="longitude", type="string", example="18.2302000", description="Beach longitude"),
 *     @OA\Property(property="country_code", type="string", example="HR", description="ISO country code"),
 *     @OA\Property(property="country_name", type="string", example="Croatia", description="Country name (translated based on Accept-Language header)"),
 *     @OA\Property(property="country_slug", type="string", nullable=true, example="croatia", description="Country slug (translated based on Accept-Language header)"),
 *     @OA\Property(property="continent_code", type="string", example="EU", description="Continent code"),
 *     @OA\Property(property="continent_slug", type="string", nullable=true, example="europe", description="Continent slug (translated based on Accept-Language header)"),
 *     @OA\Property(property="region_slug", type="string", nullable=true, example="dalmatia", description="Region slug (translated based on Accept-Language header)"),
 *     @OA\Property(property="island_slug", type="string", nullable=true, example="island-hvar", description="Island slug (translated based on Accept-Language header)"),
 *     @OA\Property(property="city_name", type="string", nullable=true, example="Split", description="City name (translated based on Accept-Language header, with English fallback)"),
 *     @OA\Property(property="city_slug", type="string", nullable=true, example="split", description="City slug (translated based on Accept-Language header)"),
 *     @OA\Property(property="main_image", type="string", nullable=true, example="img/beaches/hr-779-k-jdalmacija/hr-779-k-jdalmacija-sl4.jpg", description="Primary beach image path (relative, without CDN/domain prefix). Frontend constructs Imagor URLs by prepending IMAGOR_ENDPOINT + transformation params (e.g., 'unsafe/fit-in/400x300/filters:quality(85)/') + main_image."),
 *     @OA\Property(
 *         property="media",
 *         type="object",
 *         description="Media collections for the beach. Only non-empty collections are included. Only essential fields to reduce response size.",
 *         @OA\Property(
 *             property="gallery",
 *             type="array",
 *             description="Beach gallery images (only if beach has gallery images)",
 *             @OA\Items(
 *                 type="object",
 *                 @OA\Property(property="url", type="string", example="img/beaches/hr-69-o-pag/hr-69-o-pag-sl1.jpg", description="Relative path to image file"),
 *                 @OA\Property(property="is_primary", type="boolean", example=true, description="Whether this is the primary image"),
 *                 @OA\Property(property="order", type="integer", example=1, description="Display order")
 *             )
 *         ),
 *         @OA\Property(
 *             property="videos",
 *             type="array",
 *             description="Beach videos (only if beach has videos)",
 *             @OA\Items(
 *                 type="object",
 *                 @OA\Property(property="url", type="string", example="img/beaches/hr-69-o-pag/beach-video.mp4", description="Relative path to video file"),
 *                 @OA\Property(property="is_primary", type="boolean", example=false, description="Whether this is the primary video"),
 *                 @OA\Property(property="order", type="integer", example=1, description="Display order")
 *             )
 *         ),
 *         @OA\Property(
 *             property="spheres_360",
 *             type="array",
 *             description="360-degree sphere tours (only if beach has 360° spheres)",
 *             @OA\Items(
 *                 type="object",
 *                 @OA\Property(property="tour_url", type="string", example="img/beaches/hr-69-o-pag/vtour/tour.html", description="URL to 360° virtual tour")
 *             )
 *         ),
 *         @OA\Property(
 *             property="embeds",
 *             type="object",
 *             description="External embeds for the beach",
 *             @OA\Property(property="video_url", type="string", nullable=true, example="https://youtu.be/k_pSc9aWyz0", description="YouTube video URL"),
 *             @OA\Property(property="webcam", type="string", nullable=true, example="<iframe src='...'></iframe>", description="Legacy pasted webcam embed (unverified)"),
 *             @OA\Property(property="windy_webcam_id", type="integer", nullable=true, example=1616668973, description="Primary verified Windy webcam id (TASK-006), or null")
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
 *     )
 * )
 */
class BeachSchema {}
