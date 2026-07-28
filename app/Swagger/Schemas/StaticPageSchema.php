<?php

namespace App\Swagger\Schemas;

/**
 * @OA\Schema(
 *     schema="StaticPage",
 *     title="Static Page",
 *     description="Full static page content (CMS-managed). Localized by Accept-Language with English fallback.",
 *     type="object",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="code", type="string", example="privacy-policy", description="Unique page code"),
 *     @OA\Property(property="title", type="string", example="Privacy Policy", description="Localized title"),
 *     @OA\Property(property="slug", type="string", example="privacy-policy", description="Localized slug"),
 *     @OA\Property(property="content", type="string", nullable=true, example="<p>Privacy policy content...</p>", description="HTML content from RichEditor"),
 *     @OA\Property(property="meta_title", type="string", nullable=true, example="Privacy Policy | Beachrex"),
 *     @OA\Property(property="meta_description", type="string", nullable=true, example="How Beachrex handles your data."),
 *     @OA\Property(property="locale", type="string", example="en", description="Locale actually used (may differ from request if fallback applied)"),
 *     @OA\Property(property="is_in_footer", type="boolean", example=true),
 *     @OA\Property(property="sort_order", type="integer", example=2)
 * )
 */
class StaticPageSchema {}
