<?php

namespace App\Services\AI;

use App\Models\Beach;
use App\Services\WeatherService;
use App\Services\PostGISService;
use Illuminate\Support\Facades\Log;

/**
 * Context Builder Service
 *
 * Assembles context for LLM prompts with strict capping and sanitization.
 *
 * CRITICAL RULES:
 * - Context is deterministic (same input => same context)
 * - Context is capped (tier-based limits)
 * - Context is cheap (no unbounded queries)
 * - No PII in context, ever
 * - No raw HTML or large descriptions
 *
 * Source: docs/ai/architecture/prompt-structure.md Section 3
 */
class ContextBuilderService
{
    /**
     * Max beaches per tier (HARD LIMITS)
     */
    public const MAX_BEACHES = [
        'free' => 5,
        'basic' => 10,
        'premium' => 20,
        'enterprise' => 50,
    ];

    /**
     * Max characters per beach description (HARD LIMIT)
     */
    private const MAX_DESCRIPTION_CHARS = 200;

    /**
     * Max total context tokens (HARD LIMIT)
     * Rough estimation: 1 token ≈ 4 characters
     */
    private const MAX_CONTEXT_TOKENS = [
        'free' => 1000,      // ~4000 characters
        'basic' => 2000,     // ~8000 characters
        'premium' => 4000,   // ~16000 characters
        'enterprise' => 8000, // ~32000 characters
    ];

    public function __construct(
        private WeatherService $weatherService,
        private PostGISService $postGISService
    ) {}

    /**
     * Build context for LLM prompt.
     *
     * @param array $params Context parameters
     * @return array Context object with beaches, weather, filters, metadata
     */
    public function buildContext(array $params): array
    {
        $tier = $params['tier'] ?? 'free';
        $locale = $params['locale'] ?? 'en';
        $latitude = $params['latitude'] ?? null;
        $longitude = $params['longitude'] ?? null;
        $radius = $params['radius'] ?? 50; // km
        $filters = $params['filters'] ?? [];
        $includeWeather = $params['include_weather'] ?? false;
        $intent = $params['intent'] ?? 'beach_search';

        $context = [
            'beaches' => [],
            'weather' => null,
            'filters_available' => $this->getAvailableFilters($locale),
            'metadata' => [
                'tier' => $tier,
                'locale' => $locale,
                'max_beaches' => self::MAX_BEACHES[$tier],
                'context_truncated' => false,
                'beaches_fetched' => 0,
                'beaches_returned' => 0,
                'location_provided' => $latitude !== null && $longitude !== null,
                'intent' => $intent,
            ],
        ];

        // TASK-003: candidates already chosen by the code from the understood query (place, attributes, GPS)
        if (isset($params['candidates'])) {
            $beaches = $this->candidateBeaches($params['candidates'], $locale);
            $context['beaches'] = $beaches;
            $context['metadata']['beaches_fetched'] = count($beaches);
            $context['metadata']['beaches_returned'] = count($beaches);
            $context['metadata']['candidates'] = true;
        }
        // RULE: Popularity queries do NOT require location
        // Fetch beaches by popularity (beach_views) if intent is popularity_query
        elseif ($intent === 'popularity_query') {
            $beaches = $this->fetchPopularBeaches($tier, $filters, $locale);
            $context['beaches'] = $beaches;
            $context['metadata']['beaches_fetched'] = count($beaches);
            $context['metadata']['beaches_returned'] = count($beaches);
        }
        // RULE: Proximity queries REQUIRE location
        // Fetch beaches ONLY if location provided for proximity/distance-based queries
        elseif ($latitude !== null && $longitude !== null) {
            $beaches = $this->fetchBeaches($latitude, $longitude, $radius, $tier, $filters, $locale);
            $context['beaches'] = $beaches;
            $context['metadata']['beaches_fetched'] = count($beaches);
            $context['metadata']['beaches_returned'] = count($beaches);
        }
        // RULE: beach_search without location but WITH filters (country/region/city)
        // Return default suggestions (1-3 beaches) ordered by beach_views
        elseif ($intent === 'beach_search' && !empty($filters)) {
            $beaches = $this->fetchPopularBeaches($tier, $filters, $locale);
            $context['beaches'] = $beaches;
            $context['metadata']['beaches_fetched'] = count($beaches);
            $context['metadata']['beaches_returned'] = count($beaches);
        }

        // Fetch weather if allowed by tier and requested
        if ($includeWeather && $this->canAccessWeather($tier)) {
            if ($latitude !== null && $longitude !== null) {
                $context['weather'] = $this->fetchWeather($latitude, $longitude);
            }
        }

        // Check if context was truncated
        $contextSize = $this->estimateContextSize($context);
        $maxTokens = self::MAX_CONTEXT_TOKENS[$tier];

        if ($contextSize > $maxTokens) {
            $context = $this->truncateContext($context, $maxTokens);
            $context['metadata']['context_truncated'] = true;
        }

        return $context;
    }

