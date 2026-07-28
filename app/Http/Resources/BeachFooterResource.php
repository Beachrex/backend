<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Lightweight beach resource for the footer "Most viewed beaches" column.
 *
 * Intentionally minimal: only the localized name + the location slugs needed
 * to build the beach detail URL on the frontend (see buildBeachUrlFromSchema).
 * Omits media, tags, coordinates, etc. to keep the footer payload tiny.
 */
class BeachFooterResource extends JsonResource
{
    public function toArray($request): array
    {
        $locale = app()->getLocale();

        // Beach name + slug (locale fallback to English)
        $translation = $this->translations->firstWhere('locale', $locale);
        $translationEn = $this->translations->firstWhere('locale', 'en');

        // Continent slug (required for URL)
        $continentSlug = null;
        if ($this->continent) {
            $continentSlug = $this->continent->translations->firstWhere('locale', $locale)?->slug
                ?? $this->continent->translations->firstWhere('locale', 'en')?->slug;
        }

        // Country slug (required for URL)
        $countrySlug = null;
        if ($this->country) {
            $countrySlug = $this->country->translations->firstWhere('locale', $locale)?->slug
                ?? $this->country->translations->firstWhere('locale', 'en')?->slug;
        }

        // Region slug (optional)
        $regionSlug = null;
        if ($this->region) {
            $regionSlug = $this->region->translations->firstWhere('locale', $locale)?->slug
                ?? $this->region->translations->firstWhere('locale', 'en')?->slug;
        }

        // Island slug (optional)
        $islandSlug = null;
        if ($this->island) {
            $islandSlug = $this->island->translations->firstWhere('locale', $locale)?->slug
                ?? $this->island->translations->firstWhere('locale', 'en')?->slug;
        }

        // City slug (optional)
        $citySlug = null;
        if ($this->city) {
            $citySlug = $this->city->translations->firstWhere('locale', $locale)?->slug
                ?? $this->city->translations->firstWhere('locale', 'en')?->slug;
        }

        return [
            'id' => $this->id,
            'name' => $translation?->name ?? $translationEn?->name ?? $this->name,
            'slug' => $translation?->slug ?? $translationEn?->slug ?? null,
            'continent_slug' => $continentSlug,
            'country_slug' => $countrySlug,
            'region_slug' => $regionSlug,
            'island_slug' => $islandSlug,
            'city_slug' => $citySlug,
        ];
    }
}
