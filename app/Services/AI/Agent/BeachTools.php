<?php

namespace App\Services\AI\Agent;

/**
 * The chat agent's tools: the same capabilities as the Beachrex MCP server (mcp-server/src/index.ts), backed
 * by our own API in-process. Every beach in a tool result carries a ready website link, so the model never
 * builds or guesses URLs. Beaches the tools returned are remembered for the answer's beach cards.
 */
class BeachTools
{
    /** Attribute filters accepted by GET /beaches and /spatial/beaches/within-radius (BeachFilterService). */
    public const ATTRIBUTE_FILTERS = [
        'waterbody', 'beach_type', 'user_type', 'location_infrastructure', 'sporting_activity', 'culinary_offer',
        'accommodation_around', 'natural_location_condition', 'underwater_characteristic', 'accessibility',
        'occupancy_rate', 'area_description', 'beach_inclination', 'swimming_depth', 'parking_capacity',
        'recommended_transport_type',
    ];

    /** Localized path word for beach pages; mirrors web-client getBeachesWord() and the MCP's BEACHES_WORD. */
    private const BEACHES_WORD = [
        'en' => 'beaches', 'si' => 'plaze', 'hr' => 'plaze', 'pl' => 'plaze', 'cs' => 'plaze', 'sk' => 'plaze',
        'de' => 'strande', 'fr' => 'plages', 'es' => 'playas', 'it' => 'spiagge', 'ua' => 'plyazhi', 'ru' => 'plyazhi',
        'hu' => 'strandok', 'bg' => 'plyazhove', 'da' => 'straande', 'pt' => 'praias', 'nl' => 'stranden',
        'el' => 'paralies', 'sq' => 'plazhet',
    ];

    /** @var array<int, array> beaches returned by tools in this conversation turn, by id */
    private array $seen = [];

    public function __construct(private readonly InternalApi $api) {}

    /** Provider-neutral definitions: name, description, JSON schema. */
    public function definitions(): array
    {
        $codes = fn (string $what) => ['type' => 'string', 'description' => "{$what}. Comma-separated codes from get_beach_filters; never invent codes."];
        $filters = [];
        foreach (self::ATTRIBUTE_FILTERS as $f) {
            $filters[$f] = $codes(str_replace('_', ' ', ucfirst($f)).' code(s)');
        }
        $filters['parking'] = ['type' => 'boolean', 'description' => 'Only beaches with parking.'];
        $filters['has_swimming_pool'] = ['type' => 'boolean', 'description' => 'Only beaches with a swimming pool.'];

        return [
            [
                'name' => 'find_places_and_beaches',
                'description' => 'Full-text search by NAME for beaches and places (cities, islands, regions). Use it first whenever the user names a place or a beach. Returns beaches with links and places with the exact code to pass to search_beaches (city_code, island_code, region_code).',
                'parameters' => ['type' => 'object', 'properties' => [
                    'query' => ['type' => 'string', 'description' => 'Only the name, e.g. "Makarska" or "Zlatni rat" (not a sentence).'],
                    'country_code' => ['type' => 'string', 'description' => 'Optional ISO 3166-1 alpha-2 code, e.g. HR.'],
                ], 'required' => ['query']],
            ],
            [
                'name' => 'search_beaches',
                'description' => 'List beaches by place codes and attribute filters (most viewed first). Several codes in one filter = ANY; different filters = ALL. Place parameters take CODES from find_places_and_beaches, never names.',
                'parameters' => ['type' => 'object', 'properties' => array_merge([
                    'country_code' => ['type' => 'string', 'description' => 'ISO 3166-1 alpha-2, e.g. HR, SI, IT.'],
                    'region_code' => ['type' => 'string'],
                    'island_code' => ['type' => 'string'],
                    'city_code' => ['type' => 'string'],
                    'limit' => ['type' => 'integer', 'description' => '1-30, default 10.'],
                ], $filters)],
            ],
            [
                'name' => 'find_beaches_nearby',
                'description' => 'Beaches within a radius of coordinates, nearest first. Use when the user shares a location or asks for beaches around a known point.',
                'parameters' => ['type' => 'object', 'properties' => array_merge([
                    'latitude' => ['type' => 'number'],
                    'longitude' => ['type' => 'number'],
                    'radius_km' => ['type' => 'number', 'description' => 'Default 25, max 200.'],
                    'limit' => ['type' => 'integer', 'description' => '1-30, default 10.'],
                ], $filters), 'required' => ['latitude', 'longitude']],
            ],
            [
                'name' => 'get_beach_details',
                'description' => 'Full facts about one beach (description, type, facilities, activities, who it suits, access, parking) by its id.',
                'parameters' => ['type' => 'object', 'properties' => [
                    'id' => ['type' => 'integer', 'description' => 'Beach id from another tool.'],
                ], 'required' => ['id']],
            ],
            [
                'name' => 'get_beach_filters',
                'description' => 'The valid filter codes (beach type, facilities, who it suits, activities, waterbody, ...) with their names in the user\'s language. Call it before using attribute filters.',
                'parameters' => ['type' => 'object', 'properties' => [
                    'country_code' => ['type' => 'string', 'description' => 'Optional; limits options to one country.'],
                ]],
            ],
            [
                'name' => 'get_beach_weather',
                'description' => 'Current weather (and sea temperature when known) at one beach, by id. If unavailable, say so; never guess.',
                'parameters' => ['type' => 'object', 'properties' => [
                    'id' => ['type' => 'integer'],
                ], 'required' => ['id']],
            ],
        ];
    }