    /**
     * Fetch beaches near location with tier-based limits.
     */
    private function fetchBeaches(
        float $latitude,
        float $longitude,
        float $radius,
        string $tier,
        array $filters,
        string $locale
    ): array {
        $limit = self::MAX_BEACHES[$tier];

        // Set locale for PostGISService
        app()->setLocale($locale);

        // Use PostGISService to fetch nearest beaches
        $beaches = $this->postGISService->getNearestBeaches(
            $latitude,
            $longitude,
            $limit
        );

        // Sanitize and format beaches
        return $beaches->map(function ($beach) use ($locale) {
            return $this->sanitizeBeach($beach, $locale);
        })->toArray();
    }

    /**
     * Fetch popular beaches ordered by beach_views (without location requirement).
     *
     * RULE: Popularity queries do NOT require location.
     * Returns top 1-3 beaches ordered by beach_views DESC.
     */
    private function fetchPopularBeaches(
        string $tier,
        array $filters,
        string $locale
    ): array {
        // For popularity queries, return max 3 beaches (even for higher tiers)
        $limit = min(self::MAX_BEACHES[$tier], 3);

        // Set locale
        app()->setLocale($locale);

        // Fetch beaches ordered by beach_views
        $query = Beach::query()
            ->where('status_id', 1)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->with(['continent.translations', 'country.translations', 'region.translations', 'island.translations', 'city.translations'])
            ->orderBy('beach_views', 'desc')
            ->limit($limit);

        // Apply country filter if present in filters
        if (!empty($filters['country'])) {
            $query->where('country_code', $filters['country']);
        }

        $beaches = $query->get();

        // Format beaches (without distance_km)
        return $beaches->map(function ($beach) use ($locale) {
            return $this->sanitizeBeachForPopularity($beach, $locale);
        })->toArray();
    }

    /**
     * TASK-003: chat beaches from candidate rows (ItineraryContextBuilderService::fetchChatCandidates), in the
     * candidates' order. Keeps the existing fields (slugs, main_image) and adds the localized facts.
     */
    private function candidateBeaches(array $candidates, string $locale): array
    {
        $ids = array_column($candidates, 'id');
        if (! $ids) {
            return [];
        }
        $models = Beach::query()
            ->whereIn('id', $ids)
            ->with(['continent.translations', 'country.translations', 'region.translations', 'island.translations', 'city.translations'])
            ->get()
            ->keyBy('id');

        $beaches = [];
        foreach ($candidates as $candidate) {
            $model = $models->get($candidate['id']);
            if (! $model) {
                continue;
            }
            $beaches[] = array_merge($this->sanitizeBeachForPopularity($model, $locale), [
                'name' => $candidate['name'],
                'slug' => $candidate['slug'],
                'city' => $candidate['city'],
                'region' => $candidate['region'],
                'description' => $candidate['description'],
                'surface' => $candidate['type_code'],
                'type' => $candidate['type'],
                'facilities' => $candidate['facilities'],
                'suitable_for' => $candidate['suitable_for'],
                'distance_km' => $candidate['distance_km'] ?? null,
            ]);
        }

        return $beaches;
    }

