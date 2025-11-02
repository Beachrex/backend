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

        // Get country name translation
        $countryName = null;
        if ($this->country) {
            $countryTranslation = $this->country->translations->firstWhere('locale', $locale);
            $countryName = $countryTranslation->name ?? null;
        }

        // Get primary image from beach gallery (collection_id = 1)
        $mainImage = null;
        if ($this->media && $this->media->isNotEmpty()) {
            $primaryMedia = $this->media->first();
            if ($primaryMedia && $primaryMedia->file_path) {
                $mainImage = '/' . ltrim($primaryMedia->file_path, '/');
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
            'continent_code' => $this->continent_code,
            'main_image' => $mainImage,
        ];
    }
}
