<?php

namespace App\Swagger\Schemas;

/**
 * @OA\Schema(
 *     schema="Accessibility",
 *     title="Accessibility",
 *     description="Accessibility option model (easy, medium, difficult)",
 *     type="object",
 *     @OA\Property(
 *         property="id",
 *         type="integer",
 *         description="Accessibility ID",
 *         example=1
 *     ),
 *     @OA\Property(
 *         property="code",
 *         type="string",
 *         description="Accessibility code",
 *         example="l_easy"
 *     ),
 *     @OA\Property(
 *         property="name",
 *         type="string",
 *         description="Accessibility name (localized based on Accept-Language header)",
 *         example="Easy"
 *     ),
 *     @OA\Property(
 *         property="slug",
 *         type="string",
 *         description="URL-friendly slug (localized based on Accept-Language header)",
 *         example="easy"
 *     ),
 *     @OA\Property(
 *         property="beaches_count",
 *         type="integer",
 *         description="Number of beaches with this accessibility",
 *         example=42
 *     ),
 *     @OA\Property(
 *         property="translations",
 *         type="array",
 *         description="All available translations for this accessibility",
 *         @OA\Items(ref="#/components/schemas/AccessibilityTranslation")
 *     )
 * )
 */
class AccessibilitySchema
{
    // This class is used only for Swagger documentation
}

/**
 * @OA\Schema(
 *     schema="AccessibilityTranslation",
 *     title="Accessibility Translation",
 *     description="Translation for an accessibility option",
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
 *         description="Translated accessibility name",
 *         example="Easy"
 *     ),
 *     @OA\Property(
 *         property="slug",
 *         type="string",
 *         description="URL-friendly slug",
 *         example="easy"
 *     )
 * )
 */
class AccessibilityTranslationSchema
{
    // This class is used only for Swagger documentation
}

