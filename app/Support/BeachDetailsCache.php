<?php

namespace App\Support;

use App\Http\Middleware\SetLocaleFromHeader;
use Illuminate\Support\Facades\Cache;

/**
 * Cache of `GET /api/v1/beach-details/{identifier}` (owner, 2026-10-30).
 *
 * One entry per beach and locale (id and slug requests share it), kept long: beach data rarely changes once it is
 * in. Every change clears it instead of waiting for the TTL:
 * - the beach, its translations or its media saved/deleted (model events), the beach edit page after saving
 *   (relations saved after the record) and its "Počisti predpomnilnik" button → that beach only;
 * - a beach created, deleted, published/unpublished or moved to another place → every beach (`forgetAll`),
 *   because other beaches show it in their previous/next navigation and in the place beach counts;
 * - `php artisan cache:clear-api --type=all` (the AI content commands say to run it) flushes everything.
 *
 * `forgetAll` bumps a generation number that is part of every key, so old entries are never read again and expire
 * with their TTL (no key scan).
 */
final class BeachDetailsCache
{
    /** 30 days. */
    public const TTL = 2592000;

    private const GENERATION_KEY = 'beach_details:generation';

    public static function key(string $locale, int $beachId): string
    {
        return 'beach_details:g'.self::generation().":{$locale}:{$beachId}";
    }

    /** Clears one beach in every locale. */
    public static function forget(int $beachId): void
    {
        foreach (SetLocaleFromHeader::SUPPORTED_LOCALES as $locale) {
            Cache::forget(self::key($locale, $beachId));
        }
    }

    /** Invalidates every beach in every locale. */
    public static function forgetAll(): void
    {
        Cache::forever(self::GENERATION_KEY, self::generation() + 1);
    }

    private static function generation(): int
    {
        return (int) Cache::rememberForever(self::GENERATION_KEY, fn () => 1);
    }
}
