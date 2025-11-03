<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ArticleResource extends JsonResource
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
            'category' => $this->category,
            'title' => $translation?->title ?? 'Unknown',
            'slug' => $translation?->slug ?? 'unknown',
            'excerpt' => $translation?->excerpt ?? null,
            'feature_image' => $this->feature_image,
            // Only include full content when viewing a single article
            'content' => $this->when(
                $request->route()->getName() === 'articles.show',
                $translation?->content ?? null
            ),
        ];
    }
}

