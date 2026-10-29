<?php

namespace App\Services\AI\Agent;

use App\Services\AI\AiSpendMeter;
use App\Services\AI\ModelPricing;
use OpenAI\Laravel\Facades\OpenAI;

/** OpenAI chat completions with function calling. */
class OpenAIAgentModel implements AgentModel
{
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
            $roundIn = (int) ($response->usage->promptTokens ?? 0);
            $roundOut = (int) ($response->usage->completionTokens ?? 0);
            // Counted per round, so a later failure keeps the cost of the rounds already billed.
            AiSpendMeter::current()->add('openai', $this->model, $roundIn, $roundOut,
                ModelPricing::cost('openai', $this->model, $roundIn, $roundOut));
            $in += $roundIn;
            $out += $roundOut;
            $msg = $response->choices[0]->message;

            if (empty($msg->toolCalls) || $round >= $maxToolRounds) {
                return new AgentResult(trim((string) $msg->content), 'openai', $this->model, $in, $out, ModelPricing::cost('openai', $this->model, $in, $out), $calls);
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
}
