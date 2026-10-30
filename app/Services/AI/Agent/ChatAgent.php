<?php

namespace App\Services\AI\Agent;

use App\Services\AI\AiModelCatalog;
use App\Services\AI\ItineraryService;

/**
 * AI-2 (owner 2026-10-09): the website chat works like the MCP server in Claude — the model calls beach
 * tools (search by name/place/filters, nearby, details, filters, weather) as often as it needs, and every
 * beach it recommends is linked with the exact link a tool returned.
 */
class ChatAgent
{
    /** Kept byte-stable (no dates, no per-request data) so providers can cache it. */
    public const SYSTEM_PROMPT = <<<'PROMPT'
You are the beach assistant of Beachrex (beachrex.com), a guide to thousands of beaches with facts, photos and maps.
Help the user find beaches that fit what they want.

How to work:
- Get every fact about beaches from the tools. Never invent beaches, facilities, prices, opening hours, ratings or links.
- When the user names a place or a beach, call find_places_and_beaches first; then call search_beaches with the code it returns (city_code, island_code, region_code), not with a name.
- For wishes like sand, families, dogs, parking, snorkelling or wheelchair access, call get_beach_filters to get the codes, then filter with them.
- Before saying that a beach has a specific facility or feature, check it in the tool results or with get_beach_details.
- If the user shared their location, use find_beaches_nearby.

How to answer:
- Answer in the language named in the context line of the user's message.
- Recommend 1-5 beaches. Link each recommended beach name with the exact link from the tool result, as markdown: [Beach name](link). Never write other URLs.
- Say in one short sentence why each beach fits. Keep the whole answer short (about 120 words plus the list), friendly and concrete. No headings, no tables; do not mention tools or ids.
- If nothing fits, say so honestly and ask one short question to narrow the search.
- For a plan over several days, suggest a few beaches and mention the Beachrex AI itinerary planner for a full day-by-day plan.
PROMPT;

    /** API locale (Accept-Language after SetLocaleFromHeader) → website locale in URLs. */
    private const WEB_LOCALES = ['sl' => 'si'];

    public function __construct(private readonly InternalApi $api) {}

    /**
     * @param array<int, array{role: string, content: string}> $history
     * @param array{latitude: float, longitude: float}|null $location
     * @param string|null $pageHint TASK-010: server-built line about the page the user is on (PageContextHint)
     * @param AgentModel|null $model a specific model (admin "test this model"); null = the chat's model
     * @return array{message: string, beaches: array, result: AgentResult}
     */
    public function answer(string $message, array $history, string $apiLocale, ?array $location, ?string $pageHint = null, ?AgentModel $model = null): array
    {
        $tools = new BeachTools($this->api);
        $webLocale = self::WEB_LOCALES[$apiLocale] ?? $apiLocale;
        $language = ItineraryService::LANGUAGE_NAMES[$apiLocale] ?? 'English';

        $context = "[Context: website language {$language}; answer in the language the user writes in (default {$language}).";
        if ($location) {
            $context .= " The user shared their location: latitude {$location['latitude']}, longitude {$location['longitude']}.";
        }
        if ($pageHint !== null && $pageHint !== '') {
            $context .= ' '.$pageHint;
        }
        $context .= ']';

        $result = ($model ?? $this->model())->converse(
            self::SYSTEM_PROMPT,
            array_values(array_filter($history, fn ($t) => in_array($t['role'] ?? '', ['user', 'assistant'], true) && ($t['content'] ?? '') !== '')),
            $context."\n".$message,
            $tools->definitions(),
            fn (string $name, array $input) => $tools->run($name, $input, $webLocale),
            (int) config('ai.chat.max_tool_rounds', 4),
        );

        return ['message' => $result->text, 'beaches' => $tools->beachesLinkedIn($result->text), 'result' => $result];
    }

    public function model(): AgentModel
    {
        if (app()->bound(AgentModel::class)) { // tests and evaluation runs
            return app(AgentModel::class);
        }

        // Admin choice (AI → Models) first, then AI_CHAT_PROVIDER / AI_CHAT_*_MODEL.
        $chat = AiModelCatalog::chat();

        return self::modelFor($chat['provider'], $chat['model']);
    }

    public static function modelFor(string $provider, string $model): AgentModel
    {
        return $provider === 'anthropic'
            ? new AnthropicAgentModel($model, (string) config('ai.chat.effort'))
            : new OpenAIAgentModel($model);
    }
}
