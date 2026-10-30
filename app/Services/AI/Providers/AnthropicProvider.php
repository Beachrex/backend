<?php

namespace App\Services\AI\Providers;

use Anthropic\Client;
use Anthropic\Core\Exceptions\APITimeoutException;
use Anthropic\Core\Exceptions\RateLimitException;
use App\Services\AI\Contracts\LLMProviderInterface;
use App\Services\AI\DTOs\ChatCompletionRequest;
use App\Services\AI\DTOs\ChatCompletionResponse;
use App\Services\AI\DTOs\ChatMessage;
use App\Services\AI\Exceptions\LLMException;
use App\Services\AI\Exceptions\LLMRateLimitException;
use App\Services\AI\Exceptions\LLMTimeoutException;
use App\Services\AI\ModelPricing;

/**
 * Claude for single-shot LLMService calls (trip planner narration; owner 2026-10-30: model picked in admin).
 *
 * Same output contract as OpenAIProvider: text content, token counts, cost from ModelPricing, finishReason
 * "length" when the answer was cut off. OpenAI JSON mode (`responseFormat`) has no Claude equivalent without a
 * schema, so the JSON requirement is added to the system prompt and the outermost JSON object is returned.
 * Current Claude models always think (adaptive): effort is set low and max_tokens gets room for the thinking.
 */
class AnthropicProvider implements LLMProviderInterface
{
    /** Extra output room for adaptive thinking on top of the caller's answer budget. */
    public const THINKING_HEADROOM = 4000;

    private const JSON_INSTRUCTION = "\n\nReply with one valid JSON object only: no text before or after it, no code fences.";

    public function __construct(private readonly ?Client $client = null) {}

    public function chatCompletion(ChatCompletionRequest $request): ChatCompletionResponse
    {
        $model = $request->model ?? $this->getDefaultModel();
        $json = $request->responseFormat !== null;

        $system = '';
        $messages = [];
        foreach ($request->messages as $message) {
            /** @var ChatMessage $message */
            if ($message->role === 'system') {
                $system .= ($system === '' ? '' : "\n\n").$message->content;
            } else {
                $messages[] = ['role' => $message->role === 'assistant' ? 'assistant' : 'user', 'content' => $message->content];
            }
        }
        if ($json) {
            $system .= self::JSON_INSTRUCTION;
        }

        try {
            $response = $this->client()->messages->create(
                maxTokens: ($request->maxTokens ?? 1000) + self::THINKING_HEADROOM,
                messages: $messages,
                model: $model,
                system: $system !== '' ? $system : null,
                outputConfig: ['effort' => 'low'],
                stopSequences: $request->stop,
            );
        } catch (RateLimitException $e) {
            throw new LLMRateLimitException(message: 'Anthropic rate limit exceeded: '.$e->getMessage(), provider: 'anthropic', retryAfter: 60, previous: $e);
        } catch (APITimeoutException $e) {
            throw new LLMTimeoutException(message: 'Anthropic request timed out: '.$e->getMessage(), provider: 'anthropic',
                timeout: (int) config('ai.itinerary.timeout', 110), previous: $e);
        } catch (\Throwable $e) {
            throw new LLMException(message: 'Anthropic API error: '.$e->getMessage(), provider: 'anthropic', context: ['model' => $model], previous: $e);
        }

        $servedBy = (string) ($response->model ?? $model);
        $in = (int) ($response->usage->inputTokens ?? 0);
        $out = (int) ($response->usage->outputTokens ?? 0);
        $cacheRead = (int) ($response->usage->cacheReadInputTokens ?? 0);
        $cacheWrite = (int) ($response->usage->cacheCreationInputTokens ?? 0);
        $cost = ModelPricing::cost('anthropic', $servedBy, $in, $out, $cacheRead, $cacheWrite);

        $text = '';
        foreach ($response->content as $block) {
            if (($block->type ?? null) === 'text') {
                $text .= $block->text;
            }
        }
        $text = trim($text);
        if ($json) {
            $text = self::outermostJson($text);
        }

        $finish = match ($response->stopReason) {
            'max_tokens' => 'length',
            'end_turn', 'stop_sequence' => 'stop',
            default => $response->stopReason,
        };

        return new ChatCompletionResponse(
            content: $text,
            model: $servedBy,
            inputTokens: $in,
            outputTokens: $out,
            totalTokens: $in + $out,
            costUsd: $cost,
            provider: 'anthropic',
            finishReason: $finish,
        );
    }

    /** The text from the first "{" to the last "}" (drops code fences or a stray sentence around the object). */
    public static function outermostJson(string $text): string
    {
        $start = strpos($text, '{');
        $end = strrpos($text, '}');

        return $start !== false && $end !== false && $end > $start ? substr($text, $start, $end - $start + 1) : $text;
    }

    public function countTokens(string $text, ?string $model = null): int
    {
        return (int) ceil(strlen($text) / 4);
    }

    public function getProviderName(): string
    {
        return 'anthropic';
    }

    public function getDefaultModel(): string
    {
        return (string) config('ai.providers.anthropic.default_model');
    }

    public function calculateCost(string $model, int $inputTokens, int $outputTokens): float
    {
        return ModelPricing::cost('anthropic', $model, $inputTokens, $outputTokens);
    }

    private function client(): Client
    {
        return $this->client ?? (app()->bound(Client::class) ? app(Client::class) : null) ?? new Client(
            apiKey: (string) config('ai.providers.anthropic.api_key'),
            // No SDK retry: the callers retry themselves (the trip planner makes two attempts inside its 240 s job).
            requestOptions: ['timeout' => (float) config('ai.itinerary.timeout', 110), 'maxRetries' => 0],
        );
    }
}
