<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class AddCacheHeaders
{
    /**
     * Handle an incoming request and add cache-related headers to the response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Store the start time to measure execution time
        $startTime = microtime(true);

        // Process the request
        $response = $next($request);

        // Get the cache key from the request attributes (set by controller)
        $cacheKey = $request->attributes->get('cache_key');
        $wasCached = $request->attributes->get('cache_hit', false);

        // Only add headers for successful JSON responses
        if ($response->isSuccessful() && $response->headers->get('Content-Type') === 'application/json') {
            // Add cache status header
            if ($cacheKey) {
                $response->headers->set('X-Cache-Status', $wasCached ? 'HIT' : 'MISS');
                $response->headers->set('X-Cache-Key', $this->sanitizeCacheKey($cacheKey));
            } else {
                $response->headers->set('X-Cache-Status', 'BYPASS');
            }

            // Add execution time header
            $executionTime = round((microtime(true) - $startTime) * 1000, 2);
            $response->headers->set('X-Response-Time', $executionTime . 'ms');

            // Add locale header
            $response->headers->set('X-Locale', app()->getLocale());

            // In debug mode, add cache info to response body
            if (config('app.debug') && $request->query('debug') === 'true') {
                $content = json_decode($response->getContent(), true);

                if (is_array($content)) {
                    $content['_cache_debug'] = [
                        'status' => $wasCached ? 'HIT' : ($cacheKey ? 'MISS' : 'BYPASS'),
                        'key' => $cacheKey ? $this->sanitizeCacheKey($cacheKey) : null,
                        'locale' => app()->getLocale(),
                        'response_time_ms' => $executionTime,
                        'timestamp' => now()->toIso8601String(),
                    ];

                    $response->setContent(json_encode($content));
                }
            }
        }

        return $response;
    }

    /**
     * Sanitize cache key by removing Laravel prefix for cleaner output
     *
     * @param string $cacheKey
     * @return string
     */
    private function sanitizeCacheKey(string $cacheKey): string
    {
        // Remove Laravel cache prefix if present
        $prefix = config('cache.prefix');
        if ($prefix && str_starts_with($cacheKey, $prefix)) {
            return substr($cacheKey, strlen($prefix));
        }

        return $cacheKey;
    }
}

