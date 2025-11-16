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

        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $translation?->name ?? 'Unknown',
            'slug' => $translation?->slug ?? 'unknown',
            'country_code' => $this->country_code,
            'region_id' => $this->region_id,
            'country' => $this->when(
                $this->relationLoaded('country'),
                new CountryResource($this->country)
            ),
            'region' => $this->when(
                $this->relationLoaded('region'),
                new RegionResource($this->region)
            ),
            'beaches_count' => $this->when(
                $this->relationLoaded('beaches') || isset($this->beaches_count),
                $this->beaches_count ?? $this->beaches->count()
            ),
            'badges' => $this->when(
                $this->relationLoaded('badges'),
                BadgeResource::collection($this->badges)
            ),
            'articles' => $this->when(
                $this->relationLoaded('articles'),
                ArticleResource::collection($this->articles)
            ),
        ];
    }
}

