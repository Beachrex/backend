<?php

namespace App\Services\Webcams;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Windy Webcams API v3, for batch commands only (never at page render time).
 * The key is sent in `x-windy-api-key` and never logged. Every request counts against
 * `WINDY_DAILY_REQUEST_BUDGET`; 429/5xx are retried with exponential back-off.
 */
class WindyApiClient
{
    /** Max page size and max offset on the free tier (docs/technical/windy-webcams-api.md). */
    public const LIMIT = 50;
    public const MAX_OFFSET = 1000;
    public const MAX_IDS = 50;

    private const RETRIES = 4;

    public function __construct(private readonly ?\Closure $sleep = null)
    {
    }

    public function remainingBudget(): int
    {
        $used = (int) DB::table('windy_api_usage')->where('day', now()->toDateString())->value('requests');

        return max(0, config('webcams.api.daily_request_budget') - $used);
    }

    /** @return array{total:int, webcams:list<array<string,mixed>>} */
    public function list(array $query): array
    {
        $json = $this->get('/webcams', $query);

        return ['total' => (int) ($json['total'] ?? 0), 'webcams' => $json['webcams'] ?? []];
    }

    /**
     * Webcams by id, at most 50 per call (when webcamIds is set Windy ignores every other filter).
     *
     * @param  list<int>  $ids
     * @return list<array<string,mixed>>
     */
    public function byIds(array $ids): array
    {
        $out = [];
        foreach (array_chunk(array_values(array_unique($ids)), self::MAX_IDS) as $chunk) {
            $out = array_merge($out, $this->list([
                'webcamIds' => $chunk, 'limit' => self::LIMIT, 'include' => ['categories', 'location', 'player'], 'lang' => 'en',
            ])['webcams']);
        }

        return $out;
    }

    /** @return list<array{code:string,name:string}> */
    public function regions(): array
    {
        return $this->get('/regions', []);
    }

    private function get(string $path, array $query): array
    {
        $key = config('webcams.api.key');
        if (! $key) {
            throw new RuntimeException('WINDY_API_KEY is not set.');
        }
        // Arrays go as comma-separated values (Windy's format, e.g. categories=beach,coast).
        $query = array_map(fn ($v) => is_array($v) ? implode(',', $v) : $v, $query);

        for ($attempt = 0; ; $attempt++) {
            if ($this->remainingBudget() <= 0) {
                throw new BudgetExhausted('Daily Windy request budget reached.');
            }
            $this->count();
            $response = Http::timeout(config('webcams.api.timeout'))
                ->withHeaders(['x-windy-api-key' => $key, 'Accept' => 'application/json'])
                ->get(rtrim(config('webcams.api.base_url'), '/') . $path, $query);

            if ($response->successful()) {
                return $response->json() ?? [];
            }
            $retryable = $response->status() === 429 || $response->serverError();
            if (! $retryable || $attempt >= self::RETRIES) {
                throw new RuntimeException("Windy API {$path} failed with HTTP {$response->status()}.");
            }
            $wait = (int) ($response->header('Retry-After') ?: 2 ** ($attempt + 1));
            ($this->sleep ?? fn (int $s) => sleep($s))(min($wait, 60));
        }
    }

    private function count(): void
    {
        DB::statement(
            'INSERT INTO windy_api_usage (day, requests) VALUES (?, 1) ON CONFLICT (day) DO UPDATE SET requests = windy_api_usage.requests + 1',
            [now()->toDateString()]
        );
    }
}
