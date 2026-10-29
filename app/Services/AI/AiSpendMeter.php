<?php

namespace App\Services\AI;

/**
 * Every LLM call of one request (or one queued job) adds its tokens and cost here, so a request that fails
 * after some calls already cost money (an agent tool round, the understanding call, a retried itinerary
 * narration) still records that cost for the daily circuit breaker.
 *
 * Bound as a scoped singleton: a fresh meter per HTTP request and per queue job.
 */
class AiSpendMeter
{
    private float $cost = 0.0;
    private int $inputTokens = 0;
    private int $outputTokens = 0;
    private ?string $provider = null;
    private ?string $model = null;

    public function add(string $provider, string $model, int $inputTokens, int $outputTokens, float $costUsd): void
    {
        $this->cost += $costUsd;
        $this->inputTokens += $inputTokens;
        $this->outputTokens += $outputTokens;
        $this->provider = $provider;
        $this->model = $model;
    }

    /** A checkpoint to measure one flow inside a longer request with {@see since()}. */
    public function mark(): array
    {
        return ['cost' => $this->cost, 'in' => $this->inputTokens, 'out' => $this->outputTokens];
    }

    /**
     * @return array{cost: float, in: int, out: int, provider: ?string, model: ?string}
     */
    public function since(array $mark = ['cost' => 0.0, 'in' => 0, 'out' => 0]): array
    {
        return [
            'cost' => round($this->cost - $mark['cost'], 6),
            'in' => $this->inputTokens - $mark['in'],
            'out' => $this->outputTokens - $mark['out'],
            'provider' => $this->provider,
            'model' => $this->model,
        ];
    }

    public static function current(): self
    {
        return app(self::class);
    }
}
