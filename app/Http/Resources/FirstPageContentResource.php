<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FirstPageContentResource extends JsonResource
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

        // Get media
        $media = $this->when(
            $this->relationLoaded('media') && $this->media->isNotEmpty(),
            function () {
                return $this->media->map(function ($mediaItem) {
                    return [
                        'id' => $mediaItem->id,
                        'type' => $mediaItem->mediaType->code ?? null,
                        'url' => $mediaItem->url,
                        'file_name' => $mediaItem->file_name,
                        'is_primary' => $mediaItem->is_primary,
                        'collection' => $mediaItem->mediaCollection->code ?? null,
                    ];
                });
            }
        );

        // Get items (for repeater sections)
        $items = $this->when(
            $this->relationLoaded('items') && $this->items->isNotEmpty(),
            function () use ($locale) {
                return $this->items->map(function ($item) use ($locale) {
                    $itemTranslation = $item->translations
                        ->firstWhere('locale', $locale)
                        ?? $item->translations->firstWhere('locale', 'en');

                    $itemMedia = null;
                    if ($item->relationLoaded('media') && $item->media->isNotEmpty()) {
                        $itemMedia = $item->media->map(function ($mediaItem) {
                            return [
                                'id' => $mediaItem->id,
                                'type' => $mediaItem->mediaType->code ?? null,
                                'url' => $mediaItem->url,
                                'file_name' => $mediaItem->file_name,
                                'is_primary' => $mediaItem->is_primary,
                                'collection' => $mediaItem->mediaCollection->code ?? null,
                            ];
                        });
                    }

                    return [
                        'id' => $item->id,
                        'order' => $item->order,
                        'title' => $itemTranslation?->title ?? null,
                        'description' => $itemTranslation?->description ?? null,
                        'media' => $itemMedia,
                    ];
                });
            }
        );

        return [
            'id' => $this->id,
            'code' => $this->code,
            'section_type' => $this->section_type,
            'order' => $this->order,
            'title' => $translation?->title ?? null,
            'description' => $translation?->description ?? null,
            'config' => $this->config,
            'media' => $media,
            'items' => $items,
        ];
    }
}

