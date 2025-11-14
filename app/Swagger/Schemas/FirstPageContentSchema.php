<?php

namespace App\Swagger\Schemas;

/**
 * @OA\Schema(
 *     schema="FirstPageContent",
 *     title="First Page Content",
 *     description="First page/homepage content section model",
 *     type="object",
 *     @OA\Property(
 *         property="id",
 *         type="integer",
 *         description="Content section ID",
 *         example=1
 *     ),
 *     @OA\Property(
 *         property="code",
 *         type="string",
 *         description="Unique section code identifier",
 *         example="hero_banner"
 *     ),
 *     @OA\Property(
 *         property="section_type",
 *         type="string",
 *         description="Type of content section",
 *         enum={"banner", "text_image", "repeater", "text_only"},
 *         example="banner"
 *     ),
 *     @OA\Property(
 *         property="order",
 *         type="integer",
 *         description="Display order on the page",
 *         example=1
 *     ),
 *     @OA\Property(
 *         property="title",
 *         type="string",
 *         nullable=true,
 *         description="Section title (localized based on Accept-Language header)",
 *         example="All worries are smaller on the beach!"
 *     ),
 *     @OA\Property(
 *         property="description",
 *         type="string",
 *         nullable=true,
 *         description="Section description (localized based on Accept-Language header)",
 *         example="Discover the most beautiful beaches around the world"
 *     ),
 *     @OA\Property(
 *         property="config",
 *         type="object",
 *         nullable=true,
 *         description="Additional configuration data (JSON)",
 *         example=null
 *     ),
 *     @OA\Property(
 *         property="media",
 *         type="array",
 *         description="Media files associated with this section",
 *         @OA\Items(ref="#/components/schemas/FirstPageContentMedia")
 *     ),
 *     @OA\Property(
 *         property="items",
 *         type="array",
 *         nullable=true,
 *         description="Items for repeater sections (null for non-repeater sections)",
 *         @OA\Items(ref="#/components/schemas/FirstPageContentItem")
 *     )
 * )
 */
class FirstPageContentSchema
{
    // This class is used only for Swagger documentation
}

