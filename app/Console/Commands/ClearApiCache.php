<?php

namespace App\Console\Commands;

use App\Services\CacheService;
use Illuminate\Console\Command;

class ClearApiCache extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cache:clear-api
                            {--type= : Type of cache to clear (translations|reference|beach-counts|all)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clear API caches (translations, reference data, beach counts)';

    /**
     * Execute the console command.
     */
    public function handle(CacheService $cacheService)
    {
        $type = $this->option('type') ?? 'all';

        switch ($type) {
            case 'translations':
                $cacheService->clearTranslationCache(null);
                $this->info('✅ Translation caches cleared');
                break;

            case 'reference':
                $cacheService->clearReferenceCache();
                $this->info('✅ Reference data caches cleared');
                break;

            case 'beach-counts':
                $cacheService->clearBeachCountCache();
                $this->info('✅ Beach count caches cleared');
                break;

            case 'all':
                $cacheService->clearAll();
                $this->info('✅ All API caches cleared');
                break;

            default:
                $this->error('Invalid cache type. Use: translations, reference, beach-counts, or all');
                return 1;
        }

        return 0;
    }
}