    /** Runs a tool and returns text for the model (errors are text too, so the model can recover). */
    public function run(string $name, array $input, string $locale): string
    {
        try {
            return match ($name) {
                'find_places_and_beaches' => $this->findByName($input, $locale),
                'search_beaches' => $this->searchBeaches($input, $locale),
                'find_beaches_nearby' => $this->nearby($input, $locale),
                'get_beach_details' => $this->details($input, $locale),
                'get_beach_filters' => $this->filters($input, $locale),
                'get_beach_weather' => $this->weather($input, $locale),
                default => "Unknown tool {$name}.",
            };
        } catch (\Throwable $e) {
            report($e);

            return "The tool {$name} failed. Answer with what you already know or apologise briefly.";
        }
    }

    /** Beaches returned by tools whose link appears in the answer, in answer order (max 6). */
    public function beachesLinkedIn(string $answer): array
    {
        $found = [];
        foreach ($this->seen as $beach) {
            $pos = $beach['url'] !== '' ? mb_strpos($answer, $beach['url']) : false;
            if ($pos !== false) {
                $found[$pos] = $beach;
            }
        }
        ksort($found);

        return array_slice(array_values($found), 0, 6);
    }

    public function beachUrl(array $b, string $locale): string
    {
        if (empty($b['continent_slug']) || empty($b['country_slug']) || empty($b['slug'])) {
            return '';
        }
        $parts = array_filter([$b['continent_slug'], $b['country_slug'], $b['region_slug'] ?? null, $b['island_slug'] ?? null, $b['city_slug'] ?? null, $b['slug']],
            fn ($p) => is_string($p) && $p !== '');

        return rtrim((string) config('app.frontend_url'), '/').'/'.$locale.'/'.(self::BEACHES_WORD[$locale] ?? 'beaches').'/'.implode('/', $parts);
    }

    private function remember(array $b, string $locale): array
    {
        $card = [
            'id' => (int) $b['id'],
            'name' => (string) ($b['name'] ?? ''),
            'slug' => $b['slug'] ?? null,
            'country_code' => $b['country_code'] ?? null,
            'city' => $b['city_name'] ?? $b['city'] ?? null,
            'main_image' => $b['main_image'] ?? null,
            'continent_slug' => $b['continent_slug'] ?? null,
            'country_slug' => $b['country_slug'] ?? null,
            'region_slug' => $b['region_slug'] ?? null,
            'island_slug' => $b['island_slug'] ?? null,
            'city_slug' => $b['city_slug'] ?? null,
            'distance_km' => isset($b['distance_km']) ? round((float) $b['distance_km'], 1) : null,
        ];
        $card['url'] = $this->beachUrl($card, $locale);
        $this->seen[$card['id']] = ($this->seen[$card['id']] ?? []) + $card;

        return $card;
    }

