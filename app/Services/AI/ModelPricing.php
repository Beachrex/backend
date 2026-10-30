<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Log;

/**
 * USD cost of one LLM call, for the daily cost circuit breaker (owner 2026-10-29: free chat $5/day).
 *
 * A cost that is too low means the cap never trips, so an unknown model is never free: it is priced with a
 * conservative fallback (the most expensive tier of its provider) and a warning is logged.
 *
 * Prices are USD per 1M tokens. Anthropic: https://platform.claude.com/docs/en/about-claude/pricing (checked
 * 2026-10-29). OpenAI: https://developers.openai.com/api/docs/pricing (gpt-6-astra checked 2026-10-30; the
 * pipeline engine and the trip planner can call OpenAI models).
 */
class ModelPricing
{
    /**
     * Anthropic: [input, output, cache read multiplier]. Cache writes (5-minute TTL, the only one we use) cost
     * 1.25x input. Model ids may carry a date suffix; the longest matching prefix wins.
     */
    private const ANTHROPIC = [
        'claude-fable-5-1' => [10.00, 50.00, 0.025],
        'claude-mythos-5-1' => [10.00, 50.00, 0.025],
        'claude-fable-5' => [10.00, 50.00, 0.1],
        'claude-mythos-5' => [10.00, 50.00, 0.1],
        'claude-opus-5-5' => [4.00, 20.00, 0.05],
        'claude-opus-5' => [5.00, 25.00, 0.1],
        'claude-opus-4-8' => [5.00, 25.00, 0.1],
        'claude-opus-4-7' => [5.00, 25.00, 0.1],
        'claude-opus-4-6' => [5.00, 25.00, 0.1],
        'claude-opus-4-5' => [5.00, 25.00, 0.1],
        'claude-opus-4-1' => [15.00, 75.00, 0.1],
        'claude-opus-4' => [15.00, 75.00, 0.1],
        'claude-sonnet-5-5' => [2.00, 10.00, 0.05],
        'claude-sonnet-5' => [2.00, 10.00, 0.1],
        'claude-sonnet-4' => [3.00, 15.00, 0.1],
        'claude-haiku-5-5' => [0.10, 0.50, 0.1],
        'claude-haiku-4-5' => [1.00, 5.00, 0.1],
        'claude-3-5-haiku' => [0.80, 4.00, 0.1],
        'claude-3-haiku' => [0.25, 1.25, 0.1],
    ];

    /** Haiku 5.5 prompts over 100k tokens (cache reads and writes included) pay 5x. */
    private const ANTHROPIC_LONG_PROMPT = ['claude-haiku-5-5' => [100_000, 0.50, 2.50]];

    private const ANTHROPIC_FALLBACK = [10.00, 50.00, 0.1];

    /**
     * OpenAI: [input, output]. Cached input (gpt-6-astra: $1) is reported inside prompt_tokens and priced as normal
     * input here (a small over-count, never an under-count). gpt-6-astra: https://developers.openai.com/api/docs/pricing
     * and /api/docs/models/gpt-6-astra (checked 2026-10-30).
     */
    private const OPENAI = [
        'gpt-6-astra' => [10.00, 50.00],
        'gpt-4o-mini' => [0.15, 0.60],
        'gpt-4o' => [2.50, 10.00],
        'gpt-4-turbo' => [10.00, 30.00],
        'gpt-4' => [30.00, 60.00],
        'gpt-3.5-turbo' => [0.50, 1.50],
    ];

    /** gpt-6-astra prompts over 272K input tokens pay 2x input and 1.5x output for the whole request. */
    private const OPENAI_LONG_PROMPT = ['gpt-6-astra' => [272_000, 20.00, 75.00]];

    private const OPENAI_FALLBACK = [30.00, 75.00];

    /** Warn once per process and model, not on every call. */
    private static array $warned = [];

    /**
     * @param int $inputTokens uncached input tokens (Anthropic `input_tokens`, OpenAI `prompt_tokens`)
     */
    public static function cost(string $provider, string $model, int $inputTokens, int $outputTokens, int $cacheReadTokens = 0, int $cacheWriteTokens = 0): float
    {
        if ($provider === 'anthropic') {
            [$in, $out, $readMultiplier] = self::lookup(self::ANTHROPIC, $model) ?? self::unknown($provider, $model, self::ANTHROPIC_FALLBACK);
            $long = self::lookupKey(array_keys(self::ANTHROPIC_LONG_PROMPT), $model);
            if ($long !== null) {
                [$threshold, $longIn, $longOut] = self::ANTHROPIC_LONG_PROMPT[$long];
                if ($inputTokens + $cacheReadTokens + $cacheWriteTokens > $threshold) {
                    [$in, $out] = [$longIn, $longOut];
                }
            }

            return round(($inputTokens + $cacheReadTokens * $readMultiplier + $cacheWriteTokens * 1.25) / 1e6 * $in
                + $outputTokens / 1e6 * $out, 6);
        }

        [$in, $out] = self::lookup(self::OPENAI, $model) ?? self::unknown($provider, $model, self::OPENAI_FALLBACK);
        $long = self::lookupKey(array_keys(self::OPENAI_LONG_PROMPT), $model);
        if ($long !== null && $inputTokens > self::OPENAI_LONG_PROMPT[$long][0]) {
            [, $in, $out] = self::OPENAI_LONG_PROMPT[$long];
        }

        return round($inputTokens / 1e6 * $in + $outputTokens / 1e6 * $out, 6);
    }

    public static function isKnown(string $provider, string $model): bool
    {
        return self::lookup($provider === 'anthropic' ? self::ANTHROPIC : self::OPENAI, $model) !== null;
    }

    private static function lookup(array $table, string $model): ?array
    {
        $key = self::lookupKey(array_keys($table), $model);

        return $key === null ? null : $table[$key];
    }

    /**
     * Exact id, or the id with a snapshot suffix (`-20261001`, `-2024-07-18`, `-latest`). Anything else is unknown,
     * so `claude-opus-5-55` is never priced as `claude-opus-5`.
     */
    private static function lookupKey(array $keys, string $model): ?string
    {
        $model = strtolower(trim($model));
        foreach ($keys as $key) {
            if ($model === $key || preg_match('/^'.preg_quote($key, '/').'-(\d{8}|\d{4}-\d{2}-\d{2}|latest)$/', $model)) {
                return $key;
            }
        }

        return null;
    }

    private static function unknown(string $provider, string $model, array $fallback): array
    {
        if (! isset(self::$warned[$provider.':'.$model])) {
            self::$warned[$provider.':'.$model] = true;
            Log::warning('AI cost: unknown model, priced with the conservative fallback; add it to ModelPricing', [
                'provider' => $provider, 'model' => $model,
            ]);
        }

        return $fallback;
    }
}
