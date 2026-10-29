<?php

namespace App\Services\AI;

use App\Http\Requests\AI\ItineraryRequest;
use App\Models\AiMessage;
use App\Models\Beach;
use App\Models\User;
use App\Models\AiSession;
use App\Models\AiUsage;
use App\Services\AI\DTOs\ChatCompletionRequest;
use App\Services\AI\DTOs\ChatCompletionResponse;
use App\Services\AI\DTOs\ChatMessage;
use App\Services\AI\Itinerary\ItineraryPlanner;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Multi-day beach itineraries (TASK-001, ADR 2026-10-07 "code plans, LLM narrates").
 *
 * 1. Candidate beaches from real attributes (ItineraryContextBuilderService).
 * 2. Geographic day skeleton: clusters, order, distances (ItineraryPlanner).
 * 3. The LLM writes only the narrative (JSON mode) around the fixed skeleton.
 * 4. Merge: beach facts and distances always come from the DB, never from the LLM.
 */
class ItineraryService
{
    /** Locale code → language name for the prompt ("Respond in Slovenian", not "in sl"). */
    public const LANGUAGE_NAMES = [
        'en' => 'English', 'sl' => 'Slovenian', 'de' => 'German', 'hr' => 'Croatian', 'fr' => 'French',
        'es' => 'Spanish', 'it' => 'Italian', 'ua' => 'Ukrainian', 'ru' => 'Russian', 'pl' => 'Polish',
        'cs' => 'Czech', 'hu' => 'Hungarian', 'bg' => 'Bulgarian', 'sk' => 'Slovak', 'da' => 'Danish',
        'pt' => 'Portuguese', 'nl' => 'Dutch', 'el' => 'Greek', 'sq' => 'Albanian',
    ];

    private const TIME_BLOCKS = ['morning', 'afternoon', 'evening'];

    public function __construct(
        private readonly ItineraryContextBuilderService $contextBuilder,
        private readonly LLMService $llmService,
        private readonly LoggingService $loggingService,
        private readonly ItineraryPlanner $planner,
    ) {}

    /**
     * @throws Itinerary\NotEnoughBeachesException when too few beaches match (controller → 422)
     */
    public function generate(ItineraryRequest $request, string $requestId): array
    {
        return $this->generateFromInput($request->validated(), $request->user(), $requestId);
    }

    /**
     * AI-3: the same generation without an HTTP request, so a queued job can run it. $input is the validated
     * ItineraryRequest data; the page language comes from app()->getLocale() unless $input['locale'] is set.
     */
    public function generateFromInput(array $input, ?User $user, string $requestId): array
    {
        $startTime = microtime(true);
        $spendMark = AiSpendMeter::current()->mark();

        $destination = $input['destination'];
        $days = (int) $input['duration_days'];
        $preferences = $input['preferences'] ?? [];
        $constraints = $input['constraints'] ?? [];
        $travelParty = $input['travel_party'] ?? [];
        $locale = $input['locale'] ?? app()->getLocale();
        $pace = $preferences['pace'] ?? 'balanced';
        $maxKm = (int) ($constraints['max_travel_km_per_day'] ?? 80);

        $session = $this->getOrCreateSession($input['session_id'] ?? null, $user);

        // 1. Candidates
        $candidates = $this->contextBuilder->fetchCandidateBeaches($destination, $preferences, $constraints, $travelParty, $locale, $days);
        $beachesById = collect($candidates['beaches'])->keyBy('id');

        // 2. Skeleton (throws NotEnoughBeachesException)
        $skeleton = $this->planner->plan($candidates['beaches'], $days, $pace, $maxKm);

        // 3. Narrative
        [$narrative, $llmResponse, $narrativeSource] = $this->narrate($skeleton, $beachesById->all(), $input, $locale);

        // 4. Merge
        $itinerary = $this->merge($skeleton, $beachesById->all(), $narrative, $destination, $days, $candidates, $constraints);
        $itinerary = $this->withPageLinks($itinerary, $locale);
        $itinerary['itinerary_id'] = (string) Str::uuid();
        $itinerary['session_id'] = $session->id;
        $itinerary['metadata'] = [
            'request_id' => $requestId,
            'tier' => $user ? 'basic' : 'free',
            'tokens_used' => $llmResponse?->totalTokens ?? 0,
            'model' => $llmResponse?->model,
            'narrative' => $narrativeSource,
            'candidates_considered' => count($candidates['beaches']),
            'response_time_ms' => (int) round((microtime(true) - $startTime) * 1000),
            'cached' => false,
        ];

        $this->saveItineraryToDatabase($session, $input, $itinerary, $llmResponse, AiSpendMeter::current()->since($spendMark));

        return $itinerary;
    }

