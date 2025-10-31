<?php

namespace App\Swagger\Schemas;

/**
 * @OA\Schema(
 *     schema="CulinaryOffer",
 *     title="Culinary Offer",
 *     description="Culinary offer model (restaurant, bistro, pizzeria, etc.)",
 *     type="object",
 *     @OA\Property(
 *         property="id",
 *         type="integer",
 *         description="Culinary offer ID",
 *         example=2
 *     ),
 *     @OA\Property(
 *         property="code",
 *         type="string",
 *         description="Culinary offer code",
 *         example="restaurant"
 *     ),
 *     @OA\Property(
 *         property="name",
 *         type="string",
 *         description="Culinary offer name (localized based on Accept-Language header)",
 *         example="Restaurant"
 *     ),
 *     @OA\Property(
 *         property="slug",
 *         type="string",
 *         description="URL-friendly slug (localized based on Accept-Language header)",
 *         example="restaurant"
 *     ),
 *     @OA\Property(
 *         property="beaches_count",
 *         type="integer",
 *         description="Number of beaches with this culinary offer",
 *         example=250
 *     ),
 *     @OA\Property(
 *         property="translations",
 *         type="array",
 *         description="All available translations for this culinary offer",
 *         @OA\Items(ref="#/components/schemas/CulinaryOfferTranslation")
 *     )
 * )
 */
class CulinaryOfferSchema
{
    // This class is used only for Swagger documentation
}

/**
 * @OA\Schema(
 *     schema="CulinaryOfferTranslation",
 *     title="Culinary Offer Translation",
 *     description="Translation for a culinary offer",
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
 *         description="Translated culinary offer name",
 *         example="Restaurant"
 *     ),
 *     @OA\Property(
 *         property="slug",
 *         type="string",
 *         description="URL-friendly slug",
 *         example="restaurant"
 *     )
 * )
 */
class CulinaryOfferTranslationSchema
{
    // This class is used only for Swagger documentation
}

