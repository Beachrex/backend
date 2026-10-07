<?php

namespace App\Services\AI;

use App\Models\Beach;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Fetches and scores candidate beaches for itinerary generation (TASK-001).
 *
 * Hard filters use the real attribute tables (beach_type, location_infrastructure).
 * Soft preferences (interests, children) only change the score. When too few
 * beaches match, facility and then surface filters are relaxed — accessibility
 * never is. Every relaxation is reported so it can be shown in `assumptions`.
 */
class ItineraryContextBuilderService
{
    /** Request surface values → beach_type codes. */
    public const SURFACE_TO_BEACH_TYPES = [
        'sand' => ['sand', 'brown_sand', 'black_sand'],
        'pebble' => ['small_stones', 'big_stones'],
        'rock' => ['rock_beach'],
        'concrete' => ['concrete'],
        'grass' => ['grass_beach'],
    ];

    /** Free-text facility names (lowercased) → location_infrastructure codes. */
    public const FACILITY_TO_INFRASTRUCTURE = [
        'parking' => 'parking',
        'wc' => 'wc', 'toilet' => 'wc', 'toilets' => 'wc',
        'shower' => 'shower', 'showers' => 'shower',
        'lifeguard' => 'lifeguard',
        'playground' => 'children_playground', 'children_playground' => 'children_playground',
        'restaurant' => 'restaurants', 'restaurants' => 'restaurants', 'bar' => 'restaurants',
        'sunshades' => 'sunshades', 'umbrellas' => 'sunshades', 'parasols' => 'sunshades',
        'deckchairs' => 'deckchairs', 'sunbeds' => 'deckchairs', 'loungers' => 'deckchairs',
        'pool' => 'swimming_pool', 'swimming_pool' => 'swimming_pool',
        'waterslide' => 'waterslide',
        'pier' => 'pier',
        'wifi' => 'wifi_hotspot',
        'atm' => 'atm',
        'dog_friendly' => 'dog_friendly', 'dogs' => 'dog_friendly',
        'naturist' => 'naturist_friendly', 'naturist_friendly' => 'naturist_friendly',
        'accessible' => 'access_for_disabled', 'access_for_disabled' => 'access_for_disabled',
    ];

    /** Interests → what makes a beach a match (activity codes, user type codes, infrastructure codes). */
    private const INTEREST_SIGNALS = [
        'swimming' => ['activities' => ['swimming']],
        'snorkeling' => ['activities' => ['snorkeling']],
        'diving' => ['activities' => ['diving']],
        'surfing' => ['activities' => ['surfing', 'windsurfing', 'kiteboarding']],
        'kayaking' => ['activities' => ['water_sports']],
        'family' => ['user_types' => ['family'], 'infrastructure' => ['children_playground', 'lifeguard']],
        'nightlife' => ['user_types' => ['youth'], 'infrastructure' => ['dance_floor']],
        'quiet' => ['infrastructure' => ['no_service']],
        'nature' => ['user_types' => ['adventurer']],
        'hiking' => ['user_types' => ['adventurer']],
    ];

    private const POOL_SIZE = 150;

    /**
     * @return array{beaches: array<int, array>, relaxed: string[], unmapped_facilities: string[]}
     */
    public function fetchCandidateBeaches(
        array $destination,
        array $preferences,
        array $constraints,
        array $travelParty,
        string $locale,
        int $minimumNeeded,
    ): array {
        $surfaceCodes = collect($preferences['surface_types'] ?? [])
            ->flatMap(fn ($s) => self::SURFACE_TO_BEACH_TYPES[$s] ?? [])->unique()->values()->all();

        $facilityCodes = [];
        $unmapped = [];
        foreach ($preferences['must_have_facilities'] ?? [] as $facility) {
            $key = str_replace([' ', '-'], '_', mb_strtolower(trim($facility)));
            isset(self::FACILITY_TO_INFRASTRUCTURE[$key])
                ? $facilityCodes[] = self::FACILITY_TO_INFRASTRUCTURE[$key]
                : $unmapped[] = $facility;
        }
        $facilityCodes = array_values(array_unique($facilityCodes));
        $needsAccessible = !empty($preferences['accessibility']);

        // Try strict first, then relax facilities, then surface. Accessibility is never relaxed.
        $attempts = [
            ['facilities' => $facilityCodes, 'surface' => $surfaceCodes, 'relaxed' => []],
            ['facilities' => [], 'surface' => $surfaceCodes, 'relaxed' => $facilityCodes ? ['facilities'] : []],
            ['facilities' => [], 'surface' => [], 'relaxed' => array_values(array_filter([
                $facilityCodes ? 'facilities' : null,
                $surfaceCodes ? 'surface' : null,
            ]))],
        ];

        $beaches = collect();
        $relaxed = [];
        foreach ($attempts as $attempt) {
            $beaches = $this->query($destination, $constraints, $locale, $attempt['facilities'], $attempt['surface'], $needsAccessible)->get();
            $relaxed = $attempt['relaxed'];
            if ($beaches->count() >= $minimumNeeded) {
                break;
            }
        }

        $interests = $preferences['interests'] ?? [];
        $hasChildren = (int) ($travelParty['children'] ?? 0) > 0;
        $maxViews = max(1, (int) $beaches->max('beach_views'));

        return [
            'beaches' => $beaches
                ->map(fn (Beach $b) => $this->format($b, $locale, $interests, $hasChildren, $maxViews))
                ->values()
                ->all(),
            'relaxed' => $relaxed,
            'unmapped_facilities' => $unmapped,
        ];
    }

