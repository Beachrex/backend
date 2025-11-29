<?php

namespace App\Swagger\Schemas;

/**
 * @OA\Schema(
 *     schema="BeachDetailsSchema",
 *     title="Beach Details Schema",
 *     description="Comprehensive beach details for detail pages",
 *     type="object",
 *     @OA\Property(
 *         property="basic_info",
 *         type="object",
 *         description="Basic beach information",
 *         @OA\Property(property="id", type="integer", example=123),
 *         @OA\Property(property="name", type="string", example="Paradise Beach"),
 *         @OA\Property(property="slug", type="string", example="paradise-beach"),
 *         @OA\Property(property="description", type="string", nullable=true, example="Beautiful sandy beach"),
 *         @OA\Property(property="latitude", type="string", example="42.5996000"),
 *         @OA\Property(property="longitude", type="string", example="18.2302000"),
 *         @OA\Property(property="folder_name", type="string", nullable=true, example="paradise-beach-123"),
 *         @OA\Property(property="mobile_upload", type="boolean", example=false)
 *     ),
 *     @OA\Property(
 *         property="location",
 *         type="object",
 *         description="Location information",
 *         @OA\Property(
 *             property="continent",
 *             type="object",
 *             nullable=true,
 *             @OA\Property(property="code", type="string", example="EU"),
 *             @OA\Property(property="name", type="string", example="Europe"),
 *             @OA\Property(property="slug", type="string", nullable=true, example="europe")
 *         ),
 *         @OA\Property(
 *             property="country",
 *             type="object",
 *             nullable=true,
 *             @OA\Property(property="code", type="string", example="HR"),
 *             @OA\Property(property="name", type="string", example="Croatia"),
 *             @OA\Property(property="full_name", type="string", nullable=true, example="Republic of Croatia"),
 *             @OA\Property(property="slug", type="string", nullable=true, example="croatia")
 *         ),
 *         @OA\Property(
 *             property="region",
 *             type="object",
 *             nullable=true,
 *             @OA\Property(property="id", type="integer", example=1),
 *             @OA\Property(property="name", type="string", example="Dalmatia"),
 *             @OA\Property(property="slug", type="string", nullable=true, example="dalmatia")
 *         ),
 *         @OA\Property(
 *             property="city",
 *             type="object",
 *             nullable=true,
 *             @OA\Property(property="id", type="integer", example=1),
 *             @OA\Property(property="name", type="string", example="Split"),
 *             @OA\Property(property="slug", type="string", nullable=true, example="split")
 *         ),
 *         @OA\Property(
 *             property="island",
 *             type="object",
 *             nullable=true,
 *             @OA\Property(property="id", type="integer", example=1),
 *             @OA\Property(property="name", type="string", example="Hvar"),
 *             @OA\Property(property="slug", type="string", nullable=true, example="hvar")
 *         ),
 *         @OA\Property(
 *             property="river",
 *             type="object",
 *             nullable=true,
 *             @OA\Property(property="id", type="integer", example=1),
 *             @OA\Property(property="name", type="string", example="Danube")
 *         ),
 *         @OA\Property(
 *             property="lake",
 *             type="object",
 *             nullable=true,
 *             @OA\Property(property="id", type="integer", example=1),
 *             @OA\Property(property="name", type="string", example="Lake Bled")
 *         )
 *     ),
 *     @OA\Property(
 *         property="characteristics",
 *         type="object",
 *         description="Beach characteristics",
 *         @OA\Property(
 *             property="beach_type",
 *             type="object",
 *             nullable=true,
 *             @OA\Property(property="id", type="integer", example=1),
 *             @OA\Property(property="code", type="string", example="sand"),
 *             @OA\Property(property="name", type="string", example="Sand")
 *         ),
 *         @OA\Property(
 *             property="waterbody",
 *             type="object",
 *             nullable=true,
 *             @OA\Property(property="id", type="integer", example=1),
 *             @OA\Property(property="code", type="string", example="sea"),
 *             @OA\Property(property="name", type="string", example="Sea")
 *         ),
 *         @OA\Property(
 *             property="water_body_type",
 *             type="object",
 *             nullable=true,
 *             @OA\Property(property="id", type="integer", example=1),
 *             @OA\Property(property="code", type="string", example="adriatic"),
 *             @OA\Property(property="name", type="string", example="Adriatic Sea")
 *         ),
 *         @OA\Property(
 *             property="area_description",
 *             type="object",
 *             nullable=true,
 *             @OA\Property(property="id", type="integer", example=1),
 *             @OA\Property(property="code", type="string", example="urban"),
 *             @OA\Property(property="name", type="string", example="Urban")
 *         ),
 *         @OA\Property(
 *             property="location_types",
 *             type="array",
 *             @OA\Items(
 *                 type="object",
 *                 @OA\Property(property="id", type="integer", example=1),
 *                 @OA\Property(property="code", type="string", example="bay"),
 *                 @OA\Property(property="name", type="string", example="Bay")
 *             )
 *         ),
 *         @OA\Property(property="boat_only", type="boolean", example=false)
 *     ),
 *     @OA\Property(
 *         property="accessibility",
 *         type="object",
 *         description="Accessibility and parking information"
 *     ),
 *     @OA\Property(
 *         property="dimensions",
 *         type="object",
 *         description="Beach dimensions and measurements"
 *     ),
 *     @OA\Property(
 *         property="facilities",
 *         type="object",
 *         description="Facilities and amenities",
 *         @OA\Property(
 *             property="accommodation_around",
 *             type="array",
 *             @OA\Items(
 *                 type="object",
 *                 @OA\Property(property="id", type="integer"),
 *                 @OA\Property(property="code", type="string"),
 *                 @OA\Property(property="name", type="string")
 *             )
 *         ),
 *         @OA\Property(
 *             property="culinary_offers",
 *             type="array",
 *             @OA\Items(
 *                 type="object",
 *                 @OA\Property(property="id", type="integer"),
 *                 @OA\Property(property="code", type="string"),
 *                 @OA\Property(property="name", type="string")
 *             )
 *         ),
 *         @OA\Property(
 *             property="location_infrastructure",
 *             type="array",
 *             @OA\Items(
 *                 type="object",
 *                 @OA\Property(property="id", type="integer"),
 *                 @OA\Property(property="code", type="string"),
 *                 @OA\Property(property="name", type="string")
 *             )
 *         )
 *     ),
 *     @OA\Property(
 *         property="activities",
 *         type="object",
 *         description="Activities and user types",
 *         @OA\Property(
 *             property="sporting_activities",
 *             type="array",
 *             @OA\Items(
 *                 type="object",
 *                 @OA\Property(property="id", type="integer"),
 *                 @OA\Property(property="code", type="string"),
 *                 @OA\Property(property="name", type="string")
 *             )
 *         ),
 *         @OA\Property(
 *             property="user_types",
 *             type="array",
 *             @OA\Items(
 *                 type="object",
 *                 @OA\Property(property="id", type="integer"),
 *                 @OA\Property(property="code", type="string"),
 *                 @OA\Property(property="name", type="string")
 *             )
 *         )
 *     ),
 *     @OA\Property(
 *         property="natural_conditions",
 *         type="object",
 *         description="Natural conditions and underwater characteristics"
 *     ),
 *     @OA\Property(
 *         property="media",
 *         type="object",
 *         description="Media collections (galleries, videos, 360 spheres, etc.)",
 *         @OA\Property(
 *             property="gallery",
 *             type="array",
 *             description="Main beach photo gallery",
 *             @OA\Items(ref="#/components/schemas/MediaItem")
 *         ),
 *         @OA\Property(
 *             property="videos",
 *             type="array",
 *             description="Beach videos",
 *             @OA\Items(ref="#/components/schemas/MediaItem")
 *         ),
 *         @OA\Property(
 *             property="spheres_360",
 *             type="array",
 *             description="360-degree panoramic images and their virtual tours",
 *             @OA\Items(ref="#/components/schemas/MediaItem")
 *         ),
 *         @OA\Property(
 *             property="parking_images",
 *             type="array",
 *             description="Parking area photos",
 *             @OA\Items(ref="#/components/schemas/MediaItem")
 *         ),
 *         @OA\Property(
 *             property="spot_images",
 *             type="array",
 *             description="Specific beach spot photos",
 *             @OA\Items(ref="#/components/schemas/MediaItem")
 *         ),
 *         @OA\Property(
 *             property="infrastructure_images",
 *             type="array",
 *             description="Infrastructure and facilities photos",
 *             @OA\Items(ref="#/components/schemas/MediaItem")
 *         ),
 *         @OA\Property(
 *             property="info_images",
 *             type="array",
 *             description="Informational images and signage",
 *             @OA\Items(ref="#/components/schemas/MediaItem")
 *         )
 *     ),
 *     @OA\Property(
 *         property="embeds",
 *         type="object",
 *         description="Embedded content (videos, webcams, maps, widgets). Note: Real-time weather data is now available via separate endpoint /beaches/{identifier}/weather",
 *         @OA\Property(property="video_url", type="string", nullable=true, example="https://youtube.com/watch?v=..."),
 *         @OA\Property(property="webcam", type="string", nullable=true, example="<iframe>...</iframe>"),
 *         @OA\Property(property="map_url", type="string", nullable=true, example="https://maps.google.com/..."),
 *         @OA\Property(property="weather", type="string", nullable=true, example="<div>...</div>", description="Legacy weather widget HTML (deprecated - use /beaches/{identifier}/weather endpoint instead)"),
 *         @OA\Property(property="water_temperature", type="string", nullable=true, example="<div>...</div>", description="Legacy water temperature widget HTML (deprecated - use /beaches/{identifier}/weather endpoint instead)")
 *     ),
 *     @OA\Property(
 *         property="swimming_pool_features",
 *         type="object",
 *         description="Swimming pool features (if applicable)"
 *     ),
 *     @OA\Property(
 *         property="meta",
 *         type="object",
 *         description="Metadata",
 *         @OA\Property(property="status", type="string", nullable=true, example="Active"),
 *         @OA\Property(property="views", type="integer", example=1234),
 *         @OA\Property(
 *             property="created_by",
 *             type="object",
 *             nullable=true,
 *             @OA\Property(property="id", type="integer", example=1),
 *             @OA\Property(property="name", type="string", example="John Doe")
 *         ),
 *         @OA\Property(property="created_at", type="string", format="date-time", example="2024-01-15T10:30:00Z"),
 *         @OA\Property(property="updated_at", type="string", format="date-time", example="2024-01-20T14:45:00Z")
 *     ),
 *     @OA\Property(
 *         property="navigation",
 *         type="object",
 *         description="Previous and next beaches based on region and ID order. Provides consistent bidirectional navigation within the same region.",
 *         @OA\Property(
 *             property="previous",
 *             type="object",
 *             nullable=true,
 *             description="Beach with highest ID lower than current beach in same region (null if first beach in region)",
 *             @OA\Property(property="id", type="integer", example=122),
 *             @OA\Property(property="name", type="string", example="Nearby Beach 1"),
 *             @OA\Property(property="slug", type="string", example="nearby-beach-1"),
 *             @OA\Property(property="latitude", type="string", example="42.5900000"),
 *             @OA\Property(property="longitude", type="string", example="18.2200000"),
 *             @OA\Property(property="distance_km", type="number", format="float", example=1.25, description="Distance in kilometers"),
 *             @OA\Property(property="main_image", type="string", nullable=true, example="img/beaches/beach-folder/image.jpg", description="Main beach image path (relative, without CDN/domain prefix)."),
 *             @OA\Property(
 *                 property="location",
 *                 type="object",
 *                 description="Location slugs for building beach URL",
 *                 @OA\Property(property="continent_slug", type="string", nullable=true, example="europe"),
 *                 @OA\Property(property="country_slug", type="string", nullable=true, example="croatia"),
 *                 @OA\Property(property="region_slug", type="string", nullable=true, example="kvarner"),
 *                 @OA\Property(property="island_slug", type="string", nullable=true, example="island-cres"),
 *                 @OA\Property(property="city_slug", type="string", nullable=true, example="cres")
 *             )
 *         ),
 *         @OA\Property(
 *             property="next",
 *             type="object",
 *             nullable=true,
 *             description="Beach with lowest ID higher than current beach in same region (null if last beach in region)",
 *             @OA\Property(property="id", type="integer", example=124),
 *             @OA\Property(property="name", type="string", example="Nearby Beach 2"),
 *             @OA\Property(property="slug", type="string", example="nearby-beach-2"),
 *             @OA\Property(property="latitude", type="string", example="42.6000000"),
 *             @OA\Property(property="longitude", type="string", example="18.2400000"),
 *             @OA\Property(property="distance_km", type="number", format="float", example=2.50, description="Distance in kilometers"),
 *             @OA\Property(property="main_image", type="string", nullable=true, example="img/beaches/beach-folder/image.jpg", description="Main beach image path (relative, without CDN/domain prefix)."),
 *             @OA\Property(
 *                 property="location",
 *                 type="object",
 *                 description="Location slugs for building beach URL",
 *                 @OA\Property(property="continent_slug", type="string", nullable=true, example="europe"),
 *                 @OA\Property(property="country_slug", type="string", nullable=true, example="croatia"),
 *                 @OA\Property(property="region_slug", type="string", nullable=true, example="kvarner"),
 *                 @OA\Property(property="island_slug", type="string", nullable=true, example="island-cres"),
 *                 @OA\Property(property="city_slug", type="string", nullable=true, example="cres")
 *             )
 *         )
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="MediaItem",
 *     title="Media Item",
 *     description="Individual media item (image, video, 360 sphere, etc.)",
 *     type="object",
 *     @OA\Property(property="id", type="integer", example=456),
 *     @OA\Property(property="file_name", type="string", example="hr-6-o-hvar-sl3.jpg"),
 *     @OA\Property(property="url", type="string", example="img/beaches/hr-6-o-hvar/hr-6-o-hvar-sl3.jpg", description="Relative media path without CDN/domain prefix. Frontend should prepend its own base URL or CDN."),
 *     @OA\Property(property="thumbnail_url", type="string", nullable=true, example="img/beaches/hr-6-o-hvar/hr-6-o-hvar-sl3.jpg", description="Relative thumbnail path (same as url for now)."),
 *     @OA\Property(property="tour_url", type="string", nullable=true, example="img/beaches/hr-6-o-hvar/vtour/tour.html", description="Virtual tour HTML path for 360 spheres (present only for spheres_360 collection)."),
 *     @OA\Property(property="mime_type", type="string", example="image/jpeg"),
 *     @OA\Property(property="width", type="integer", nullable=true, example=1920),
 *     @OA\Property(property="height", type="integer", nullable=true, example=1080),
 *     @OA\Property(property="duration", type="integer", nullable=true, example=120, description="Duration in seconds for videos"),
 *     @OA\Property(property="is_primary", type="boolean", example=true),
 *     @OA\Property(property="order", type="integer", example=1)
 * )
 */
class BeachDetailsSchema {}

