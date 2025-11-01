<?php

namespace App\Models;

use App\Traits\Cacheable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;

class Continent extends Model
{
    use Cacheable;
    protected $table = 'continents';

    // Primary key is 'code' instead of 'id'
    protected $primaryKey = 'code';

    // Primary key is not auto-incrementing
    public $incrementing = false;

    // Primary key is a string
    protected $keyType = 'string';

    // No timestamps in continents table
    public $timestamps = false;

    protected $fillable = [
        'code',
        'name',
    ];

    /**
     * Get all beaches for this continent
     */
    public function beaches(): HasMany
    {
        return $this->hasMany(Beach::class, 'continent_code', 'code');
    }

    /**
     * Scope to get only continents that have beaches
     */
    public function scopeWithBeaches($query)
    {
        return $query->whereHas('beaches');
    }

    /**
     * Clear continent-specific caches
     */
    public function clearModelCache(): void
    {
        // Clear reference data cache for this model
        $this->cacheService()->clearReferenceCache('Continent');

        // Clear specific continent caches
        Cache::forget("continent:show:{$this->code}");
        Cache::forget('continents:index');

        // Clear beach count cache
        $this->cacheService()->clearBeachCountCache();
    }
}