    private function query(array $destination, array $constraints, string $locale, array $facilityCodes, array $surfaceCodes, bool $needsAccessible): Builder
    {
        $locales = array_values(array_unique([$locale, 'en']));
        $withTranslations = fn ($q) => $q->whereIn('locale', $locales);

        $query = Beach::query()
            ->where('status_id', 1)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->where('country_code', strtoupper($destination['country_code']));

        foreach (['region' => 'region_slug', 'city' => 'city_slug'] as $relation => $key) {
            if (!empty($destination[$key])) {
                $query->whereHas("{$relation}.translations", fn ($q) => $q->where('slug', $destination[$key]));
            }
        }

        if ($surfaceCodes) {
            $query->whereHas('beachType', fn ($q) => $q->whereIn('code', $surfaceCodes));
        }
        foreach ($facilityCodes as $code) {
            $query->whereHas('locationInfrastructures', fn ($q) => $q->where('code', $code));
        }
        if ($needsAccessible) {
            $query->whereHas('locationInfrastructures', fn ($q) => $q->where('code', 'access_for_disabled'));
        }
        if (!empty($constraints['avoid_ferries'])) {
            $query->whereNull('island_id');
        }

        return $query
            ->with([
                'translations' => $withTranslations,
                'city.translations' => $withTranslations,
                'region.translations' => $withTranslations,
                'beachType.translations' => $withTranslations,
                'locationInfrastructures.translations' => $withTranslations,
                'userTypes',
                'sportingActivities',
            ])
            ->orderByDesc('beach_views')
            ->orderBy('id')
            ->limit(self::POOL_SIZE);
    }

    private function format(Beach $beach, string $locale, array $interests, bool $hasChildren, int $maxViews): array
    {
        $translation = $this->translation($beach, $locale);
        $infraCodes = $beach->locationInfrastructures->pluck('code')->all();
        $userTypes = $beach->userTypes->pluck('code')->all();
        $activities = $beach->sportingActivities->pluck('code')->all();

        $matched = [];
        foreach ($interests as $interest) {
            $signals = self::INTEREST_SIGNALS[$interest] ?? [];
            if (array_intersect($signals['activities'] ?? [], $activities)
                || array_intersect($signals['user_types'] ?? [], $userTypes)
                || array_intersect($signals['infrastructure'] ?? [], $infraCodes)) {
                $matched[] = $interest;
            }
        }
        $familyFriendly = in_array('family', $userTypes, true) || in_array('children_playground', $infraCodes, true);

        // Popularity 0..1 (log-scaled) + 1 per matched interest + 1 for families travelling with children.
        $score = log10(1 + (int) $beach->beach_views) / log10(1 + $maxViews)
            + count($matched)
            + ($hasChildren && $familyFriendly ? 1 : 0);

        $description = $translation?->description ? trim(strip_tags($translation->description)) : null;

        return [
            'id' => $beach->id,
            'name' => $translation?->name ?? $beach->name ?? "Beach #{$beach->id}",
            'slug' => $translation?->slug,
            'city' => $this->localizedName($beach->city, $locale),
            'region' => $this->localizedName($beach->region, $locale),
            'country_code' => $beach->country_code,
            'latitude' => (float) $beach->latitude,
            'longitude' => (float) $beach->longitude,
            'type' => $this->localizedName($beach->beachType, $locale),
            'type_code' => $beach->beachType?->code,
            'facilities' => $beach->locationInfrastructures
                ->map(fn ($i) => $this->localizedName($i, $locale))->filter()->values()->all(),
            'facility_codes' => $infraCodes,
            'suitable_for' => $userTypes,
            'activities' => $activities,
            'has_parking' => in_array('parking', $infraCodes, true),
            'accessible' => in_array('access_for_disabled', $infraCodes, true),
            'description' => $description ? mb_substr($description, 0, 400) : null,
            'matched_interests' => $matched,
            'beach_views' => (int) $beach->beach_views,
            'score' => round($score, 4),
        ];
    }

    private function translation(Model $model, string $locale): ?Model
    {
        $translations = $model->relationLoaded('translations') ? $model->translations : collect();

        return $translations->firstWhere('locale', $locale) ?? $translations->firstWhere('locale', 'en');
    }

    private function localizedName(?Model $model, string $locale): ?string
    {
        if (!$model) {
            return null;
        }

        return $this->translation($model, $locale)?->name ?? $model->name ?? $model->code ?? null;
    }
}
