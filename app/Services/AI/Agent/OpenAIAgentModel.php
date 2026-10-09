<?php

namespace App\Services\AI\Agent;

use OpenAI\Laravel\Facades\OpenAI;

/** OpenAI chat completions with function calling. */
class OpenAIAgentModel implements AgentModel
{
    /** USD per 1M tokens (input, output); unknown models are costed as gpt-4o. */
    private const PRICING = [
        'gpt-4o' => [2.50, 10.00],
        'gpt-4o-mini' => [0.15, 0.60],
    ];

    public function __construct(private readonly string $model) {}

    public function converse(string $system, array $history, string $message, array $tools, callable $runTool, int $maxToolRounds): AgentResult
    {
        $messages = [['role' => 'system', 'content' => $system]];
        foreach ($history as $turn) {
            $messages[] = ['role' => $turn['role'], 'content' => $turn['content']];
        }
        $messages[] = ['role' => 'user', 'content' => $message];

        $toolDefs = array_map(fn ($t) => ['type' => 'function', 'function' => [
            'name' => $t['name'], 'description' => $t['description'], 'parameters' => $t['parameters'],
        ]], $tools);

        $in = $out = 0;
        $calls = [];
        for ($round = 0; ; $round++) {
            $params = ['model' => $this->model, 'messages' => $messages, 'temperature' => 0.3, 'max_tokens' => 1200];
            if ($round < $maxToolRounds) {
                $params['tools'] = $toolDefs;
                $params['tool_choice'] = 'auto';
            }
            $response = OpenAI::chat()->create($params);
            $in += $response->usage->promptTokens ?? 0;
            $out += $response->usage->completionTokens ?? 0;
            $msg = $response->choices[0]->message;

            if (empty($msg->toolCalls) || $round >= $maxToolRounds) {
                return new AgentResult(trim((string) $msg->content), 'openai', $this->model, $in, $out, $this->cost($in, $out), $calls);
            }

            $messages[] = ['role' => 'assistant', 'content' => $msg->content, 'tool_calls' => array_map(fn ($c) => [
                'id' => $c->id, 'type' => 'function', 'function' => ['name' => $c->function->name, 'arguments' => $c->function->arguments],
            ], $msg->toolCalls)];
            foreach ($msg->toolCalls as $call) {
                $input = json_decode($call->function->arguments ?: '{}', true);
                $input = is_array($input) ? $input : [];
                $calls[] = ['name' => $call->function->name, 'input' => $input];
                $messages[] = ['role' => 'tool', 'tool_call_id' => $call->id, 'content' => $runTool($call->function->name, $input)];
            }
        }
    }

    private function cost(int $in, int $out): float
    {
        [$pin, $pout] = self::PRICING[$this->model] ?? self::PRICING['gpt-4o'];

        return round($in / 1e6 * $pin + $out / 1e6 * $pout, 6);
    }
}
