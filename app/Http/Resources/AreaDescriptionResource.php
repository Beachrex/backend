<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\HandlesTranslations;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AreaDescriptionResource extends JsonResource
{
    use HandlesTranslations;

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $translation = $this->getTranslation($request, $this->translations);

        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $translation?->name ?? $this->code,
            'slug' => $translation?->slug ?? $this->code,
            'beaches_count' => $this->when(
                $this->relationLoaded('beaches') || isset($this->beaches_count),
                $this->beaches_count ?? $this->beaches->count()
            ),
            // Only include all translations if explicitly requested
            'translations' => $this->when(
                $this->relationLoaded('translations') && $this->shouldIncludeAllTranslations($request),
                AreaDescriptionTranslationResource::collection($this->translations)
            ),
            // Include current translation info
            'translation' => $this->when(
                $this->relationLoaded('translations'),
                $this->getLocalizedTranslation($request, $this->translations)
            ),
        ];
    }
}

