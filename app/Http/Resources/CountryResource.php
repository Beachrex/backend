<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CountryResource extends JsonResource
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
            'code' => $this->code,
            'name' => $translation?->name ?? 'Unknown',
            'full_name' => $translation?->full_name ?? null,
            'slug' => $translation?->slug ?? 'unknown',
            'iso3' => $this->iso3,
            'continent_code' => $this->continent_code,
            'continent' => $this->when(
                $this->relationLoaded('continent'),
                new ContinentResource($this->continent)
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
