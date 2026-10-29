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
    public const AI_ASSISTANT_ENABLED = 'ai_assistant_enabled';

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

    public static function set(string $key, bool|string|null $value): void
    {
        $stored = is_bool($value) ? ($value ? '1' : '0') : $value;
        static::query()->updateOrCreate(['key' => $key], ['value' => $stored]);
    }

    /** The AI beach guide (chat, itineraries, buying credits). Default on. */
    public static function aiAssistantEnabled(): bool
    {
        return self::bool(self::AI_ASSISTANT_ENABLED, true);
    }
}
