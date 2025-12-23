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
    private const MAX_BEACHES = [
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
            ],
        ];

        // Fetch beaches if location provided
        if ($latitude !== null && $longitude !== null) {
            $beaches = $this->fetchBeaches($latitude, $longitude, $radius, $tier, $filters, $locale);
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

        // Beaches
        if (!empty($context['beaches'])) {
            $text .= "BEACHES (nearest to your location):\n\n";

            foreach ($context['beaches'] as $index => $beach) {
                $num = $index + 1;
                $text .= "Beach {$num}:\n";
                $text .= "- ID: {$beach['id']}\n";
                $text .= "- Name: {$beach['name']}\n";

                if ($beach['location']['country_name']) {
                    $text .= "- Location: {$beach['location']['country_name']}";
                    if ($beach['location']['distance_km'] !== null) {
                        $text .= " ({$beach['location']['distance_km']} km away)";
                    }
                    $text .= "\n";
                }

                if ($beach['description']) {
                    $text .= "- Description: {$beach['description']}\n";
                }

                $text .= "\n";
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

