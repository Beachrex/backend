<?php

namespace App\Services\AI\Agent;

/**
 * One chat model that can call tools. Each implementation runs its own tool loop in its provider's
 * format; the chat agent only sees text in and text out.
 */
interface AgentModel
{
    /**
     * @param array<int, array{role: 'user'|'assistant', content: string}> $history earlier turns, oldest first
     * @param array<int, array{name: string, description: string, parameters: array}> $tools
     * @param callable(string $name, array $input): string $runTool
     */
    public function converse(string $system, array $history, string $message, array $tools, callable $runTool, int $maxToolRounds): AgentResult;
}
