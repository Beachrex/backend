<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Island extends Model
{
    use HasFactory;

    protected $table = 'island';

    protected $fillable = [
        'code',
        'country_code',
        'region_id',
        'latitude',
        'longitude',
        'status_id',
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
     * Get the status of this island
     */
    public function status(): BelongsTo
    {
        return $this->belongsTo(Status::class);
    }

    /**
     * Get the country this island belongs to
     */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'country_code', 'code');
    }

    /**
     * Get the region this island belongs to
     */
    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    /**
     * Get all translations for this island
     */
    public function translations(): HasMany
    {
        return $this->hasMany(IslandTranslation::class);
    }

    /**
     * Get all beaches on this island
     */
    public function beaches(): HasMany
    {
        return $this->hasMany(Beach::class);
    }

    /**
     * Get all badges for this island
     */
    public function badges(): BelongsToMany
    {
        return $this->belongsToMany(Badge::class, 'badge_island');
    }

    /**
     * Get all articles for this island
     */
    public function articles(): BelongsToMany
    {
        return $this->belongsToMany(Article::class, 'article_island');
    }

    /**
     * Scope to get only active islands
     */
    public function scopeActive($query)
    {
        return $query->where('status_id', 1);
    }

    /**
     * Scope to get only islands that have active beaches (status_id = 1)
     */
    public function scopeWithBeaches($query)
    {
        return $query->whereHas('beaches', function ($q) {
            $q->where('status_id', 1);
        });
    }
}

