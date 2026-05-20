<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class ClearSearchCache extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cache:clear-search';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clear all search-related cache entries';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Clearing search cache...');

        // Get cache store
        $cache = Cache::store();

        // Clear all keys that start with "search:"
        // Note: This requires a cache driver that supports tags or pattern matching
        // For Redis, we can use SCAN to find keys
        
        $driver = config('cache.default');

        if ($driver === 'redis') {
            $this->clearRedisSearchCache();
        } else {
            $this->warn('Cache driver is not Redis. Clearing entire cache instead.');
            Cache::flush();
        }

        $this->info('Search cache cleared successfully!');

        return self::SUCCESS;
    }

    /**
     * Clear search cache from Redis using SCAN
     */
    protected function clearRedisSearchCache(): void
    {
        $redis = Cache::connection()->client();
        $prefix = config('cache.prefix') . ':search:';
        $cursor = '0';
        $deleted = 0;

        do {
            // Use SCAN to find keys matching our pattern
            $result = $redis->scan($cursor, [
                'MATCH' => $prefix . '*',
                'COUNT' => 100
            ]);

            $cursor = $result[0];
            $keys = $result[1] ?? [];

            foreach ($keys as $key) {
                $redis->del($key);
                $deleted++;
            }

        } while ($cursor !== '0');

        $this->info("Deleted {$deleted} search cache entries.");
    }
}

