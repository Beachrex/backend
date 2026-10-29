<?php

namespace App\Services\AI;

use App\Models\AiUsage;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Daily AI spend for the cost circuit breaker (ADR 2026-10-29 "AI chat daily cost cap and Turnstile").
 *
 * - "Day" is a calendar day in `ai.circuit_breakers.timezone` (default Europe/Ljubljana, the owner's day), so the
 *   chat switches itself back on at local midnight. Nothing has to be reset: every key and query is per date.
 * - Buckets: `free` = free-tier usage except the paid trip planner (in practice the chat), `all` = everything.
 * - Sums are cached for 5 minutes and dropped whenever a usage row is written ({@see forget()}), so the check is
 *   exact apart from requests already running when the limit is crossed.
 */
class AiDailySpend
{
    public const BUCKET_FREE = 'free';
    public const BUCKET_ALL = 'all';

    private const CACHE_TTL = 300;

    public static function timezone(): string
    {
        return (string) (config('ai.circuit_breakers.timezone') ?: config('app.timezone', 'UTC'));
    }

    public static function now(): CarbonImmutable
    {
        return CarbonImmutable::now(self::timezone());
    }

    public static function date(): string
    {
        return self::now()->format('Y-m-d');
    }

    public static function cacheKey(string $bucket, ?string $date = null): string
    {
        return "ai:circuit_breaker:daily_cost:{$bucket}:".($date ?? self::date());
    }

    /** Start of the next local day, when a tripped breaker opens again (ISO 8601 with offset). */
    public static function resetsAt(): CarbonImmutable
    {
        return self::now()->addDay()->startOfDay();
    }

    public static function cost(string $bucket): float
    {
        return (float) Cache::remember(self::cacheKey($bucket), self::CACHE_TTL, function () use ($bucket) {
            $start = self::now()->startOfDay();
            // created_at is stored in the app timezone (UTC); compare with the local day's bounds converted to it.
            $appTz = config('app.timezone', 'UTC');
            $query = AiUsage::query()
                ->where('created_at', '>=', $start->setTimezone($appTz)->toDateTimeString())
                ->where('created_at', '<', $start->addDay()->setTimezone($appTz)->toDateTimeString());

            if ($bucket === self::BUCKET_FREE) {
                $query->where('tier', 'free')->where('event_type', '!=', 'itinerary');
            }

            return (float) $query->sum('cost_usd');
        });
    }

    public static function forget(): void
    {
        Cache::forget(self::cacheKey(self::BUCKET_FREE));
        Cache::forget(self::cacheKey(self::BUCKET_ALL));
    }

    /**
     * Once per bucket and day: a warning in the log and an e-mail to `ai.circuit_breakers.alert_email`
     * (AI_ALERT_EMAIL; no e-mail when empty).
     */
    public static function tripped(string $bucket, float $cost, float $limit): void
    {
        if (! Cache::add('ai:circuit_breaker:alerted:'.$bucket.':'.self::date(), 1, 60 * 60 * 26)) {
            return;
        }

        Log::warning('AI daily cost limit reached; AI paused until the next day', [
            'bucket' => $bucket, 'daily_cost_usd' => round($cost, 4), 'limit_usd' => $limit,
            'resets_at' => self::resetsAt()->toIso8601String(),
        ]);

        $to = config('ai.circuit_breakers.alert_email');
        if (! $to) {
            return;
        }

        $what = $bucket === self::BUCKET_FREE ? 'The free AI chat' : 'All AI features (emergency limit)';
        $text = sprintf(
            "%s reached the daily cost limit on %s: \$%.2f of \$%.2f (%s).\n\n".
            "It switches back on automatically at %s. To change the limit, set %s in the server env file and redeploy.",
            $what, config('app.url'), $cost, $limit, self::date(), self::resetsAt()->toIso8601String(),
            $bucket === self::BUCKET_FREE ? 'AI_FREE_TIER_DAILY_LIMIT' : 'AI_EMERGENCY_SHUTDOWN_LIMIT',
        );
        $subject = 'Beachrex: AI daily cost limit reached ('.config('app.env').')';

        // After the response: a slow mail server never delays the visitor's answer.
        \Illuminate\Support\defer(function () use ($to, $text, $subject) {
            try {
                Mail::raw($text, fn ($m) => $m->to($to)->subject($subject));
            } catch (\Throwable $e) {
                Log::error('AI cost alert e-mail failed', ['error' => $e->getMessage()]);
            }
        });
    }
}
