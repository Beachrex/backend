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

        $data = [
            'code' => $this->code,
            'name' => $translation?->name ?? 'Unknown',
            'full_name' => $translation?->full_name ?? null,
            'slug' => $translation?->slug ?? 'unknown',
            'iso3' => $this->iso3,
            'continent_code' => $this->continent_code,
            'image_path' => $this->image_path,
        ];

        // Add beaches_count if available
        if ($this->relationLoaded('beaches') || isset($this->beaches_count)) {
            $data['beaches_count'] = $this->beaches_count ?? $this->beaches->count();
        }

        // Add continent only if loaded (for show endpoint)
        if ($this->relationLoaded('continent')) {
            $data['continent'] = new ContinentResource($this->continent);
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