    private function beachLine(array $b, string $locale): string
    {
        $c = $this->remember($b, $locale);
        $place = implode(', ', array_filter([$b['city_name'] ?? null, $b['island_name'] ?? null, $b['region_name'] ?? null, $b['country_name'] ?? $b['country_code'] ?? null]));
        $extra = [];
        if ($c['distance_km'] !== null) {
            $extra[] = "{$c['distance_km']} km";
        }
        if (! empty($b['tags']) && is_array($b['tags'])) {
            $extra[] = implode(', ', array_slice(array_filter($b['tags'], 'is_string'), 0, 4));
        }

        return "- {$c['name']} (id {$c['id']})".($place ? " — {$place}" : '').($extra ? ' — '.implode(' — ', $extra) : '')
            .($c['url'] ? "\n  link: {$c['url']}" : '');
    }

    private function list(array $beaches, string $locale, int $limit, string $empty): string
    {
        if (! $beaches) {
            return $empty;
        }
        $lines = array_map(fn ($b) => $this->beachLine($b, $locale), $beaches);
        $more = count($beaches) >= $limit ? "\n(First {$limit} results, most viewed first; add filters for a narrower list.)" : '';

        return implode("\n", $lines).$more;
    }

    private function attributeQuery(array $input): array
    {
        $q = [];
        foreach (self::ATTRIBUTE_FILTERS as $f) {
            if (! empty($input[$f])) {
                $q[$f] = is_array($input[$f]) ? implode(',', $input[$f]) : (string) $input[$f];
            }
        }
        foreach (['parking', 'has_swimming_pool'] as $b) {
            if (isset($input[$b]) && is_bool($input[$b])) {
                $q[$b] = $input[$b] ? 1 : 0;
            }
        }

        return $q;
    }

    private function limit(array $input): int
    {
        return max(1, min(30, (int) ($input['limit'] ?? 10)));
    }

    private function findByName(array $input, string $locale): string
    {
        $query = trim((string) ($input['query'] ?? ''));
        if ($query === '') {
            return 'Give a name to search for.';
        }
        $body = $this->api->get('search', [
            'q' => $query, 'limit' => 8, 'types' => 'beach,city,island,region',
            'country_code' => isset($input['country_code']) ? strtoupper((string) $input['country_code']) : null,
        ], $locale)['body'];
        $data = $body['data'] ?? [];

        $out = [];
        if (! empty($data['beaches'])) {
            $out[] = "BEACHES:\n".$this->list($data['beaches'], $locale, 99, '');
        }
        foreach (['cities' => 'city_code', 'islands' => 'island_code', 'regions' => 'region_code'] as $key => $param) {
            if (! empty($data[$key])) {
                $out[] = strtoupper($key).":\n".implode("\n", array_map(
                    fn ($p) => "- {$p['name']}".(isset($p['beaches_count']) ? " ({$p['beaches_count']} beaches)" : '')." → search_beaches {$param}=\"{$p['code']}\"",
                    $data[$key]
                ));
            }
        }

        return $out ? implode("\n\n", $out) : "Nothing found for \"{$query}\". Try another spelling, only the name, or search by country with search_beaches.";
    }

    private function searchBeaches(array $input, string $locale): string
    {
        $limit = $this->limit($input);
        $query = array_merge([
            'limit' => $limit,
            'country_code' => isset($input['country_code']) ? strtoupper((string) $input['country_code']) : null,
            'region_code' => $input['region_code'] ?? null,
            'island_code' => $input['island_code'] ?? null,
            'city_code' => $input['city_code'] ?? null,
        ], $this->attributeQuery($input));
        $res = $this->api->get('beaches', $query, $locale);
        if ($res['status'] === 422) {
            return 'Invalid filter value: '.json_encode($res['body']['errors'] ?? $res['body']).'. Check codes with get_beach_filters.';
        }

        return $this->list($res['body']['data'] ?? [], $locale, $limit, 'No beach matches these filters. Remove the least important filter and try again.');
    }

