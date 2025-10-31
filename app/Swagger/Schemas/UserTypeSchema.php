<?php

namespace App\Swagger\Schemas;

/**
 * @OA\Schema(
 *     schema="UserType",
 *     title="User Type",
 *     description="User type model (adventurer, family, youth)",
 *     type="object",
 *     @OA\Property(
 *         property="id",
 *         type="integer",
 *         description="User type ID",
 *         example=1
 *     ),
 *     @OA\Property(
 *         property="code",
 *         type="string",
 *         description="User type code",
 *         example="adventurer"
 *     ),
 *     @OA\Property(
 *         property="name",
 *         type="string",
 *         description="User type name (localized based on Accept-Language header)",
 *         example="Adventurer"
 *     ),
 *     @OA\Property(
 *         property="slug",
 *         type="string",
 *         description="URL-friendly slug (localized based on Accept-Language header)",
 *         example="adventurer"
 *     ),
 *     @OA\Property(
 *         property="beaches_count",
 *         type="integer",
 *         description="Number of beaches suitable for this user type",
 *         example=320
 *     ),
 *     @OA\Property(
 *         property="translations",
 *         type="array",
 *         description="All available translations for this user type",
 *         @OA\Items(ref="#/components/schemas/UserTypeTranslation")
 *     )
 * )
 */
class UserTypeSchema
{
    // This class is used only for Swagger documentation
}

/**
 * @OA\Schema(
 *     schema="UserTypeTranslation",
 *     title="User Type Translation",
 *     description="Translation for a user type",
 *     type="object",
 *     @OA\Property(
 *         property="locale",
 *         type="string",
 *         description="Language code (en, sl, de, hr, fr, es, it, ua, ru, pl, cs, hu, bg, sk)",
 *         example="en"
 *     ),
 *     @OA\Property(
 *         property="name",
 *         type="string",
 *         description="Translated user type name",
 *         example="Adventurer"
 *     ),
 *     @OA\Property(
 *         property="slug",
 *         type="string",
 *         description="URL-friendly slug",
 *         example="adventurer"
 *     )
 * )
 */
class UserTypeTranslationSchema
{
    // This class is used only for Swagger documentation
}