    /**
     * @return array{0: array, 1: ?ChatCompletionResponse, 2: string} narrative, last LLM response, "llm"|"fallback"
     */
    private function narrate(array $skeleton, array $beachesById, array $input, string $locale): array
    {
        $messages = [
            ChatMessage::system($this->systemPrompt($locale)),
            ChatMessage::user($this->userPrompt($skeleton, $beachesById, $input)),
        ];
        $days = count($skeleton);
        $lastResponse = null;

        for ($attempt = 1; $attempt <= 2; $attempt++) {
            try {
                $lastResponse = $this->llmService->chatCompletion(new ChatCompletionRequest(
                    messages: $messages,
                    temperature: 0.6,
                    maxTokens: min(8000, 800 + 450 * $days),
                    responseFormat: ['type' => 'json_object'],
                ));

                if ($lastResponse->finishReason === 'length') {
                    throw new \RuntimeException('LLM output truncated (finish_reason=length)');
                }

                $decoded = json_decode(
                    preg_replace('/^```(?:json)?\s*|\s*```$/', '', trim($lastResponse->content)),
                    true
                );
                if (!is_array($decoded) || !isset($decoded['days']) || !is_array($decoded['days'])) {
                    throw new \RuntimeException('LLM narrative is not valid JSON with a days array');
                }

                return [$decoded, $lastResponse, 'llm'];
            } catch (Throwable $e) {
                Log::warning('Itinerary narrative attempt failed', ['attempt' => $attempt, 'error' => $e->getMessage()]);
            }
        }

        // Loud (logged + metadata.narrative="fallback") but the paid plan is still delivered.
        Log::error('Itinerary narrative fell back to deterministic text after 2 attempts');

        return [[], $lastResponse, 'fallback'];
    }

    /**
     * AI-3: every beach in the plan gets what the website needs for its page link and photo (place slugs in the
     * page language, main image), so a bought plan links straight to the beach pages.
     */
    private function withPageLinks(array $itinerary, string $locale): array
    {
        $ids = [];
        foreach ($itinerary['days'] as $day) {
            foreach ($day['items'] as $item) {
                if (isset($item['beach']['id'])) {
                    $ids[] = (int) $item['beach']['id'];
                }
            }
        }
        if (! $ids) {
            return $itinerary;
        }

        $locales = array_values(array_unique([$locale, 'en']));
        $tr = fn ($q) => $q->whereIn('locale', $locales);
        $beaches = Beach::query()->whereIn('id', array_unique($ids))->with([
            'translations' => $tr, 'continent.translations' => $tr, 'country.translations' => $tr,
            'region.translations' => $tr, 'island.translations' => $tr, 'city.translations' => $tr,
            'media' => fn ($q) => $q->where('status_id', 1)->orderBy('is_primary', 'desc')->orderBy('order')->orderBy('created_at'),
        ])->get()->keyBy('id');

        $slug = function ($model) use ($locale): ?string {
            $translations = $model?->translations;
            if (! $translations) {
                return null;
            }

            return ($translations->firstWhere('locale', $locale) ?? $translations->firstWhere('locale', 'en'))?->slug;
        };

        foreach ($itinerary['days'] as &$day) {
            foreach ($day['items'] as &$item) {
                $beach = isset($item['beach']['id']) ? $beaches->get((int) $item['beach']['id']) : null;
                if (! $beach) {
                    continue;
                }
                $item['beach'] += [
                    'continent_slug' => $slug($beach->continent),
                    'country_slug' => $slug($beach->country),
                    'region_slug' => $slug($beach->region),
                    'island_slug' => $slug($beach->island),
                    'city_slug' => $slug($beach->city),
                    'main_image' => $beach->media->first()?->file_path,
                ];
            }
            unset($item);
        }
        unset($day);

        return $itinerary;
    }

