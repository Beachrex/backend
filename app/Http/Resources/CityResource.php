<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CityResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $locale = app()->getLocale();

        // Get the translation for the current locale, fallback to English
        $translation = $this->translations
            ->firstWhere('locale', $locale)
            ?? $this->translations->firstWhere('locale', 'en');

        // Get location slugs for URL building
        $continentSlug = null;
        $countrySlug = null;
        $regionSlug = null;
        $islandSlug = null;

        // Get continent slug from country relationship
        if ($this->relationLoaded('country') && $this->country) {
            if ($this->country->relationLoaded('continent') && $this->country->continent) {
                $continentTranslation = $this->country->continent->translations
                    ->firstWhere('locale', $locale)
                    ?? $this->country->continent->translations->firstWhere('locale', 'en');
                $continentSlug = $continentTranslation?->slug;
            }

            // Get country slug
            $countryTranslation = $this->country->translations
                ->firstWhere('locale', $locale)
                ?? $this->country->translations->firstWhere('locale', 'en');
            $countrySlug = $countryTranslation?->slug;
        }

        // Get region slug
        if ($this->relationLoaded('region') && $this->region) {
            $regionTranslation = $this->region->translations
                ->firstWhere('locale', $locale)
                ?? $this->region->translations->firstWhere('locale', 'en');
            $regionSlug = $regionTranslation?->slug;
        }

        // Get island slug
        if ($this->relationLoaded('island') && $this->island) {
            $islandTranslation = $this->island->translations
                ->firstWhere('locale', $locale)
                ?? $this->island->translations->firstWhere('locale', 'en');
            $islandSlug = $islandTranslation?->slug;
        }

        $data = [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $translation?->name ?? 'Unknown',
            'slug' => $translation?->slug ?? 'unknown',
            'country_code' => $this->country_code,
            'region_id' => $this->region_id,
            'island_id' => $this->island_id,
            'main_image' => $this->main_image,
        ];

        // Add location slugs for URL building (always included when relationships are loaded)
        if ($continentSlug !== null) {
            $data['location'] = [
                'continent_slug' => $continentSlug,
                'country_slug' => $countrySlug,
                'region_slug' => $regionSlug,
                'island_slug' => $islandSlug,
            ];
        }

        // Add beaches_count if available
        if ($this->relationLoaded('beaches') || isset($this->beaches_count)) {
            $data['beaches_count'] = $this->beaches_count ?? $this->beaches->count();
        }

        // Add accommodation_count (hardcoded to 0 until booking integration)
        $data['accommodation_count'] = 0;

        // Add country only if loaded (for show endpoint)
        if ($this->relationLoaded('country')) {
            $data['country'] = new CountryResource($this->country);
        }

        // Add region only if loaded and not null (for show endpoint)
        if ($this->relationLoaded('region') && $this->region) {
            $data['region'] = new RegionResource($this->region);
        }

        // Add island only if loaded and not null (for show endpoint)
        if ($this->relationLoaded('island') && $this->island) {
            $data['island'] = new IslandResource($this->island);
        }

        // Add badges only if loaded (for show endpoint)
        if ($this->relationLoaded('badges')) {
            $data['badges'] = BadgeResource::collection($this->badges);
        }

        // Add articles only if loaded (for show endpoint)
        if ($this->relationLoaded('articles')) {
            $data['articles'] = ArticleResource::collection($this->articles);
        }

        return $data;
    }
}

