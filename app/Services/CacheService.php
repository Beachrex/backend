<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class CacheService
{
    /**
     * Cache TTL in seconds
     */
    const TRANSLATION_TTL = 86400; // 24 hours
    const REFERENCE_DATA_TTL = 3600; // 1 hour
    const BEACH_COUNT_TTL = 300; // 5 minutes

    /**
     * Cache key prefixes
     */
    const PREFIX_TRANSLATION = 'translation';
    const PREFIX_REFERENCE = 'reference';
    const PREFIX_BEACH_COUNT = 'beach_count';

    /**
     * Get cached translation
     *
     * @param string $table Table name (e.g., 'waterbody')
     * @param int $foreignId Foreign key ID
     * @param string $locale Locale code
     * @param callable $callback Callback to fetch data if not cached
     * @return mixed
     */
    public function getTranslation(string $table, int $foreignId, string $locale, callable $callback)
    {
        $key = $this->translationKey($table, $foreignId, $locale);
        return Cache::remember($key, self::TRANSLATION_TTL, $callback);
    }

    /**
     * Get all translations for a model
     *
     * @param string $table Table name
     * @param int $foreignId Foreign key ID
     * @param callable $callback Callback to fetch data if not cached
     * @return mixed
     */
    public function getAllTranslations(string $table, int $foreignId, callable $callback)
    {
        $key = $this->translationKey($table, $foreignId, 'all');
        return Cache::remember($key, self::TRANSLATION_TTL, $callback);
    }

    /**
     * Get cached reference data (waterbody, beach_type, etc.)
     *
     * @param string $model Model name (e.g., 'Waterbody')
     * @param string $method Method name (e.g., 'active', 'showInFilter')
     * @param array $params Additional parameters
     * @param callable $callback Callback to fetch data if not cached
     * @return mixed
     */
    public function getReferenceData(string $model, string $method, array $params, callable $callback)
    {
        $key = $this->referenceDataKey($model, $method, $params);
        return Cache::remember($key, self::REFERENCE_DATA_TTL, $callback);
    }

    /**
     * Get cached beach count
     *
     * @param string $type Type of count (e.g., 'continent', 'country', 'waterbody')
     * @param string|int $identifier Identifier (code or ID)
     * @param callable $callback Callback to fetch data if not cached
     * @return int
     */
    public function getBeachCount(string $type, $identifier, callable $callback): int
    {
        $key = $this->beachCountKey($type, $identifier);
        return Cache::remember($key, self::BEACH_COUNT_TTL, $callback);
    }

    /**
     * Clear translation cache for a specific model
     *
     * @param string $table Table name
     * @param int|null $foreignId Foreign key ID (null to clear all)
     * @return void
     */
    public function clearTranslationCache(string $table, ?int $foreignId = null): void
    {
        if ($foreignId) {
            // Clear specific model translations
            $pattern = self::PREFIX_TRANSLATION . ":{$table}:{$foreignId}:*";
            $this->clearByPattern($pattern);
        } else {
            // Clear all translations for table
            $pattern = self::PREFIX_TRANSLATION . ":{$table}:*";
            $this->clearByPattern($pattern);
        }
    }

    /**
     * Clear reference data cache
     *
     * @param string|null $model Model name (null to clear all)
     * @return void
     */
    public function clearReferenceCache(?string $model = null): void
    {
        if ($model) {
            $pattern = self::PREFIX_REFERENCE . ":{$model}:*";
            $this->clearByPattern($pattern);
        } else {
            $pattern = self::PREFIX_REFERENCE . ":*";
            $this->clearByPattern($pattern);
        }
    }

    /**
     * Clear beach count cache
     *
     * @param string|null $type Type of count (null to clear all)
     * @return void
     */
    public function clearBeachCountCache(?string $type = null): void
    {
        if ($type) {
            $pattern = self::PREFIX_BEACH_COUNT . ":{$type}:*";
            $this->clearByPattern($pattern);
        } else {
            $pattern = self::PREFIX_BEACH_COUNT . ":*";
            $this->clearByPattern($pattern);
        }
    }

    /**
     * Clear all caches
     *
     * @return void
     */
    public function clearAll(): void
    {
        Cache::flush();
    }

    /**
     * Generate translation cache key
     *
     * @param string $table
     * @param int $foreignId
     * @param string $locale
     * @return string
     */
    private function translationKey(string $table, int $foreignId, string $locale): string
    {
        return self::PREFIX_TRANSLATION . ":{$table}:{$foreignId}:{$locale}";
    }

    /**
     * Generate reference data cache key
     *
     * @param string $model
     * @param string $method
     * @param array $params
     * @return string
     */
    private function referenceDataKey(string $model, string $method, array $params): string
    {
        $paramsHash = md5(json_encode($params));
        return self::PREFIX_REFERENCE . ":{$model}:{$method}:{$paramsHash}";
    }

    /**
     * Generate beach count cache key
     *
     * @param string $type
     * @param string|int $identifier
     * @return string
     */
    private function beachCountKey(string $type, $identifier): string
    {
        return self::PREFIX_BEACH_COUNT . ":{$type}:{$identifier}";
    }

    /**
     * Clear cache by pattern (Redis only)
     *
     * @param string $pattern
     * @return void
     */
    private function clearByPattern(string $pattern): void
    {
        if (config('cache.default') === 'redis') {
            $redis = Cache::getRedis();
            $keys = $redis->keys($pattern);
            if (!empty($keys)) {
                $redis->del($keys);
            }
        } else {
            // For non-Redis drivers, flush all cache
            Cache::flush();
        }
    }
}

