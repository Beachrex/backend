<?php

namespace App\Services\AI;

use App\Models\Beach;
use Illuminate\Support\Facades\DB;

/**
 * Itinerary Context Builder Service
 * 
 * Fetches candidate beaches for itinerary generation based on destination and preferences.
 * 
 * Source: docs/ai/implementation/PHASE_1_PROPOSAL.md Section 4 (Suggested Phase 2 scope)
 */
class ItineraryContextBuilderService
{
    /**
     * Fetch candidate beaches for itinerary generation.
     * 
     * Returns beaches ordered by beach_views (visitor interest) when no explicit routing is provided.
     * 
     * @param array $destination Destination details (country_code, region_slug, city_slug)
     * @param array $preferences User preferences (interests, facilities, surface_types, accessibility)
     * @param int $limit Maximum number of beaches to fetch
     * @param string $locale Locale for translations
     * @return array Array of beaches with essential fields
     */
    public function fetchCandidateBeaches(
        array $destination,
        array $preferences,
        int $limit = 30,
        string $locale = 'en'
    ): array {
        // Set locale
        app()->setLocale($locale);

        // Build query
        $query = Beach::query()
            ->where('status_id', 1)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude');

        // Apply destination filters
        $query->where('country_code', $destination['country_code']);

        if (!empty($destination['region_slug'])) {
            $query->whereHas('region', function ($q) use ($destination) {
                $q->where('slug', $destination['region_slug']);
            });
        }

        if (!empty($destination['city_slug'])) {
            $query->whereHas('city', function ($q) use ($destination) {
                $q->where('slug', $destination['city_slug']);
            });
        }

        // Apply preference filters
        if (!empty($preferences['surface_types'])) {
            $query->whereIn('surface', $preferences['surface_types']);
        }

        // Apply facility filters (if must_have_facilities specified)
        if (!empty($preferences['must_have_facilities'])) {
            foreach ($preferences['must_have_facilities'] as $facility) {
                // Map facility names to database columns
                $facilityColumn = $this->mapFacilityToColumn($facility);
                if ($facilityColumn) {
                    $query->where($facilityColumn, true);
                }
            }
        }

        // Apply accessibility filters
        if (!empty($preferences['accessibility'])) {
            foreach ($preferences['accessibility'] as $accessibilityFeature) {
                $accessibilityColumn = $this->mapAccessibilityToColumn($accessibilityFeature);
                if ($accessibilityColumn) {
                    $query->where($accessibilityColumn, true);
                }
            }
        }

        // Order by beach_views (visitor interest) - TRANSPARENT ORDERING
        // This is explicitly documented in assumptions field of response
        $query->orderBy('beach_views', 'desc');

        // Limit results
        $query->limit($limit);

        // Fetch beaches
        $beaches = $query->get();

        // Format beaches for itinerary context
        return $beaches->map(function ($beach) use ($locale) {
            return $this->formatBeachForItinerary($beach, $locale);
        })->toArray();
    }

    /**
     * Format beach data for itinerary generation.
     * 
     * Returns minimal essential fields needed for LLM to generate itinerary.
     * 
     * @param Beach $beach
     * @param string $locale
     * @return array
     */
    private function formatBeachForItinerary(Beach $beach, string $locale): array
    {
        $beachArray = $beach->toArray();

        return [
            'id' => $beach->id,
            'name' => $beach->name ?? null,
            'slug' => $beach->slug ?? null,
            'country_code' => $beach->country_code,
            'city' => $beach->city?->name ?? null,
            'surface' => $beach->surface ?? null,
            'facilities' => $this->extractFacilities($beach),
            'latitude' => $beach->latitude,
            'longitude' => $beach->longitude,
            'beach_views' => $beach->beach_views ?? 0,
            'description' => $beach->description ?? null,
        ];
    }

