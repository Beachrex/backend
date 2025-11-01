<?php

namespace App\Traits;

use Illuminate\Support\Facades\Cache;
use App\Services\CacheService;

trait CacheAware
{
    /**
     * Remember data in cache and track cache status
     *
     * @param string $key Cache key
     * @param int $ttl Time to live in seconds
     * @param callable $callback Callback to fetch data if not cached
     * @return mixed
     */
    protected function cacheRemember(string $key, int $ttl, callable $callback)
    {
        // Check if data exists in cache before calling Cache::remember
        $wasCached = Cache::has($key);

        // Store the cache key and hit status in request attributes for middleware
        request()->attributes->set('cache_key', $key);
        request()->attributes->set('cache_hit', $wasCached);

        // Use Laravel's Cache::remember
        return Cache::remember($key, $ttl, $callback);
    }
}