    private function systemPrompt(string $locale): string
    {
        $language = self::LANGUAGE_NAMES[$locale] ?? 'English';

        return <<<PROMPT
You are an expert beach-trip writer for Beachrex, a beach discovery platform.
The trip plan is ALREADY FIXED: which beaches are visited on which day, in which order, and the distances.
Your job is to write the text around it. Do not add, remove, reorder or invent beaches.

Rules:
- Write everything in {$language}.
- Use only the facts given for each beach (type, facilities, suitable for, activities, description). Never invent facilities, ratings, prices, opening hours or reviews.
- You may suggest at most one generic extra per day (food, activity or viewpoint) without naming specific businesses.
- If facts are missing for a beach, say less, not more.

Return one JSON object:
{
  "title": string,
  "summary": string (2-4 sentences),
  "days": [
    {
      "day_number": integer,
      "theme": string (short),
      "beaches": [ { "beach_id": integer, "time_block": "morning"|"afternoon"|"evening", "notes": string (1-3 sentences), "why": string (short) } ],
      "extra": { "type": "food"|"activity"|"viewpoint", "time_block": "morning"|"afternoon"|"evening", "notes": string } | null,
      "logistics_note": string (one sentence)
    }
  ],
  "assumptions": [string]
}
PROMPT;
    }

    private function userPrompt(array $skeleton, array $beachesById, array $input): string
    {
        $preferences = $input['preferences'] ?? [];
        $party = $input['travel_party'] ?? [];
        $lines = [];
        $lines[] = 'Trip: ' . count($skeleton) . ' days, country ' . $input['destination']['country_code']
            . (! empty($input['start_date']) ? ', starting ' . $input['start_date'] : '') . '.';
        $lines[] = 'Travellers: ' . (int) ($party['adults'] ?? 2) . ' adults, ' . (int) ($party['children'] ?? 0) . ' children.';
        $lines[] = 'Pace: ' . ($preferences['pace'] ?? 'balanced') . '. Interests: ' . (implode(', ', $preferences['interests'] ?? []) ?: 'none given') . '.';
        $lines[] = '';
        $lines[] = 'FIXED PLAN (distances are straight-line km from the previous stop):';

        foreach ($skeleton as $i => $day) {
            $lines[] = '';
            $lines[] = 'Day ' . ($i + 1) . ' — total ~' . $day['total_km'] . ' km';
            foreach ($day['beach_ids'] as $j => $id) {
                $b = $beachesById[$id];
                $facts = array_filter([
                    $b['type'] ? 'type: ' . $b['type'] : null,
                    $b['facilities'] ? 'facilities: ' . implode(', ', $b['facilities']) : null,
                    $b['suitable_for'] ? 'suitable for: ' . implode(', ', $b['suitable_for']) : null,
                    $b['activities'] ? 'activities: ' . implode(', ', $b['activities']) : null,
                    $b['matched_interests'] ? 'matches interests: ' . implode(', ', $b['matched_interests']) : null,
                    $day['legs_km'][$j] !== null ? 'distance from previous: ' . $day['legs_km'][$j] . ' km' : null,
                    $b['description'] ? 'description: ' . $b['description'] : null,
                ]);
                $lines[] = "- beach_id {$id}: {$b['name']}" . ($b['city'] ? " ({$b['city']})" : '') . ' | ' . implode(' | ', $facts);
            }
        }

        return implode("\n", $lines);
    }

