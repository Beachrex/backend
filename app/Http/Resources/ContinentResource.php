<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContinentResource extends JsonResource
{
    /** @return array<string, int> beaches per continent code, counted once per request */
    public static function beachCounts(): array
    {
        $attributes = request()->attributes;
        if (! $attributes->has('continent_beach_counts')) {
            $attributes->set('continent_beach_counts', \Illuminate\Support\Facades\DB::table('beach')
                ->whereNotNull('continent_code')
                ->groupBy('continent_code')
                ->selectRaw('continent_code, count(*) as n')
                ->pluck('n', 'continent_code')
                ->map(fn ($n) => (int) $n)
                ->all());
        }

        return $attributes->get('continent_beach_counts');
    }

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
            // Before, `when(loaded || count set, $this->beaches_count ?? $this->beaches->count())` evaluated its value
            // eagerly: a continent nested in a city, region, island or country lazy-loaded EVERY beach row of the
            // continent, and from then on the field appeared (for every item but the first). Now the same number
            // (all beaches of the continent, as the relation counts them) comes from one grouped count per request,
            // and the field is always present (ADR 2026-10-30 "API performance").
            'beaches_count' => $this->beaches_count
                ?? ($this->relationLoaded('beaches') ? $this->beaches->count() : (self::beachCounts()[$this->code] ?? 0)),
            'accommodation_count' => 0, // Hardcoded until booking integration
        ];
    }
}