    /**
     * Sanitize beach data for popularity queries (no distance_km).
     */
    private function sanitizeBeachForPopularity($beach, string $locale): array
    {
        $beachArray = $beach->toArray();

        $description = $beachArray['description'] ?? '';

        // Strip HTML tags
        $description = strip_tags($description);

        // Truncate to max length
        if (strlen($description) > self::MAX_DESCRIPTION_CHARS) {
            $description = substr($description, 0, self::MAX_DESCRIPTION_CHARS) . '...';
        }

        // Get location slugs from relationships
        $continentSlug = null;
        $countrySlug = null;
        $regionSlug = null;
        $islandSlug = null;
        $citySlug = null;

        if ($beach->continent && $beach->continent->translations) {
            $continentTranslation = $beach->continent->translations->where('locale', $locale)->first()
                ?? $beach->continent->translations->where('locale', 'en')->first();
            $continentSlug = $continentTranslation?->slug ?? null;
        }

        if ($beach->country && $beach->country->translations) {
            $countryTranslation = $beach->country->translations->where('locale', $locale)->first()
                ?? $beach->country->translations->where('locale', 'en')->first();
            $countrySlug = $countryTranslation?->slug ?? null;
        }

        if ($beach->region && $beach->region->translations) {
            $regionTranslation = $beach->region->translations->where('locale', $locale)->first()
                ?? $beach->region->translations->where('locale', 'en')->first();
            $regionSlug = $regionTranslation?->slug ?? null;
        }

        if ($beach->island && $beach->island->translations) {
            $islandTranslation = $beach->island->translations->where('locale', $locale)->first()
                ?? $beach->island->translations->where('locale', 'en')->first();
            $islandSlug = $islandTranslation?->slug ?? null;
        }

        if ($beach->city && $beach->city->translations) {
            $cityTranslation = $beach->city->translations->where('locale', $locale)->first()
                ?? $beach->city->translations->where('locale', 'en')->first();
            $citySlug = $cityTranslation?->slug ?? null;
        }

        return [
            'id' => $beachArray['id'] ?? null,
            'name' => $beachArray['name'] ?? null,
            'slug' => $beachArray['slug'] ?? null,
            'country_code' => $beachArray['country_code'] ?? null,
            'city' => $beachArray['city'] ?? null,
            'description' => $description,
            'surface' => $beachArray['surface'] ?? null,
            'facilities' => $beachArray['facilities'] ?? [],
            'distance_km' => null, // ALWAYS null for popularity queries
            'beach_views' => $beachArray['beach_views'] ?? 0,
            'main_image' => $beachArray['main_image'] ?? null,
            // Location slugs for frontend URL building
            'continent_slug' => $continentSlug,
            'country_slug' => $countrySlug,
            'region_slug' => $regionSlug,
            'island_slug' => $islandSlug,
            'city_slug' => $citySlug,
        ];
    }

    /**
     * Sanitize beach data for context injection.
     *
     * RULES:
     * - No PII
     * - No raw HTML
     * - Truncate descriptions to MAX_DESCRIPTION_CHARS
     * - Only essential fields
     */
    private function sanitizeBeach($beach, string $locale): array
    {
        // PostGISService returns arrays, not objects
        $beachArray = is_array($beach) ? $beach : (array) $beach;

        $description = $beachArray['description'] ?? '';

        // Strip HTML tags
        $description = strip_tags($description);

        // Truncate to max length
        if (strlen($description) > self::MAX_DESCRIPTION_CHARS) {
            $description = substr($description, 0, self::MAX_DESCRIPTION_CHARS) . '...';
        }

        return [
            'id' => $beachArray['id'] ?? null,
            'name' => $beachArray['name'] ?? null,
            'slug' => $beachArray['slug'] ?? null,
            'location' => [
                'country_code' => $beachArray['country_code'] ?? null,
                'country_name' => $beachArray['country_name'] ?? null,
                'latitude' => isset($beachArray['latitude']) ? (float) $beachArray['latitude'] : null,
                'longitude' => isset($beachArray['longitude']) ? (float) $beachArray['longitude'] : null,
                'distance_km' => isset($beachArray['distance_km']) ? round((float) $beachArray['distance_km'], 1) : null,
            ],
            'description' => $description,
            'main_image' => $beachArray['main_image'] ?? null,
        ];
    }

