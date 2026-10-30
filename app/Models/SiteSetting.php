<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Per-environment site switches edited in admin (TASK-009). Read through the static helpers, which cache a plain
 * key → value array (never the models) and are cleared on every save/delete.
 */
class SiteSetting extends Model
{
    /** AI chat (chat API, chat page, chat widget). The key name predates the trip planner switch. */
    public const AI_ASSISTANT_ENABLED = 'ai_assistant_enabled';

    /** AI trip planner (paid): itineraries, itinerary plans, credit products and Stripe checkout. */
    public const AI_TRIP_PLANNER_ENABLED = 'ai_trip_planner_enabled';

    /** Google ads (AdSense) on the website. Default OFF (owner 2026-10-29). */
    public const ADS_ENABLED = 'ads_enabled';

    /** Model of the AI chat (AiModelCatalog id); null/missing = env/config default (owner 2026-10-30). */
    public const AI_CHAT_MODEL = 'ai_chat_model';

    /** Model of the AI trip planner narration (AiModelCatalog id); null/missing = env/config default. */
    public const AI_ITINERARY_MODEL = 'ai_itinerary_model';

    public const CACHE_KEY = 'site_settings:all';

    protected $table = 'site_settings';

    protected $fillable = ['key', 'value'];

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget(self::CACHE_KEY));
        static::deleted(fn () => Cache::forget(self::CACHE_KEY));
    }

    /** @return array<string, string|null> */
    public static function allValues(): array
    {
        return Cache::remember(self::CACHE_KEY, 3600, fn () => static::query()->pluck('value', 'key')->all());
    }

    public static function bool(string $key, bool $default): bool
    {
        $values = self::allValues();
        if (!array_key_exists($key, $values) || $values[$key] === null) {
            return $default;
        }

        return filter_var($values[$key], FILTER_VALIDATE_BOOLEAN);
    }

    public static function string(string $key): ?string
    {
        $value = self::allValues()[$key] ?? null;

        return $value === null ? null : (string) $value;
    }

    public static function set(string $key, bool|string|null $value): void
    {
        $stored = is_bool($value) ? ($value ? '1' : '0') : $value;
        static::query()->updateOrCreate(['key' => $key], ['value' => $stored]);
    }

    /** The AI chat (chat API, chat page, chat widget). Default on. */
    public static function aiAssistantEnabled(): bool
    {
        return self::bool(self::AI_ASSISTANT_ENABLED, true);
    }

    /** The paid AI trip planner (itineraries, plans, buying credits). Default on. */
    public static function aiTripPlannerEnabled(): bool
    {
        return self::bool(self::AI_TRIP_PLANNER_ENABLED, true);
    }

    /** Google ads (AdSense) on the website. Default OFF: a missing key means no ads. */
    public static function adsEnabled(): bool
    {
        return self::bool(self::ADS_ENABLED, false);
    }
}
