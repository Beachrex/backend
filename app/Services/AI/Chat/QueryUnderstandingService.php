<?php

namespace App\Services\AI\Chat;

use App\Http\Middleware\SetLocaleFromHeader;
use App\Services\AI\DTOs\ChatCompletionRequest;
use App\Services\AI\DTOs\ChatCompletionResponse;
use App\Services\AI\DTOs\ChatMessage;
use App\Services\AI\LLMService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * TASK-003: turns a chat message in any language into validated search parameters.
 *
 * One small LLM call with a strict JSON schema (structured output). Attribute codes are enums
 * built from the DB, so the model can only pick codes that exist. Returns null on any failure;
 * the caller then falls back to keyword detection.
 */
class QueryUnderstandingService
{
    public const INTENTS = [
        'beach_search', 'popularity_query', 'proximity_query', 'weather_query',
        'beach_comparison', 'accessibility_query', 'itinerary_request', 'general_question',
    ];

    /** The last ChatCompletionResponse, so the caller can count its tokens. */
    public ?ChatCompletionResponse $lastResponse = null;

    public function __construct(private LLMService $llm) {}

    /**
     * @param  array<int, array{role: string, content: string}>  $history  oldest first
     * @return array{intent: string, place: ?string, country_code: ?string, beach_types: string[], facilities: string[],
     *               user_types: string[], activities: string[], near_me: bool, days: ?int, beach_names: string[], language: ?string}|null
     */
    public function understand(string $message, array $history = []): ?array
    {
        $this->lastResponse = null;
        $codes = $this->codes();

        $messages = [ChatMessage::system($this->systemPrompt())];
        foreach (array_slice($history, -4) as $turn) {
            if (in_array($turn['role'], ['user', 'assistant'], true)) {
                $messages[] = $turn['role'] === 'user' ? ChatMessage::user($turn['content']) : ChatMessage::assistant($turn['content']);
            }
        }
        $messages[] = ChatMessage::user($message);

        try {
            $response = $this->llm->chatCompletion(new ChatCompletionRequest(
                messages: $messages,
                model: config('ai.llm.default_model'),
                temperature: 0.0,
                maxTokens: 300,
                responseFormat: [
                    'type' => 'json_schema',
                    'json_schema' => ['name' => 'beach_query', 'strict' => true, 'schema' => $this->schema($codes)],
                ],
            ));
        } catch (\Throwable $e) {
            Log::warning('Chat query understanding failed', ['error' => $e->getMessage()]);

            return null;
        }
        $this->lastResponse = $response;

        $data = json_decode($response->content, true);
        if (! is_array($data) || ! in_array($data['intent'] ?? null, self::INTENTS, true)) {
            Log::warning('Chat query understanding returned an invalid result', ['content' => mb_substr($response->content, 0, 300)]);

            return null;
        }

        // Defensive: keep only known codes even if the provider ignored the schema.
        $only = fn (string $key, array $allowed) => array_values(array_intersect(
            array_filter((array) ($data[$key] ?? []), 'is_string'), $allowed
        ));
        $string = fn (string $key) => is_string($data[$key] ?? null) && trim($data[$key]) !== '' ? trim($data[$key]) : null;
        $country = strtoupper((string) $string('country_code'));
        $language = $string('language');
        $days = $data['days'] ?? null;

        return [
            'intent' => $data['intent'],
            'place' => $string('place'),
            'country_code' => in_array($country, $codes['countries'], true) ? $country : null,
            'beach_types' => $only('beach_types', $codes['beach_types']),
            'facilities' => $only('facilities', $codes['facilities']),
            'user_types' => $only('user_types', $codes['user_types']),
            'activities' => $only('activities', $codes['activities']),
            'near_me' => (bool) ($data['near_me'] ?? false),
            'days' => is_int($days) && $days > 0 && $days <= 60 ? $days : null,
            'beach_names' => array_values(array_filter((array) ($data['beach_names'] ?? []), fn ($n) => is_string($n) && trim($n) !== '')),
            'language' => in_array($language, SetLocaleFromHeader::SUPPORTED_LOCALES, true) ? $language : null,
        ];
    }

    /**
     * Attribute codes that exist in the DB (cached as plain arrays).
     *
     * @return array{beach_types: string[], facilities: string[], user_types: string[], activities: string[], countries: string[]}
     */
    public function codes(): array
    {
        return Cache::remember('ai:chat:query-codes:v1', 3600, fn () => [
            'beach_types' => $this->column('beach_type'),
            'facilities' => $this->column('location_infrastructure'),
            'user_types' => $this->column('user_type'),
            'activities' => $this->column('sporting_activities'),
            'countries' => DB::table('beach')->where('status_id', 1)->whereNotNull('country_code')
                ->distinct()->orderBy('country_code')->pluck('country_code')->map(fn ($c) => strtoupper($c))->all(),
        ]);
    }

    /** @return string[] */
    private function column(string $table): array
    {
        return DB::table($table)->whereNotNull('code')->orderBy('code')->pluck('code')->unique()->values()->all();
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
You extract search parameters for Beachrex, a beach guide (mostly Croatia and the Adriatic).
The user may write in any language. Never answer the question; only fill the schema.
- intent: what the user wants. "itinerary_request" = a multi-day trip plan; "beach_comparison" = compare named beaches;
  "proximity_query" = near the user's own position ("near me", "blizu mene", "in meiner Nähe");
  "popularity_query" = most popular/visited beaches; "accessibility_query" = wheelchair or reduced mobility;
  "weather_query" = weather or sea temperature; "general_question" = anything else that is not a beach search.
- place: the island, city or region named by the user, in its base form (nominative), without words like "island"/"otok"
  (e.g. "na Braču" → "Brač", "in der Nähe von Makarska" → "Makarska"). null if none.
- country_code: ISO code only if a country is named (e.g. Hrvaška/Kroatien/Croatia → HR).
- beach_types, facilities, user_types, activities: only codes from the lists, only when the user asks for them
  (e.g. sandy → sand; for kids/children/za otroke → family; showers → shower; with a dog → dog_friendly).
- days: the number of days for an itinerary, else null. beach_names: beaches named explicitly, else [].
- language: the language code of the user's last message (en, sl, de, hr, fr, es, it, ua, ru, pl, cs, hu, bg, sk, da, pt, nl, el, sq).
Use the previous messages to resolve follow-ups ("and with a playground?" keeps the earlier place).
PROMPT;
    }

    private function schema(array $codes): array
    {
        $codeList = fn (array $values) => ['type' => 'array', 'items' => ['type' => 'string', 'enum' => array_values($values) ?: ['none']]];

        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['intent', 'place', 'country_code', 'beach_types', 'facilities', 'user_types', 'activities',
                'near_me', 'days', 'beach_names', 'language'],
            'properties' => [
                'intent' => ['type' => 'string', 'enum' => self::INTENTS],
                'place' => ['type' => ['string', 'null']],
                'country_code' => ['type' => ['string', 'null'], 'enum' => [...$codes['countries'], null]],
                'beach_types' => $codeList($codes['beach_types']),
                'facilities' => $codeList($codes['facilities']),
                'user_types' => $codeList($codes['user_types']),
                'activities' => $codeList($codes['activities']),
                'near_me' => ['type' => 'boolean'],
                'days' => ['type' => ['integer', 'null']],
                'beach_names' => ['type' => 'array', 'items' => ['type' => 'string']],
                'language' => ['type' => ['string', 'null'], 'enum' => [...SetLocaleFromHeader::SUPPORTED_LOCALES, null]],
            ],
        ];
    }
}
