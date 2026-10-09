<?php

namespace App\Services\AI\Agent;

use Anthropic\Client;

/**
 * Claude via the official Anthropic PHP SDK (Messages API with client tools, manual loop).
 * Refusals fall back server-side to the model Anthropic picks for the refusal category
 * (beta server-side-fallback, `fallbacks: "default"`).
 */
class AnthropicAgentModel implements AgentModel
{
    /** USD per 1M tokens (input, output), first-party API prices (claude-api reference, 2026-10). */
    private const PRICING = [
        'claude-opus-5-5' => [4.00, 20.00],
        'claude-sonnet-5-5' => [2.00, 10.00],
        'claude-haiku-5-5' => [0.10, 0.50],
    ];

    public function __construct(
        private readonly string $model,
        private readonly string $effort,
        private readonly ?Client $client = null,
    ) {}

    public function converse(string $system, array $history, string $message, array $tools, callable $runTool, int $maxToolRounds): AgentResult
    {
        $client = $this->client ?? new Client(
            apiKey: (string) config('ai.providers.anthropic.api_key'),
            // The whole chat request must finish inside php-fpm/nginx's 60 s.
            requestOptions: ['timeout' => 25.0, 'maxRetries' => 1],
        );

        $messages = [];
        foreach ($history as $turn) {
            $messages[] = ['role' => $turn['role'], 'content' => $turn['content']];
        }
        $messages[] = ['role' => 'user', 'content' => $message];

        // Stable order and content (tools, then system) so the prefix is cached across requests.
        $toolDefs = array_map(fn ($t) => [
            'name' => $t['name'], 'description' => $t['description'], 'inputSchema' => $t['parameters'],
        ], $tools);

        $in = $out = $cacheRead = $cacheWrite = 0;
        $calls = [];
        $servedBy = $this->model;
        for ($round = 0; ; $round++) {
            $response = $client->beta->messages->create(
                model: $this->model,
                maxTokens: 8000,
                system: [['type' => 'text', 'text' => $system, 'cacheControl' => ['type' => 'ephemeral']]],
                messages: $messages,
                tools: $toolDefs,
                // After the last allowed round the model must answer with what it has.
                toolChoice: $round < $maxToolRounds ? ['type' => 'auto'] : ['type' => 'none'],
                outputConfig: ['effort' => $this->effort],
                fallbacks: 'default',
                betas: ['server-side-fallback-2026-07-01'],
            );

            $in += (int) ($response->usage->inputTokens ?? 0);
            $out += (int) ($response->usage->outputTokens ?? 0);
            $cacheRead += (int) ($response->usage->cacheReadInputTokens ?? 0);
            $cacheWrite += (int) ($response->usage->cacheCreationInputTokens ?? 0);
            $servedBy = (string) ($response->model ?? $servedBy);

            if ($response->stopReason === 'refusal') {
                return new AgentResult('', 'anthropic', $servedBy, $in, $out, $this->cost($in, $out, $cacheRead, $cacheWrite), $calls, refused: true);
            }

            if ($response->stopReason !== 'tool_use') {
                $text = '';
                foreach ($response->content as $block) {
                    if ($block->type === 'text') {
                        $text .= $block->text;
                    }
                }

                return new AgentResult(trim($text), 'anthropic', $servedBy, $in, $out, $this->cost($in, $out, $cacheRead, $cacheWrite), $calls);
            }

            // Keep the whole assistant turn (including thinking blocks) unchanged, then answer every tool call
            // in one user message.
            $messages[] = ['role' => 'assistant', 'content' => $response->content];
            $results = [];
            foreach ($response->content as $block) {
                if ($block->type === 'tool_use') {
                    $input = is_array($block->input) ? $block->input : (array) $block->input;
                    $calls[] = ['name' => $block->name, 'input' => $input];
                    $results[] = ['type' => 'tool_result', 'toolUseID' => $block->id, 'content' => $runTool($block->name, $input)];
                }
            }
            $messages[] = ['role' => 'user', 'content' => $results];
        }
    }

    private function cost(int $in, int $out, int $cacheRead, int $cacheWrite): float
    {
        [$pin, $pout] = self::PRICING[$this->model] ?? self::PRICING['claude-opus-5-5'];

        // Cache reads ~0.05-0.1x and writes 1.25x the input price; 0.1x is used for reads (upper bound).
        return round(($in + $cacheRead * 0.1 + $cacheWrite * 1.25) / 1e6 * $pin + $out / 1e6 * $pout, 6);
    }
}
