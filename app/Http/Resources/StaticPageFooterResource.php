<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StaticPageFooterResource extends JsonResource
{
    /**
     * Lightweight static page resource (used by the footer list endpoint).
     * Intentionally omits content + meta fields to keep the footer payload small.
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
            'sort_order' => $this->sort_order,
        ];
    }
}
