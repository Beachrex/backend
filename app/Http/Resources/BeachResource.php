<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class BeachResource extends JsonResource
{
    public function toArray($request)
    {
        $locale = app()->getLocale();

        // Get translation for current locale, fallback to English
        $translation = $this->translations->firstWhere('locale', $locale);
        $translationEn = $this->translations->firstWhere('locale', 'en');

        // Get country name and slug
        $countryName = null;
        $countrySlug = null;
        if ($this->country) {
            $countryTranslation = $this->country->translations->firstWhere('locale', $locale);
            $countryTranslationEn = $this->country->translations->firstWhere('locale', 'en');
            $countryName = $countryTranslation?->name ?? null;
            $countrySlug = $countryTranslation?->slug ?? $countryTranslationEn?->slug ?? null;
        }

        // Get continent slug
        $continentSlug = null;
        if ($this->continent) {
            $continentTranslation = $this->continent->translations->firstWhere('locale', $locale);
            $continentTranslationEn = $this->continent->translations->firstWhere('locale', 'en');
            $continentSlug = $continentTranslation?->slug ?? $continentTranslationEn?->slug ?? null;
        }

        // Get region slug
        $regionSlug = null;
        if ($this->region) {
            $regionTranslation = $this->region->translations->firstWhere('locale', $locale);
            $regionTranslationEn = $this->region->translations->firstWhere('locale', 'en');
            $regionSlug = $regionTranslation?->slug ?? $regionTranslationEn?->slug ?? null;
        }

        // Get city slug
        $citySlug = null;
        if ($this->city) {
            $cityTranslation = $this->city->translations->firstWhere('locale', $locale);
            $cityTranslationEn = $this->city->translations->firstWhere('locale', 'en');
            $citySlug = $cityTranslation?->slug ?? $cityTranslationEn?->slug ?? null;
        }

        // Get island slug
        $islandSlug = null;
        if ($this->island) {
            $islandTranslation = $this->island->translations->firstWhere('locale', $locale);
            $islandTranslationEn = $this->island->translations->firstWhere('locale', 'en');
            $islandSlug = $islandTranslation?->slug ?? $islandTranslationEn?->slug ?? null;
        }

        // Get primary image from beach gallery (collection_id = 1)
        $mainImage = null;
        $mainImageImagor = null;
        if ($this->media && $this->media->isNotEmpty()) {
            $primaryMedia = $this->media->first();
            if ($primaryMedia && $primaryMedia->file_path) {
                $mainImage = $primaryMedia->file_path;
                // Generate small Imagor URL for list view
                $mainImageImagor = $primaryMedia->imagorSmall();
            }
        }

        return [
            'id' => $this->id,
            'name' => $translation->name ?? $translationEn->name ?? $this->name,
            'slug' => $translation->slug ?? $translationEn->slug ?? null,
            'description' => $translation->description ?? $translationEn->description ?? null,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'country_code' => $this->country_code,
            'country_name' => $countryName,
            'country_slug' => $countrySlug,
            'continent_code' => $this->continent_code,
            'continent_slug' => $continentSlug,
            'region_slug' => $regionSlug,
            'island_slug' => $islandSlug,
            'city_slug' => $citySlug,
            'main_image' => $mainImage,
            'main_image_imagor' => $mainImageImagor,
        ];
    }
}
