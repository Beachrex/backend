<?php

namespace App\Models;

use App\Traits\Cacheable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

class Waterbody extends Model
{
    use Cacheable;

    protected $table = 'waterbody';

    protected $fillable = [
        'code',
        'status_id',
        'show_filter',
        'icon',
        'filter_order',
        'input_type',
    ];

    protected $casts = [
        'show_filter' => 'boolean',
    ];

    protected $appends = ['name'];

    /**
     * Get all translations for this waterbody
     */
    public function translations(): HasMany
    {
        return $this->hasMany(WaterbodyTranslation::class);
    }

    /**
     * Get the name attribute (English translation for admin display)
     */
    public function getNameAttribute(): string
    {
        $translation = $this->translations()->where('locale', 'en')->first();
        return $translation?->name ?? $this->code;
    }

    /**
     * Get the status of this waterbody
     */
    public function status(): BelongsTo
    {
        return $this->belongsTo(Status::class);
    }

    /**
     * Get all beaches with this waterbody type
     */
    public function beaches(): HasMany
    {
        return $this->hasMany(Beach::class);
    }

    /**
     * Scope to get only waterbodies that should be shown in filters
     */
    public function scopeShowInFilter($query)
    {
        return $query->where('show_filter', true);
    }

    /**
     * Scope to get only active waterbodies
     */
    public function scopeActive($query)
    {
        return $query->where('status_id', 1);
    }

    /**
     * Scope to get waterbody by code
     */
    public function scopeByCode($query, string $code)
    {
        return $query->where('code', $code);
    }

    /**
     * Clear waterbody-specific caches
     */
    public function clearModelCache(): void
    {
        // Clear reference data cache for this model
        $this->cacheService()->clearReferenceCache('Waterbody');

        // Clear specific waterbody caches
        Cache::forget("waterbody:show:{$this->id}");
        Cache::forget('waterbodies:index:all');
        Cache::forget('waterbodies:index:filtered');

        // Clear beach count cache
        $this->cacheService()->clearBeachCountCache();
    }
}

