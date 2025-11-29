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

        $data = [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $translation?->name ?? 'Unknown',
            'slug' => $translation?->slug ?? 'unknown',
            'country_code' => $this->country_code,
            'region_id' => $this->region_id,
        ];

        // Add beaches_count if available
        if ($this->relationLoaded('beaches') || isset($this->beaches_count)) {
            $data['beaches_count'] = $this->beaches_count ?? $this->beaches->count();
        }

        // Add country only if loaded (for show endpoint)
        if ($this->relationLoaded('country')) {
            $data['country'] = new CountryResource($this->country);
        }

        // Add region only if loaded and not null (for show endpoint)
        if ($this->relationLoaded('region') && $this->region) {
            $data['region'] = new RegionResource($this->region);
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

