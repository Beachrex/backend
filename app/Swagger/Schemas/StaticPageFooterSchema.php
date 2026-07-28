<?php

namespace App\Swagger\Schemas;

/**
 * @OA\Schema(
 *     schema="StaticPageFooter",
 *     title="Static Page Footer Item",
 *     description="Lightweight static page entry for footer rendering. Localized by Accept-Language with English fallback.",
 *     type="object",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="code", type="string", example="privacy-policy"),
 *     @OA\Property(property="title", type="string", example="Privacy Policy", description="Localized title for footer link"),
 *     @OA\Property(property="slug", type="string", example="privacy-policy", description="Localized slug for link href"),
 *     @OA\Property(property="sort_order", type="integer", example=2)
 * )
 */
class StaticPageFooterSchema {}