    /**
     * Fetch weather data for location.
     */
    private function fetchWeather(float $latitude, float $longitude): ?array
    {
        $weather = $this->weatherService->getCurrentWeather($latitude, $longitude);

        if ($weather === null) {
            return null;
        }

        // Return only essential weather fields
        return [
            'temperature' => $weather['temperature'] ?? null,
            'condition' => $weather['condition'] ?? null,
            'description' => $weather['description'] ?? null,
            'wind_speed' => $weather['wind_speed'] ?? null,
            'humidity' => $weather['humidity'] ?? null,
        ];
    }

    /**
     * Get available filters for context injection.
     */
    private function getAvailableFilters(string $locale): array
    {
        // Simplified filter list (no database queries)
        return [
            'countries' => [
                ['code' => 'HR', 'name' => 'Croatia'],
                ['code' => 'SI', 'name' => 'Slovenia'],
                ['code' => 'ME', 'name' => 'Montenegro'],
            ],
            'surface_types' => ['sand', 'pebble', 'rock', 'concrete'],
            'facilities' => ['parking', 'showers', 'toilets', 'lifeguard', 'playground'],
            'accessibility' => ['wheelchair_accessible', 'ramp_available'],
        ];
    }

    /**
     * Check if tier can access weather data.
     */
    private function canAccessWeather(string $tier): bool
    {
        // All tiers can access current weather (free feature)
        return true;
    }

    /**
     * Estimate context size in tokens.
     *
     * Rough estimation: 1 token ≈ 4 characters
     */
    private function estimateContextSize(array $context): int
    {
        $json = json_encode($context);
        $chars = strlen($json);

        return (int) ceil($chars / 4);
    }

    /**
     * Truncate context to fit within token budget.
     *
     * Strategy: Remove beaches from the end until context fits.
     */
    private function truncateContext(array $context, int $maxTokens): array
    {
        $beaches = $context['beaches'];

        while ($this->estimateContextSize($context) > $maxTokens && count($beaches) > 1) {
            // Remove last beach
            array_pop($beaches);
            $context['beaches'] = $beaches;
        }

        $context['metadata']['beaches_returned'] = count($beaches);

        Log::warning('Context truncated to fit token budget', [
            'max_tokens' => $maxTokens,
            'beaches_before' => $context['metadata']['beaches_fetched'],
            'beaches_after' => count($beaches),
        ]);

        return $context;
    }

