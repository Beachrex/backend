<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoadToParkingTypeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Get the locale from Accept-Language header
        $locale = $request->header('Accept-Language', 'en');
        
        // Find the translation for the requested locale
        $translation = $this->translations->firstWhere('locale', $locale);
        
        // Fallback to English if translation not found
        if (!$translation) {
            $translation = $this->translations->firstWhere('locale', 'en');
        }

        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $translation?->name ?? $this->code,
            'slug' => $translation?->slug ?? $this->code,
            'show_filter' => $this->show_filter,
            'beaches_count' => $this->when(
                $this->relationLoaded('beaches') || isset($this->beaches_count),
                $this->beaches_count ?? $this->beaches->count()
            ),
            'translations' => $this->when(
                $this->relationLoaded('translations'),
                RoadToParkingTypeTranslationResource::collection($this->translations)
            ),
        ];
    }
}

