<?php

namespace App\Swagger\Schemas;

/**
 * @OA\Schema(
 *     schema="BeachFooter",
 *     title="Beach Footer Item",
 *     description="Lightweight beach entry for the footer 'Most viewed beaches' column. Localized by Accept-Language with English fallback. Includes only the slugs needed to build the beach detail URL.",
 *     type="object",
 *     @OA\Property(property="id", type="integer", example=793),
 *     @OA\Property(property="name", type="string", example="Beach Bucanj", description="Localized beach name (with English fallback)"),
 *     @OA\Property(property="slug", type="string", example="beach-bucanj", description="Localized beach slug (with English fallback)"),
 *     @OA\Property(property="continent_slug", type="string", nullable=true, example="europe", description="Localized continent slug"),
 *     @OA\Property(property="country_slug", type="string", nullable=true, example="croatia", description="Localized country slug"),
 *     @OA\Property(property="region_slug", type="string", nullable=true, example="dalmatia", description="Localized region slug"),
 *     @OA\Property(property="island_slug", type="string", nullable=true, example="island-hvar", description="Localized island slug"),
 *     @OA\Property(property="city_slug", type="string", nullable=true, example="split", description="Localized city slug")
 * )
 */
class BeachFooterSchema {}
