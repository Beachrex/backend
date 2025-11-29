<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContinentResource extends JsonResource
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
            'slug' => $translation?->slug ?? 'unknown',
            'main_image' => $this->main_image,
            'beaches_count' => $this->when(
                $this->relationLoaded('beaches') || isset($this->beaches_count),
                $this->beaches_count ?? $this->beaches->count()
            ),
        ];
    }
}
