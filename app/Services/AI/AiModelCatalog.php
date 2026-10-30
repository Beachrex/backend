<?php

namespace App\Services\AI;

use App\Models\AiUsage;
use App\Models\SiteSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * The models the admin can pick for the AI chat and the AI trip planner (owner 2026-10-30: "v adminu seznam modelov
 * in približne cene … za chat posebej, za AI itinerary posebej"), and which model each feature actually uses:
 * admin choice (site setting) → env/config → code default.
 *
 * Prices are never stored here: they come from {@see ModelPricing}, the single price table the daily cost cap uses.
 */
final class AiModelCatalog
{
    public const USE_CHAT = 'chat';

    public const USE_ITINERARY = 'itinerary';

    /**
     * Supported models, best first per provider. `chat` = can run the chat (tool calling through the agent engine's
     * OpenAI Chat Completions or Anthropic Messages call); every model can write the trip planner narration.
     * GPT-6 Astra: OpenAI's Chat Completions has no function calling for it (Responses API only), so not for the chat.
     *
     * @var array<string, array{provider: string, label: string, quality: string, chat: bool}>
     */
    public const MODELS = [
        'claude-opus-5-5' => ['provider' => 'anthropic', 'label' => 'Claude Opus 5.5', 'chat' => true, 'quality' => 'Best quality: most reliable tool use and wording in all 19 languages; the most expensive Claude here. Trip planner default.'],
        'claude-sonnet-5-5' => ['provider' => 'anthropic', 'label' => 'Claude Sonnet 5.5', 'chat' => true, 'quality' => 'Good balance: close to Opus for beach questions at half the price; faster.'],
        'claude-haiku-5-5' => ['provider' => 'anthropic', 'label' => 'Claude Haiku 5.5', 'chat' => true, 'quality' => 'Cheapest Claude, fastest: fine for simple questions, weaker on multi-step searches and nuanced wording.'],
        'gpt-6-astra' => ['provider' => 'openai', 'label' => 'OpenAI GPT-6 Astra', 'chat' => false, 'quality' => 'OpenAI\'s top model (Sept 2026), the most expensive here. Trip planner only (no tool calling in the API the chat uses).'],
        'gpt-4o' => ['provider' => 'openai', 'label' => 'OpenAI GPT-4o', 'chat' => true, 'quality' => 'Older OpenAI model, mid price; no advantage over Sonnet 5.5 here.'],
        'gpt-4o-mini' => ['provider' => 'openai', 'label' => 'OpenAI GPT-4o mini', 'chat' => true, 'quality' => 'Cheapest overall; was the trip planner model until 2026-10-30. Weakest at choosing tools and following the answer rules.'],
    ];

    /**
     * Token profile of one answer when there is not enough history on this environment (documented in
     * docs/guides/admin/ai-modeli.md). Chat: an agent answer with ~2–3 tool rounds; calibrated so that Opus 5.5
     * costs ~$0.036, the measured average of the 2026-10-09 evaluation (40 questions). Trip planner: one 7-day
     * narration (prompt with the fixed day skeleton, JSON answer).
     *
     * @var array<string, array{in: int, out: int, cache_read: int}>
     */
    public const ASSUMED_TOKENS = [
        self::USE_CHAT => ['in' => 6000, 'out' => 600, 'cache_read' => 4000],
        self::USE_ITINERARY => ['in' => 4000, 'out' => 3500, 'cache_read' => 0],
    ];

    /** Answers needed in the last 30 days before the measured average replaces the assumption. */
    public const MIN_MEASURED = 20;

    private const SETTING = [
        self::USE_CHAT => SiteSetting::AI_CHAT_MODEL,
        self::USE_ITINERARY => SiteSetting::AI_ITINERARY_MODEL,
    ];

    private const USAGE_EVENT = [self::USE_CHAT => 'chat', self::USE_ITINERARY => 'itinerary'];

    public static function find(string $model): ?array
    {
        return self::MODELS[$model] ?? null;
    }

    /** Whether this environment has an API key for the provider (never reads or returns the key itself). */
    public static function hasKey(string $provider): bool
    {
        return trim((string) config("ai.providers.{$provider}.api_key")) !== '';
    }

    /** In the list, usable for this feature, and its provider has a key here: the only values the admin can save. */
    public static function selectable(string $model, string $use = self::USE_ITINERARY): bool
    {
        $entry = self::find($model);

        return $entry !== null && self::hasKey($entry['provider']) && ($use !== self::USE_CHAT || $entry['chat']);
    }

    /** @return list<string> */
    public static function selectableIds(string $use): array
    {
        return array_values(array_filter(array_keys(self::MODELS), fn ($id) => self::selectable($id, $use)));
    }

    /** The admin's choice if it is still usable here; null = use the env/config default. */
    public static function adminChoice(string $use): ?string
    {
        $value = SiteSetting::string(self::SETTING[$use]);
        if ($value === null || $value === '') {
            return null;
        }
        if (! self::selectable($value, $use)) {
            // Removed from the list or the provider's key is gone: never break the feature, fall back loudly.
            Log::warning('AI model setting ignored: not supported or no API key in this environment', ['use' => $use, 'model' => $value]);

            return null;
        }

        return $value;
    }

    /**
     * What the chat runs: engine, provider and model. An Anthropic model always runs on the agent engine (the
     * pipeline engine is OpenAI JSON mode only), so choosing one in admin switches a pipeline environment to agent.
     *
     * @return array{engine: string, provider: string, model: string, source: string}
     */
    public static function chat(): array
    {
        return self::resolveChat(self::adminChoice(self::USE_CHAT));
    }

    private static function resolveChat(?string $choice): array
    {
        $engine = config('ai.chat.engine') === 'agent' ? 'agent' : 'pipeline';
        if ($choice !== null) {
            $provider = self::MODELS[$choice]['provider'];

            return ['engine' => $provider === 'anthropic' ? 'agent' : $engine, 'provider' => $provider, 'model' => $choice, 'source' => 'admin'];
        }

        if ($engine === 'pipeline') {
            return ['engine' => 'pipeline', 'provider' => 'openai', 'model' => (string) config('ai.providers.openai.default_model'), 'source' => 'config'];
        }

        $provider = config('ai.chat.provider') === 'anthropic' ? 'anthropic' : 'openai';

        return ['engine' => 'agent', 'provider' => $provider, 'model' => (string) config("ai.chat.{$provider}_model"), 'source' => 'config'];
    }

    /**
     * What the trip planner narration runs: admin choice, else `ai.itinerary.model` (AI_ITINERARY_MODEL, default
     * Claude Opus 5.5, owner 2026-10-30: "AI trip planner mora biti najdražji/najboljši model"), else — when that
     * provider has no key here — the old LLM provider config (AI_PROVIDER, OPENAI_MODEL).
     *
     * @return array{provider: string, model: string, source: string}
     */
    public static function itinerary(): array
    {
        return self::resolveItinerary(self::adminChoice(self::USE_ITINERARY));
    }

    private static function resolveItinerary(?string $choice): array
    {
        if ($choice !== null) {
            return ['provider' => self::MODELS[$choice]['provider'], 'model' => $choice, 'source' => 'admin'];
        }

        $configured = (string) config('ai.itinerary.model');
        if (self::selectable($configured, self::USE_ITINERARY)) {
            return ['provider' => self::MODELS[$configured]['provider'], 'model' => $configured, 'source' => 'config'];
        }

        $provider = (string) config('ai.provider', 'openai');

        return ['provider' => $provider, 'model' => (string) config("ai.providers.{$provider}.default_model"), 'source' => 'config'];
    }

    /** What the feature would use with no admin choice (shown as "default" in admin). */
    public static function configDefault(string $use): array
    {
        return $use === self::USE_CHAT ? self::resolveChat(null) : self::resolveItinerary(null);
    }

    /** The model the chat pipeline engine calls (OpenAI JSON mode): an OpenAI admin choice, else OPENAI_MODEL. */
    public static function pipelineModel(): string
    {
        $chat = self::chat();

        return $chat['engine'] === 'pipeline' && $chat['provider'] === 'openai'
            ? $chat['model']
            : (string) config('ai.providers.openai.default_model');
    }

    /**
     * Tokens of one typical answer: the average of the last 30 days on this environment when there are enough
     * answered requests, else {@see ASSUMED_TOKENS}. Cached 10 minutes (admin page only).
     *
     * @return array{in: int, out: int, cache_read: int, measured: int, source: string}
     */
    public static function typicalTokens(string $use): array
    {
        return Cache::remember("ai_models:typical_tokens:{$use}", 600, function () use ($use) {
            $assumed = self::ASSUMED_TOKENS[$use];
            $row = AiUsage::query()
                ->where('event_type', self::USAGE_EVENT[$use])
                ->where('tokens_output', '>', 0)
                ->where('created_at', '>=', now()->subDays(30))
                ->selectRaw('count(*) as n, avg(tokens_input) as tin, avg(tokens_output) as tout')
                ->first();
            $n = (int) ($row->n ?? 0);
            if ($n < self::MIN_MEASURED) {
                return $assumed + ['measured' => $n, 'source' => 'assumption'];
            }

            // ai_usage keeps uncached input only; cached prompt reads (cheap) come from the assumption.
            return ['in' => (int) round($row->tin), 'out' => (int) round($row->tout), 'cache_read' => $assumed['cache_read'],
                'measured' => $n, 'source' => 'measured'];
        });
    }

    /** Estimated USD cost of one answer with this model, priced by {@see ModelPricing}. */
    public static function estimate(string $model, string $use): float
    {
        $entry = self::find($model);
        $tokens = self::typicalTokens($use);
        $provider = $entry['provider'] ?? (str_starts_with($model, 'claude') ? 'anthropic' : 'openai');
        // OpenAI caches prompts on its own and reports them inside prompt_tokens: count them as input.
        $cacheRead = $provider === 'anthropic' ? $tokens['cache_read'] : 0;
        $in = $provider === 'anthropic' ? $tokens['in'] : $tokens['in'] + $tokens['cache_read'];

        return ModelPricing::cost($provider, $model, $in, $tokens['out'], $cacheRead);
    }

    /**
     * Estimated USD cost of one trip plan of this length: the narration's prompt and answer grow with the days
     * (answer budget 800 + 450 per day). Scaled from the 7-day profile; Claude's thinking is in the output.
     */
    public static function estimatePlan(string $model, int $days): float
    {
        $entry = self::find($model);
        $tokens = self::typicalTokens(self::USE_ITINERARY);
        $scale = (1500 + 350 * $days) / (1500 + 350 * 7);
        $outScale = (300 + 450 * $days) / (300 + 450 * 7);

        return ModelPricing::cost($entry['provider'] ?? 'openai', $model, (int) round($tokens['in'] * $scale), (int) round($tokens['out'] * $outScale));
    }

    /**
     * Measured average cost per answer with this model in the last 30 days (null = no answers).
     *
     * @return array{cost: float, n: int}|null
     */
    public static function measuredCost(string $model, string $use): ?array
    {
        $row = AiUsage::query()
            ->where('event_type', self::USAGE_EVENT[$use])
            ->where('llm_model', $model)
            ->where('tokens_output', '>', 0)
            ->where('created_at', '>=', now()->subDays(30))
            ->selectRaw('count(*) as n, avg(cost_usd) as c')
            ->first();

        return (int) ($row->n ?? 0) > 0 ? ['cost' => (float) $row->c, 'n' => (int) $row->n] : null;
    }

    /** Input / output USD per 1M tokens (standard tier), from ModelPricing. */
    public static function pricePerMillion(string $model): array
    {
        $provider = self::MODELS[$model]['provider'];

        // Priced on a small prompt and scaled: a 1M-token prompt would hit Haiku 5.5's long-prompt tier.
        return [round(ModelPricing::cost($provider, $model, 10_000, 0) * 100, 4), round(ModelPricing::cost($provider, $model, 0, 10_000) * 100, 4)];
    }
}
