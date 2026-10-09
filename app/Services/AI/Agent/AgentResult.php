<?php

namespace App\Services\AI\Agent;

class AgentResult
{
    public function __construct(
        public readonly string $text,
        public readonly string $provider,
        public readonly string $model,
        public readonly int $inputTokens,
        public readonly int $outputTokens,
        public readonly float $costUsd,
        /** @var array<int, array{name: string, input: array}> */
        public readonly array $toolCalls = [],
        public readonly bool $refused = false,
    ) {}
}
