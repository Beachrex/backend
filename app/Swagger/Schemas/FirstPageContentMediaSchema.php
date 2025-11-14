<?php

namespace App\Swagger\Schemas;

/**
 * @OA\Schema(
 *     schema="FirstPageContentMedia",
 *     title="First Page Content Media",
 *     description="Media file for first page content",
 *     type="object",
 *     @OA\Property(
 *         property="id",
 *         type="integer",
 *         description="Media ID",
 *         example=42338
 *     ),
 *     @OA\Property(
 *         property="type",
 *         type="string",
 *         description="Media type code",
 *         example="image"
 *     ),
 *     @OA\Property(
 *         property="url",
 *         type="string",
 *         description="Full CDN URL to the media file",
 *         example="https://beachrex.fra1.digitaloceanspaces.com/public/img/first-page/hero-bg.jpg"
 *     ),
 *     @OA\Property(
 *         property="file_name",
 *         type="string",
 *         description="File name",
 *         example="hero-bg.jpg"
 *     ),
 *     @OA\Property(
 *         property="is_primary",
 *         type="boolean",
 *         description="Whether this is the primary media file",
 *         example=true
 *     ),
 *     @OA\Property(
 *         property="collection",
 *         type="string",
 *         nullable=true,
 *         description="Media collection code (e.g., icon, main_image)",
 *         example="icon"
 *     )
 * )
 */
class FirstPageContentMediaSchema
{
    // This class is used only for Swagger documentation
}