    /**
     * Extract facilities from beach model.
     * 
     * @param Beach $beach
     * @return array
     */
    private function extractFacilities(Beach $beach): array
    {
        $facilities = [];

        if ($beach->parking) $facilities[] = 'parking';
        if ($beach->showers) $facilities[] = 'showers';
        if ($beach->toilets) $facilities[] = 'toilets';
        if ($beach->lifeguard) $facilities[] = 'lifeguard';
        if ($beach->playground) $facilities[] = 'playground';
        if ($beach->restaurant) $facilities[] = 'restaurant';
        if ($beach->beach_bar) $facilities[] = 'beach_bar';
        if ($beach->sunbeds) $facilities[] = 'sunbeds';
        if ($beach->umbrellas) $facilities[] = 'umbrellas';

        return $facilities;
    }

    /**
     * Map facility name to database column.
     * 
     * @param string $facility
     * @return string|null
     */
    private function mapFacilityToColumn(string $facility): ?string
    {
        $mapping = [
            'parking' => 'parking',
            'showers' => 'showers',
            'toilets' => 'toilets',
            'lifeguard' => 'lifeguard',
            'playground' => 'playground',
            'restaurant' => 'restaurant',
            'beach_bar' => 'beach_bar',
            'sunbeds' => 'sunbeds',
            'umbrellas' => 'umbrellas',
        ];

        return $mapping[$facility] ?? null;
    }

    /**
     * Map accessibility feature to database column.
     * 
     * @param string $feature
     * @return string|null
     */
    private function mapAccessibilityToColumn(string $feature): ?string
    {
        $mapping = [
            'wheelchair_accessible' => 'wheelchair_accessible',
            'ramp_available' => 'ramp_available',
            'accessible_parking' => 'accessible_parking',
            'accessible_toilets' => 'accessible_toilets',
        ];

        return $mapping[$feature] ?? null;
    }

    /**
     * Build context text for LLM prompt.
     * 
     * Formats beaches as text for LLM consumption.
     * 
     * @param array $beaches
     * @param array $destination
     * @param int $durationDays
     * @param array $preferences
     * @return string
     */
    public function buildContextText(
        array $beaches,
        array $destination,
        int $durationDays,
        array $preferences
    ): string {
        $text = "ITINERARY GENERATION CONTEXT\n\n";

        $text .= "DESTINATION:\n";
        $text .= "- Country: {$destination['country_code']}\n";
        if (!empty($destination['region_slug'])) {
            $text .= "- Region: {$destination['region_slug']}\n";
        }
        if (!empty($destination['city_slug'])) {
            $text .= "- City: {$destination['city_slug']}\n";
        }
        $text .= "- Duration: {$durationDays} days\n\n";

        $text .= "PREFERENCES:\n";
        $text .= "- Pace: " . ($preferences['pace'] ?? 'balanced') . "\n";
        if (!empty($preferences['interests'])) {
            $text .= "- Interests: " . implode(', ', $preferences['interests']) . "\n";
        }
        if (!empty($preferences['must_have_facilities'])) {
            $text .= "- Required facilities: " . implode(', ', $preferences['must_have_facilities']) . "\n";
        }
        if (!empty($preferences['surface_types'])) {
            $text .= "- Preferred surfaces: " . implode(', ', $preferences['surface_types']) . "\n";
        }
        $text .= "\n";

        $text .= "CANDIDATE BEACHES (ordered by visitor interest - beach_views):\n";
        $text .= "IMPORTANT: Use transparent language like 'frequently viewed' or 'based on visitor interest'.\n";
        $text .= "DO NOT claim 'best rated' or 'highest rated' unless you have rating data.\n\n";

        foreach ($beaches as $index => $beach) {
            $num = $index + 1;
            $text .= "Beach {$num}:\n";
            $text .= "- ID: {$beach['id']}\n";
            $text .= "- Name: {$beach['name']}\n";
            $text .= "- City: " . ($beach['city'] ?? 'N/A') . "\n";
            $text .= "- Surface: " . ($beach['surface'] ?? 'N/A') . "\n";
            $text .= "- Facilities: " . (empty($beach['facilities']) ? 'None' : implode(', ', $beach['facilities'])) . "\n";
            $text .= "- Views: {$beach['beach_views']}\n";
            if (!empty($beach['description'])) {
                $text .= "- Description: {$beach['description']}\n";
            }
            $text .= "\n";
        }

        return $text;
    }
}

