<?php

namespace App\Traits;

use App\Services\CacheService;

trait Cacheable
{
    /**
     * Get cache service instance
     *
     * @return CacheService
     */
    protected function cacheService(): CacheService
    {
        return app(CacheService::class);
    }

    /**
     * Clear cache when model is saved
     *
     * @return void
     */
    protected static function bootCacheable(): void
    {
        static::saved(function ($model) {
            $model->clearModelCache();
        });

        static::deleted(function ($model) {
            $model->clearModelCache();
        });
    }

    /**
     * Clear cache for this model
     *
     * @return void
     */
    public function clearModelCache(): void
    {
        $cacheService = $this->cacheService();
        
        // Clear reference data cache for this model
        $modelName = class_basename($this);
        $cacheService->clearReferenceCache($modelName);
        
        // Clear beach count cache if this is a beach-related model
        if (method_exists($this, 'beaches')) {
            $cacheService->clearBeachCountCache();
        }
    }
}

