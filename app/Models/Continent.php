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

    // No timestamps in continents table
    public $timestamps = false;

    protected $fillable = [
        'code',
        'main_image',
    ];

    protected $appends = ['name'];

    /**
     * Get the English name from translations
     */
    public function getNameAttribute(): string
    {
        $translation = $this->translations()->where('locale', 'en')->first();
        return $translation?->name ?? $this->code;
    }

    /**
     * Get all translations for this continent
     */
    public function translations(): HasMany
    {
        return $this->hasMany(ContinentTranslation::class, 'continent_code', 'code');
    }

    /**
     * Get all beaches for this continent
     */
    public function beaches(): HasMany
    {
        return $this->hasMany(Beach::class, 'continent_code', 'code');
    }

    /**
     * Get only active beaches for this continent (status_id = 1)
     */
    public function activeBeaches(): HasMany
    {
        return $this->hasMany(Beach::class, 'continent_code', 'code')
            ->where('status_id', 1);
    }

    /**
     * Scope to get only continents that have active beaches (status_id = 1)
     */
    public function scopeWithBeaches($query)
    {
        return $query->whereHas('beaches', function ($q) {
            $q->where('status_id', 1);
        });
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
