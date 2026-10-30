<?php

namespace App\Services\AI\Agent;

use Anthropic\Client;
use App\Services\AI\AiSpendMeter;
use App\Services\AI\ModelPricing;

/**
 * Claude via the official Anthropic PHP SDK (Messages API with client tools, manual loop).
 * Refusals fall back server-side to the model Anthropic picks for the refusal category
 * (beta server-side-fallback, `fallbacks: "default"`).
 */
class AnthropicAgentModel implements AgentModel
{
    public function __construct(
        private readonly string $model,
        private readonly string $effort,
        private readonly ?Client $client = null,
    ) {}

    public function converse(string $system, array $history, string $message, array $tools, callable $runTool, int $maxToolRounds): AgentResult
    {
        // A bound client (tests) wins over a new one; the model id still comes from the caller.
        $client = $this->client ?? (app()->bound(Client::class) ? app(Client::class) : null) ?? new Client(
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

        $in = $out = 0;
        $cost = 0.0;
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

            // Every round is billed; each is priced by the model that served it (a server-side fallback may
            // answer with another model) and counted at once, so a later failure keeps the cost of this round.
            $servedBy = (string) ($response->model ?? $servedBy);
            $roundIn = (int) ($response->usage->inputTokens ?? 0);
            $roundOut = (int) ($response->usage->outputTokens ?? 0);
            $cacheRead = (int) ($response->usage->cacheReadInputTokens ?? 0);
            $cacheWrite = (int) ($response->usage->cacheCreationInputTokens ?? 0);
            $roundCost = ModelPricing::cost('anthropic', $servedBy, $roundIn, $roundOut, $cacheRead, $cacheWrite);
            AiSpendMeter::current()->add('anthropic', $servedBy, $roundIn, $roundOut, $roundCost);
            $in += $roundIn; // uncached input; cached tokens are in the cost only
            $out += $roundOut;
            $cost += $roundCost;

            if ($response->stopReason === 'refusal') {
                return new AgentResult('', 'anthropic', $servedBy, $in, $out, round($cost, 6), $calls, refused: true);
            }

            if ($response->stopReason !== 'tool_use') {
                $text = '';
                foreach ($response->content as $block) {
                    if ($block->type === 'text') {
                        $text .= $block->text;
                    }
                }

                return new AgentResult(trim($text), 'anthropic', $servedBy, $in, $out, round($cost, 6), $calls);
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
}
