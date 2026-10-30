<?php

namespace App\Support;

use Closure;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Cache for whole public JSON responses (ADR 2026-10-30 "API performance: layered caching").
 *
 * Stores the finished JSON body (compressed), never Eloquent models: before, `countries`, `regions`, `islands` and
 * `cities` cached model graphs with every translation (one `cities` key was ~50 MB per locale) and a cache HIT
 * still cost 150 ms – 1.4 s of PHP to unserialize them. A hit is now one Redis GET and a gunzip.
 *
 * - Key: `api:{generation}:{name}:{locale}:{md5(params)}`. Callers pass only the request parameters that change
 *   the answer, already normalized, so the key space stays bounded.
 * - Invalidation: one generation for all public list responses. Saving or deleting any content model (admin, sync
 *   commands that use Eloquent) moves it ({@see self::flush()}, wired in AppServiceProvider), so the next request
 *   rebuilds. `cache:clear-api` and every deploy (`cache:clear`) do the same. TTL is only a safety net.
 * - The generation is a millisecond timestamp, not a counter: if Redis evicts it (allkeys-lru, shared by three
 *   environments), the new value is still newer than any old one, so a stale entry can never be served again.
 * - Stampede: on a miss only one PHP worker builds a key (atomic lock); the others wait up to LOCK_WAIT_S for its
 *   result and build themselves only if that fails (fail open, never an error because of the lock).
 */
final class ApiResponseCache
{
    public const GENERATION_KEY = 'api:generation';

    /** Lists of reference data: changes are rare and clear the cache at once (generation). */
    public const TTL_REFERENCE = 21600; // 6 h

    /** Parameterized lists (filters, coordinates): larger key space, shorter life. */
    public const TTL_QUERY = 3600; // 1 h

    private const LOCK_SECONDS = 30;

    private const LOCK_WAIT_S = 10;

    /**
     * Models whose changes do not affect public API responses (no cache flush on save).
     * Everything else in App\Models is content (places, beaches, translations, media, CMS pages, settings).
     */
    private const NOT_CONTENT = [
        \App\Models\AiMessage::class,
        \App\Models\AiSession::class,
        \App\Models\AiUsage::class,
        \App\Models\ItineraryPlan::class,
        \App\Models\NewsletterSubscriber::class,
        \App\Models\Purchase::class,
        \App\Models\User::class,
        \App\Models\UserEntitlement::class,
        \App\Models\UserMigrationStatus::class,
    ];

    /**
     * The cached JSON response for this name, locale and parameters; built with $payload on a miss.
     *
     * @param  array<string, mixed>  $params  normalized request parameters that change the answer
     * @param  Closure(): mixed  $payload  returns what would be passed to response()->json() (arrays, resources)
     */
    public static function json(string $name, array $params, int $ttl, Closure $payload, int $status = 200): JsonResponse
    {
        $key = self::key($name, $params);
        $body = self::read($key);
        $hit = $body !== null;

        if (! $hit) {
            $body = self::build($key, $ttl, $payload);
        }

        self::count($hit ? 'hit' : 'miss');
        request()->attributes->set('cache_key', $key);
        request()->attributes->set('cache_hit', $hit);

        return new JsonResponse($body, $status, [], 0, true);
    }

    public static function key(string $name, array $params = []): string
    {
        ksort($params);

        return 'api:'.self::generation().':'.$name.':'.app()->getLocale().':'.md5(json_encode($params));
    }

    /** Clears every cached response (moves the generation; old entries expire by TTL or LRU). */
    public static function flush(): void
    {
        $next = max((int) floor(microtime(true) * 1000), self::generation() + 1);
        Cache::forever(self::GENERATION_KEY, $next);
    }

    /** True when saving or deleting this model can change a public response. */
    public static function isContent(Model $model): bool
    {
        return str_starts_with($model::class, 'App\\Models\\') && ! in_array($model::class, self::NOT_CONTENT, true);
    }

    public static function generation(): int
    {
        return (int) Cache::rememberForever(self::GENERATION_KEY, fn () => (int) floor(microtime(true) * 1000));
    }

    /** @return array{hit: int, miss: int, date: string} today's counters (UTC) */
    public static function stats(?string $date = null): array
    {
        $date ??= gmdate('Ymd');

        return [
            'date' => $date,
            'hit' => (int) Cache::get("api:stats:{$date}:hit", 0),
            'miss' => (int) Cache::get("api:stats:{$date}:miss", 0),
        ];
    }

    private static function build(string $key, int $ttl, Closure $payload): string
    {
        $make = function () use ($key, $ttl, $payload): string {
            // Another worker may have built it while this one waited for the lock.
            if (($ready = self::read($key)) !== null) {
                return $ready;
            }
            $body = (new JsonResponse($payload()))->getContent();
            Cache::put($key, gzcompress($body, 3), $ttl);

            return $body;
        };

        try {
            return Cache::lock('lock:'.$key, self::LOCK_SECONDS)->block(self::LOCK_WAIT_S, $make);
        } catch (LockTimeoutException) {
            return $make();
        }
    }

    private static function read(string $key): ?string
    {
        $stored = Cache::get($key);
        if (! is_string($stored)) {
            return null;
        }
        $body = @gzuncompress($stored);

        return $body === false ? null : $body;
    }

    private static function count(string $what): void
    {
        try {
            // One INCR per request; one small key per day and counter (no TTL on INCR, they are a few bytes).
            Cache::increment('api:stats:'.gmdate('Ymd').':'.$what);
        } catch (Throwable $e) {
            Log::debug('api cache stats: '.$e->getMessage());
        }
    }
}
