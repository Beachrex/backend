<?php

namespace App\Swagger\Schemas;

/**
 * @OA\Schema(
 *     schema="Media",
 *     type="object",
 *     title="Media",
 *     description="Media file (image, video, 360 sphere, etc.)",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(
 *         property="media_type",
 *         type="object",
 *         @OA\Property(property="id", type="integer", example=1),
 *         @OA\Property(property="code", type="string", example="image"),
 *         @OA\Property(property="name", type="string", example="Image")
 *     ),
 *     @OA\Property(
 *         property="media_collection",
 *         type="object",
 *         nullable=true,
 *         @OA\Property(property="id", type="integer", example=1),
 *         @OA\Property(property="code", type="string", example="beach_gallery"),
 *         @OA\Property(property="name", type="string", example="Beach Gallery")
 *     ),
 *     @OA\Property(property="file_name", type="string", example="hr-6-o-hvar-sl3.jpg"),
 *     @OA\Property(property="url", type="string", example="img/beaches/hr-6-o-hvar/hr-6-o-hvar-sl3.jpg", description="Relative media path without CDN/domain prefix. Frontend should prepend its own base URL or CDN."),
 *     @OA\Property(property="thumbnail_url", type="string", nullable=true, example="img/beaches/hr-6-o-hvar/hr-6-o-hvar-sl3.jpg", description="Relative thumbnail path (same as url for now)."),
 *     @OA\Property(property="mime_type", type="string", example="image/jpeg"),
 *     @OA\Property(property="size", type="integer", example=2048576, description="File size in bytes"),
 *     @OA\Property(property="human_readable_size", type="string", example="2 MB"),
 *     @OA\Property(property="width", type="integer", nullable=true, example=1920),
 *     @OA\Property(property="height", type="integer", nullable=true, example=1080),
 *     @OA\Property(property="duration", type="integer", nullable=true, example=120, description="Duration in seconds for videos"),
 *     @OA\Property(property="is_primary", type="boolean", example=false),
 *     @OA\Property(property="order", type="integer", example=0),
 *     @OA\Property(property="alt_text", type="string", nullable=true, example="Beautiful sunset at the beach"),
 *     @OA\Property(property="caption", type="string", nullable=true, example="Sunset view from the main beach area"),
 *     @OA\Property(
 *         property="uploaded_by",
 *         type="object",
 *         nullable=true,
 *         @OA\Property(property="id", type="integer", example=1),
 *         @OA\Property(property="name", type="string", example="John Doe")
 *     ),
 *     @OA\Property(property="created_at", type="string", format="date-time", example="2025-11-01T20:00:00.000000Z"),
 *     @OA\Property(property="updated_at", type="string", format="date-time", example="2025-11-01T20:00:00.000000Z")
 * )
 */
class MediaSchema
{
    // This class is used only for Swagger documentation
}