    /**
     * Format context as text for prompt injection.
     *
     * @param array $context Context object
     * @return string Formatted context text
     */
    public function formatContextAsText(array $context): string
    {
        $text = "AVAILABLE CONTEXT:\n\n";

        $intent = $context['metadata']['intent'] ?? 'beach_search';
        $locationProvided = $context['metadata']['location_provided'] ?? false;

        // Beaches
        if (!empty($context['beaches'])) {
            // Different header based on intent
            if ($intent === 'popularity_query') {
                $text .= "BEACHES (ordered by popularity - beach_views):\n";
                $text .= "IMPORTANT: Use transparent language like 'frequently viewed' or 'based on visitor interest'.\n";
                $text .= "Do NOT claim 'highest rated' or 'best reviewed' (we don't have verified ratings).\n\n";
            } elseif (! empty($context['metadata']['candidates'])) {
                $text .= "BEACHES (chosen from the Beachrex database to match the user's request; best match first):\n";
                $text .= "IMPORTANT: Recommend only these beaches and use only the facts listed here.\n\n";
            } elseif ($locationProvided) {
                $text .= "BEACHES (nearest to your location):\n\n";
            } elseif ($intent === 'beach_search') {
                $text .= "BEACHES (default suggestions ordered by popularity - beach_views):\n";
                $text .= "IMPORTANT: These are suggestions based on visitor interest. You may ask follow-up questions,\n";
                $text .= "but you MUST include these beaches in your response (do NOT return empty beaches array).\n\n";
            } else {
                $text .= "BEACHES:\n\n";
            }

            foreach ($context['beaches'] as $index => $beach) {
                $num = $index + 1;
                $text .= "Beach {$num}:\n";
                $text .= "- ID: {$beach['id']}\n";
                $text .= "- Name: {$beach['name']}\n";

                // TASK-003 candidate facts (only candidate beaches carry these as plain strings/lists)
                if (! empty($context['metadata']['candidates'])) {
                    $place = implode(', ', array_filter([$beach['city'] ?? null, $beach['region'] ?? null, $beach['country_code'] ?? null], 'is_string'));
                    if ($place !== '') {
                        $text .= "- Place: {$place}\n";
                    }
                    if (! empty($beach['type'])) {
                        $text .= "- Type: {$beach['type']}\n";
                    }
                    if (! empty($beach['facilities'])) {
                        $text .= '- Facilities: '.implode(', ', $beach['facilities'])."\n";
                    }
                    if (! empty($beach['suitable_for'])) {
                        $text .= '- Suitable for: '.implode(', ', $beach['suitable_for'])."\n";
                    }
                    if (($beach['distance_km'] ?? null) !== null) {
                        $text .= "- Distance from the user: {$beach['distance_km']} km\n";
                    }
                }

                if (isset($beach['location']['country_name'])) {
                    $text .= "- Location: {$beach['location']['country_name']}";
                    if (isset($beach['location']['distance_km']) && $beach['location']['distance_km'] !== null) {
                        $text .= " ({$beach['location']['distance_km']} km away)";
                    }
                    $text .= "\n";
                }

                // Show beach_views for popularity queries
                if ($intent === 'popularity_query' && isset($beach['beach_views'])) {
                    $text .= "- Views: {$beach['beach_views']}\n";
                }

                if (isset($beach['description']) && $beach['description']) {
                    $text .= "- Description: {$beach['description']}\n";
                }

                $text .= "\n";
            }
        } else {
            // No beaches - different message based on intent
            if ($intent === 'proximity_query') {
                // Proximity query requires location
                $text .= "NO LOCATION PROVIDED:\n";
                $text .= "User asked for beaches 'near me' but has not provided their location.\n";
                $text .= "You MUST ask the user to share their location.\n\n";
                $text .= "IMPORTANT: Do NOT compute or mention distance without user location.\n\n";
            } else {
                // General query without location
                $text .= "NO LOCATION PROVIDED:\n";
                $text .= "User has not provided their location. You should either:\n";
                $text .= "1. Ask the user to share their location for personalized recommendations\n";
                $text .= "2. Provide general guidance about beaches in the requested area\n";
                $text .= "3. Suggest popular beaches if user asks for 'best' or 'popular' beaches\n\n";
                $text .= "IMPORTANT: Do NOT compute or mention distance without user location.\n\n";
            }
        }

        // Weather
        if ($context['weather'] !== null) {
            $weather = $context['weather'];
            $text .= "CURRENT WEATHER:\n";
            $text .= "- Temperature: {$weather['temperature']}°C\n";
            $text .= "- Condition: {$weather['condition']}\n";
            $text .= "- Description: {$weather['description']}\n";
            $text .= "- Wind: {$weather['wind_speed']} km/h\n";
            $text .= "- Humidity: {$weather['humidity']}%\n\n";
        }

        // Available filters
        $text .= "AVAILABLE FILTERS:\n";
        $text .= "- Countries: " . implode(', ', array_column($context['filters_available']['countries'], 'name')) . "\n";
        $text .= "- Surface types: " . implode(', ', $context['filters_available']['surface_types']) . "\n";
        $text .= "- Facilities: " . implode(', ', $context['filters_available']['facilities']) . "\n";
        $text .= "- Accessibility: " . implode(', ', $context['filters_available']['accessibility']) . "\n\n";

        return $text;
    }
}