    /**
     * Merge LLM text into the fixed skeleton. The response keeps the documented schema.
     */
    private function merge(array $skeleton, array $beachesById, array $narrative, array $destination, int $days, array $candidates, array $constraints): array
    {
        $narrativeDays = collect($narrative['days'] ?? [])->keyBy(fn ($d) => (int) ($d['day_number'] ?? 0));
        $resultDays = [];
        $ignoredIds = [];

        foreach ($skeleton as $i => $day) {
            $dayNumber = $i + 1;
            $text = $narrativeDays->get($dayNumber, []);
            $textByBeach = collect($text['beaches'] ?? [])->keyBy(fn ($b) => (int) ($b['beach_id'] ?? 0));
            $ignoredIds = array_merge($ignoredIds, array_diff($textByBeach->keys()->all(), $day['beach_ids']));

            $items = [];
            foreach ($day['beach_ids'] as $j => $id) {
                $b = $beachesById[$id];
                $t = $textByBeach->get($id, []);
                $items[] = [
                    'type' => 'beach',
                    'time_block' => in_array($t['time_block'] ?? null, self::TIME_BLOCKS, true)
                        ? $t['time_block']
                        : self::TIME_BLOCKS[min($j, 2)],
                    'beach' => [
                        'id' => $b['id'],
                        'name' => $b['name'],
                        'slug' => $b['slug'],
                        'city' => $b['city'],
                        'country_code' => $b['country_code'],
                        'surface' => $b['type'],
                        'type' => $b['type_code'],
                        'facilities' => $b['facilities'],
                        'suitable_for' => $b['suitable_for'],
                        'latitude' => $b['latitude'],
                        'longitude' => $b['longitude'],
                        'distance_km' => $day['legs_km'][$j],
                    ],
                    'notes' => $this->str($t['notes'] ?? null) ?? $this->factsSentence($b),
                    'why' => $this->str($t['why'] ?? null) ?? ($b['matched_interests']
                        ? 'Matches: ' . implode(', ', $b['matched_interests'])
                        : 'Frequently viewed on Beachrex'),
                ];
            }

            $extra = $text['extra'] ?? null;
            if (is_array($extra) && in_array($extra['type'] ?? null, ['food', 'activity', 'viewpoint'], true) && $this->str($extra['notes'] ?? null)) {
                $items[] = [
                    'type' => $extra['type'],
                    'time_block' => in_array($extra['time_block'] ?? null, self::TIME_BLOCKS, true) ? $extra['time_block'] : 'evening',
                    'notes' => $this->str($extra['notes']),
                    'why' => null,
                ];
            }

            $dayBeaches = array_map(fn ($id) => $beachesById[$id], $day['beach_ids']);
            $resultDays[] = [
                'day_number' => $dayNumber,
                'theme' => $this->str($text['theme'] ?? null) ?? ($dayBeaches[0]['city'] ?? $dayBeaches[0]['region'] ?? "Day {$dayNumber}"),
                'base_area' => $dayBeaches[0]['city'] ?? $dayBeaches[0]['region'],
                'items' => $items,
                'logistics' => [
                    'estimated_travel_km' => $day['total_km'],
                    'parking_notes' => $this->parkingNote($dayBeaches),
                    'accessibility_notes' => $this->accessibilityNote($dayBeaches),
                    'notes' => $this->str($text['logistics_note'] ?? null),
                ],
            ];
        }

        if ($ignoredIds) {
            Log::warning('Itinerary narrative referenced beaches outside the plan; ignored', ['beach_ids' => array_values($ignoredIds)]);
        }

        return [
            'title' => $this->str($narrative['title'] ?? null) ?? "{$days}-day beach trip ({$destination['country_code']})",
            'summary' => $this->str($narrative['summary'] ?? null) ?? "A {$days}-day plan with " . count($beachesById) . ' candidate beaches considered.',
            'destination' => [
                'country_code' => $destination['country_code'],
                'region_slug' => $destination['region_slug'] ?? null,
                'city_slug' => $destination['city_slug'] ?? null,
            ],
            'duration_days' => $days,
            'days' => $resultDays,
            'assumptions' => array_values(array_filter(array_merge(
                $this->systemAssumptions($candidates, $constraints),
                array_map(fn ($a) => $this->str($a), array_slice($narrative['assumptions'] ?? [], 0, 5))
            ))),
        ];
    }

    private function systemAssumptions(array $candidates, array $constraints): array
    {
        $a = [
            'Beaches are grouped by day by location; distances are straight-line estimates, not road or ferry distances.',
            'Beach facts (type, facilities, suitability) come from the Beachrex database.',
        ];
        if (in_array('facilities', $candidates['relaxed'], true)) {
            $a[] = 'Too few beaches had all requested facilities; facility requirements were relaxed.';
        }
        if (in_array('surface', $candidates['relaxed'], true)) {
            $a[] = 'Too few beaches had the requested surface; the surface preference was relaxed.';
        }
        if ($candidates['unmapped_facilities']) {
            $a[] = 'Not recognised as filters: ' . implode(', ', $candidates['unmapped_facilities']) . '.';
        }
        if (!empty($constraints['avoid_ferries'])) {
            $a[] = 'Island beaches were excluded to avoid ferries (this also excludes islands reachable by bridge).';
        }

        return $a;
    }

