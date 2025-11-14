<?php

namespace App\Swagger\Schemas;

/**
 * @OA\Schema(
 *     schema="FirstPageContentItem",
 *     title="First Page Content Item",
 *     description="Item within a repeater section",
 *     type="object",
 *     @OA\Property(
 *         property="id",
 *         type="integer",
 *         description="Item ID",
 *         example=1
 *     ),
 *     @OA\Property(
 *         property="order",
 *         type="integer",
 *         description="Display order within the section",
 *         example=1
 *     ),
 *     @OA\Property(
 *         property="title",
 *         type="string",
 *         nullable=true,
 *         description="Item title (localized based on Accept-Language header)",
 *         example="Gallery"
 *     ),
 *     @OA\Property(
 *         property="description",
 *         type="string",
 *         nullable=true,
 *         description="Item description (localized based on Accept-Language header)",
 *         example="Browse through beautiful beach photos"
 *     ),
 *     @OA\Property(
 *         property="media",
 *         type="array",
 *         description="Media files associated with this item",
 *         @OA\Items(ref="#/components/schemas/FirstPageContentMedia")
 *     )
 * )
 */
class FirstPageContentItemSchema
{
    // This class is used only for Swagger documentation
}

