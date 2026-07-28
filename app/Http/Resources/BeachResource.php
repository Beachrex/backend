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

        // Get city name and slug
        $cityName = null;
        $citySlug = null;
        if ($this->city) {
            $cityTranslation = $this->city->translations->firstWhere('locale', $locale);
            $cityTranslationEn = $this->city->translations->firstWhere('locale', 'en');
            $cityName = $cityTranslation?->name ?? $cityTranslationEn?->name ?? null;
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
        if ($this->media && $this->media->isNotEmpty()) {
            $primaryMedia = $this->media->first();
            if ($primaryMedia && $primaryMedia->file_path) {
                $mainImage = $primaryMedia->file_path;
            }
        }

        // Group media by collection
        $mediaByCollection = $this->media->groupBy('mediaCollection.code');

        // Format gallery images - only essential fields, sorted by is_primary DESC, then order ASC
        $gallery = $mediaByCollection->get('beach_gallery', collect())
            ->sortBy([
                fn($a, $b) => $b->is_primary <=> $a->is_primary,  // Primary first (DESC)
                fn($a, $b) => $a->order <=> $b->order,             // Then by order (ASC)
            ])
            ->map(function ($media) {
                return [
                    'url' => $media->url,
                    'is_primary' => $media->is_primary,
                    'order' => $media->order,
                ];
            })->values()->toArray();

        // Format videos - only essential fields, sorted by is_primary DESC, then order ASC
        $videos = $mediaByCollection->get('beach_videos', collect())
            ->sortBy([
                fn($a, $b) => $b->is_primary <=> $a->is_primary,  // Primary first (DESC)
                fn($a, $b) => $a->order <=> $b->order,             // Then by order (ASC)
            ])
            ->map(function ($media) {
                return [
                    'url' => $media->url,
                    'is_primary' => $media->is_primary,
                    'order' => $media->order,
                ];
            })->values()->toArray();

        // Format 360 spheres - only tour_url needed
        $spheres360Collection = $mediaByCollection->get('beach_360_sphere', collect());
        $vtourUrl = ($this->folder_name && $spheres360Collection->isNotEmpty())
            ? "img/beaches/{$this->folder_name}/vtour/tour.html"
            : null;

        $spheres360 = [];
        if ($vtourUrl) {
            $spheres360 = $spheres360Collection->map(function ($media) use ($vtourUrl) {
                return [
                    'tour_url' => $vtourUrl,
                ];
            })->values()->toArray();
        }

        // Build media object - only include non-empty collections
        $media = [];
        if (!empty($gallery)) {
            $media['gallery'] = $gallery;
        }
        if (!empty($videos)) {
            $media['videos'] = $videos;
        }
        if (!empty($spheres360)) {
            $media['spheres_360'] = $spheres360;
        }

        // Add embeds under media
        $media['embeds'] = [
            'video_url' => $this->video_url,
            'webcam' => $this->webcam,
        ];

        // Build tags: up to 4 names from sporting activities, culinary offers, natural conditions
        $tags = collect()
            ->merge(
                $this->whenLoaded('sportingActivities', function () use ($locale) {
                    return $this->sportingActivities->map(function ($item) use ($locale) {
                        $trans = $item->translations->firstWhere('locale', $locale);
                        $transEn = $item->translations->firstWhere('locale', 'en');
                        return $trans?->name ?? $transEn?->name ?? null;
                    })->filter()->values();
                }, collect())
            )
            ->merge(
                $this->whenLoaded('culinaryOffers', function () use ($locale) {
                    return $this->culinaryOffers->map(function ($item) use ($locale) {
                        $trans = $item->translations->firstWhere('locale', $locale);
                        $transEn = $item->translations->firstWhere('locale', 'en');
                        return $trans?->name ?? $transEn?->name ?? null;
                    })->filter()->values();
                }, collect())
            )
            ->merge(
                $this->whenLoaded('naturalLocationConditions', function () use ($locale) {
                    return $this->naturalLocationConditions->map(function ($item) use ($locale) {
                        $trans = $item->translations->firstWhere('locale', $locale);
                        $transEn = $item->translations->firstWhere('locale', 'en');
                        return $trans?->name ?? $transEn?->name ?? null;
                    })->filter()->values();
                }, collect())
            )
            ->take(4)
            ->values()
            ->toArray();

        return [
            'id' => $this->id,
            'name' => $translation->name ?? $translationEn->name ?? $this->name,
            'slug' => $translation->slug ?? $translationEn->slug ?? null,
            // 'description' removed from list view to reduce memory usage when caching
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'country_code' => $this->country_code,
            'country_name' => $countryName,
            'country_slug' => $countrySlug,
            'continent_code' => $this->continent_code,
            'continent_slug' => $continentSlug,
            'region_slug' => $regionSlug,
            'island_slug' => $islandSlug,
            'city_name' => $cityName,
            'city_slug' => $citySlug,
            'main_image' => $mainImage,
            'media' => $media,
            'tags' => $tags,
            // 'main_image_imagor' removed - frontend constructs Imagor URLs directly
        ];
    }
}