    private function factsSentence(array $b): string
    {
        $parts = array_filter([
            $b['type'],
            $b['facilities'] ? implode(', ', array_slice($b['facilities'], 0, 4)) : null,
        ]);

        return $parts ? implode(' · ', $parts) : ($b['city'] ? "Beach near {$b['city']}." : 'Beach on your route.');
    }

    private function parkingNote(array $beaches): ?string
    {
        $with = array_column(array_filter($beaches, fn ($b) => $b['has_parking']), 'name');

        return $with ? 'Parking listed at: ' . implode(', ', $with) . '.' : 'No parking listed for these beaches.';
    }

    private function accessibilityNote(array $beaches): ?string
    {
        $with = array_column(array_filter($beaches, fn ($b) => $b['accessible']), 'name');

        return $with ? 'Access for disabled listed at: ' . implode(', ', $with) . '.' : null;
    }

    private function str(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    private function getOrCreateSession(?string $sessionId, $user): AiSession
    {
        if ($sessionId && ($session = AiSession::find($sessionId))) {
            return $session;
        }

        return AiSession::create([
            'id' => (string) Str::uuid(),
            'user_id' => $user?->id,
            'tier' => $user ? 'basic' : 'free',
            'locale' => app()->getLocale(),
        ]);
    }

    /**
     * @param array{cost: float, in: int, out: int} $spent every LLM call of this plan (also a failed first
     *        narration attempt, whose tokens were billed too)
     */
    private function saveItineraryToDatabase(AiSession $session, array $input, array $itinerary, ?ChatCompletionResponse $llm, array $spent = ['cost' => 0.0, 'in' => 0, 'out' => 0]): void
    {
        AiMessage::create([
            'session_id' => $session->id,
            'role' => 'user',
            'content' => json_encode($input),
            'intent' => 'itinerary',
            'context' => [
                'destination' => $input['destination'],
                'duration_days' => $input['duration_days'],
            ],
            'tokens_input' => $llm?->inputTokens ?? 0,
            'tokens_output' => 0,
            'tokens_total' => $llm?->inputTokens ?? 0,
        ]);

        AiMessage::create([
            'session_id' => $session->id,
            'role' => 'assistant',
            'content' => json_encode($itinerary),
            'intent' => 'itinerary',
            'context' => ['itinerary_id' => $itinerary['itinerary_id']],
            'tokens_input' => 0,
            'tokens_output' => $llm?->outputTokens ?? 0,
            'tokens_total' => $llm?->outputTokens ?? 0,
        ]);

        $beachCount = array_sum(array_map(
            fn ($d) => count(array_filter($d['items'], fn ($i) => $i['type'] === 'beach')),
            $itinerary['days']
        ));

        AiUsage::create([
            'user_id' => $session->user_id,
            'session_id' => $session->id,
            'tier' => $session->tier,
            'event_type' => 'itinerary',
            'llm_provider' => $llm?->provider ?? 'openai',
            'llm_model' => $llm?->model ?? 'none',
            'tokens_input' => max($llm?->inputTokens ?? 0, $spent['in']),
            'tokens_output' => max($llm?->outputTokens ?? 0, $spent['out']),
            'tokens_total' => max($llm?->totalTokens ?? 0, $spent['in'] + $spent['out']),
            'cost_usd' => max($llm?->costUsd ?? 0, $spent['cost']),
            'latency_ms' => $itinerary['metadata']['response_time_ms'] ?? 0,
            'cache_hit' => false,
            'beaches_fetched' => $itinerary['metadata']['candidates_considered'] ?? 0,
            'beaches_returned' => $beachCount,
            'context_truncated' => false,
            'locale' => $session->locale,
            'user_agent' => $session->user_agent,
            'ip_address' => $session->ip_address,
            'request_id' => $itinerary['metadata']['request_id'],
        ]);
    }
}
