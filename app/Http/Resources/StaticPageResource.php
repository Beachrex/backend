<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StaticPageResource extends JsonResource
{
    /**
     * Full static page resource (used by the show endpoint).
     * Resolves the translation for the request locale, falling back to English.
     */
    public function toArray(Request $request): array
    {
        $locale = app()->getLocale();

        $translation = $this->translations
            ->firstWhere('locale', $locale)
            ?? $this->translations->firstWhere('locale', 'en');

        return [
            'id' => $this->id,
            'code' => $this->code,
            'title' => $translation?->title ?? $this->code,
            'slug' => $translation?->slug ?? $this->code,
            'content' => $translation?->content,
            'meta_title' => $translation?->meta_title,
            'meta_description' => $translation?->meta_description,
            'locale' => $translation?->locale ?? 'en',
            'is_in_footer' => $this->is_in_footer,
            'sort_order' => $this->sort_order,
        ];
    }
}
