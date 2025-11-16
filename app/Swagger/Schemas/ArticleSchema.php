<?php

namespace App\Swagger\Schemas;

/**
 * @OA\Schema(
 *     schema="Article",
 *     type="object",
 *     title="Article",
 *     description="Article model",
 *     @OA\Property(
 *         property="id",
 *         type="integer",
 *         description="Article ID",
 *         example=1
 *     ),
 *     @OA\Property(
 *         property="code",
 *         type="string",
 *         maxLength=100,
 *         description="Article code",
 *         example="krk-history"
 *     ),
 *     @OA\Property(
 *         property="category",
 *         type="string",
 *         nullable=true,
 *         description="Article category",
 *         example="History",
 *         enum={"History", "Culture", "Cuisine", "Sights", "Nature", "Activities"}
 *     ),
 *     @OA\Property(
 *         property="title",
 *         type="string",
 *         description="Article title (localized based on Accept-Language header)",
 *         example="History of Krk Island"
 *     ),
 *     @OA\Property(
 *         property="slug",
 *         type="string",
 *         description="Localized URL-friendly slug",
 *         example="history-of-krk-island"
 *     ),
 *     @OA\Property(
 *         property="excerpt",
 *         type="string",
 *         nullable=true,
 *         description="Short excerpt or summary of the article",
 *         example="Discover the rich history of Krk Island..."
 *     ),
 *     @OA\Property(
 *         property="feature_image",
 *         type="string",
 *         nullable=true,
 *         description="Feature image path",
 *         example="/storage/articles/krk-history.jpg"
 *     ),
 *     @OA\Property(
 *         property="content",
 *         type="string",
 *         nullable=true,
 *         description="Full article content (only included in single article view)",
 *         example="<p>The island of Krk has a long and fascinating history...</p>"
 *     )
 * )
 */
class ArticleSchema
{
    // This class is used only for Swagger documentation
}