    private function nearby(array $input, string $locale): string
    {
        $limit = $this->limit($input);
        $res = $this->api->get('spatial/beaches/within-radius', array_merge([
            'latitude' => (float) ($input['latitude'] ?? 0),
            'longitude' => (float) ($input['longitude'] ?? 0),
            'radius' => max(0.5, min(200.0, (float) ($input['radius_km'] ?? 25))),
            'limit' => $limit,
        ], $this->attributeQuery($input)), $locale);

        return $this->list($res['body']['data'] ?? [], $locale, $limit, 'No beach within this radius. Try a larger radius_km.');
    }

    private function details(array $input, string $locale): string
    {
        $res = $this->api->get('beach-details/'.(int) ($input['id'] ?? 0), [], $locale);
        if ($res['status'] !== 200) {
            return 'Beach not found.';
        }
        $d = $res['body']['data'] ?? $res['body'];
        $basic = $d['basic_info'] ?? [];
        $loc = $d['location'] ?? [];
        $card = $this->remember([
            'id' => $basic['id'] ?? $input['id'], 'name' => $basic['name'] ?? '', 'slug' => $basic['slug'] ?? null,
            'country_code' => $loc['country']['code'] ?? null, 'city_name' => $loc['city']['name'] ?? null,
            'main_image' => $d['media']['gallery'][0]['url'] ?? null,
            'continent_slug' => $loc['continent']['slug'] ?? null, 'country_slug' => $loc['country']['slug'] ?? null,
            'region_slug' => $loc['region']['slug'] ?? null, 'island_slug' => $loc['island']['slug'] ?? null,
            'city_slug' => $loc['city']['slug'] ?? null,
        ], $locale);

        $text = trim(preg_replace('/\s+/', ' ', strip_tags((string) ($basic['description'] ?? $d['description'] ?? ''))));
        unset($d['media'], $d['basic_info']['description'], $d['description'], $d['seo'], $d['previous_beach'], $d['next_beach']);
        $facts = json_encode($d, JSON_UNESCAPED_UNICODE);

        return "{$card['name']} (id {$card['id']})\nlink: {$card['url']}\n"
            .($text !== '' ? 'Description: '.mb_substr($text, 0, 1500)."\n" : "Description: none in the database.\n")
            .'Facts (JSON): '.mb_substr((string) $facts, 0, 4000);
    }

    private function filters(array $input, string $locale): string
    {
        $body = $this->api->get('beaches/filters', [
            'country_code' => isset($input['country_code']) ? strtoupper((string) $input['country_code']) : null,
        ], $locale)['body'];
        $groups = $body['data'] ?? [];
        $param = ['sporting_activities' => 'sporting_activity', 'swimming_depth_of_one_m' => 'swimming_depth'];

        $out = [];
        foreach ($groups as $key => $group) {
            $p = $param[$key] ?? $key;
            if (! in_array($p, self::ATTRIBUTE_FILTERS, true) || empty($group['options'])) {
                continue;
            }
            $opts = implode(', ', array_map(fn ($o) => ($o['code'] ?? '?').': '.($o['name'] ?? ''), array_slice($group['options'], 0, 60)));
            $out[] = ($group['title'] ?? $group['name'] ?? $key)." → parameter \"{$p}\": {$opts}";
        }

        return $out ? implode("\n", $out) : 'No filters available.';
    }

    private function weather(array $input, string $locale): string
    {
        $res = $this->api->get('beach-details/'.(int) ($input['id'] ?? 0).'/weather', [], $locale);
        if ($res['status'] !== 200) {
            return 'Weather is unavailable right now. Say so; do not guess.';
        }

        return 'Weather (JSON): '.mb_substr((string) json_encode($res['body']['data'] ?? $res['body'], JSON_UNESCAPED_UNICODE), 0, 1500);
    }
}
