<?php

namespace App\Swagger\Schemas;

/**
 * @OA\Schema(
 *     schema="RoadToParkingType",
 *     title="Road to Parking Type",
 *     description="Road to parking type model (asphalt, concrete path, dirt, etc.)",
 *     type="object",
 *     @OA\Property(
 *         property="id",
 *         type="integer",
 *         description="Road to parking type ID",
 *         example=1
 *     ),
 *     @OA\Property(
 *         property="code",
 *         type="string",
 *         description="Road to parking type code",
 *         example="asphalt"
 *     ),
 *     @OA\Property(
 *         property="name",
 *         type="string",
 *         description="Road to parking type name (localized based on Accept-Language header)",
 *         example="Asphalt"
 *     ),
 *     @OA\Property(
 *         property="slug",
 *         type="string",
 *         description="URL-friendly slug (localized based on Accept-Language header)",
 *         example="asphalt"
 *     ),
 *     @OA\Property(
 *         property="show_filter",
 *         type="boolean",
 *         description="Whether this type should be shown in filter options",
 *         example=true
 *     ),
 *     @OA\Property(
 *         property="beaches_count",
 *         type="integer",
 *         description="Number of beaches with this road to parking type",
 *         example=58
 *     ),
 *     @OA\Property(
 *         property="translations",
 *         type="array",
 *         description="All available translations for this road to parking type",
 *         @OA\Items(ref="#/components/schemas/RoadToParkingTypeTranslation")
 *     )
 * )
 */
class RoadToParkingTypeSchema
{
    // This class is used only for Swagger documentation
}

/**
 * @OA\Schema(
 *     schema="RoadToParkingTypeTranslation",
 *     title="Road to Parking Type Translation",
 *     description="Translation for a road to parking type",
 *     type="object",
 *     @OA\Property(
 *         property="locale",
 *         type="string",
 *         description="Language code (en, sl, de, hr, fr, es, it, ua, ru)",
 *         example="en"
 *     ),
 *     @OA\Property(
 *         property="name",
 *         type="string",
 *         description="Translated road to parking type name",
 *         example="Asphalt"
 *     ),
 *     @OA\Property(
 *         property="slug",
 *         type="string",
 *         description="URL-friendly slug",
 *         example="asphalt"
 *     )
 * )
 */
class RoadToParkingTypeTranslationSchema
{
    // This class is used only for Swagger documentation
}

