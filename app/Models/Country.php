<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Country extends Model
{
    protected $table = 'countries';

    // No timestamps in countries table
    public $timestamps = false;

    protected $fillable = [
        'code',
        'iso3',
        'number',
        'continent_code',
        'image_path',
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
     * Get all translations for this country
     */
    public function translations(): HasMany
    {
        return $this->hasMany(CountryTranslation::class, 'country_code', 'code');
    }

    /**
     * Get all regions for this country
     */
    public function regions(): HasMany
    {
        return $this->hasMany(Region::class, 'country_code', 'code');
    }

    /**
     * Get all beaches for this country
     */
    public function beaches(): HasMany
    {
        return $this->hasMany(Beach::class, 'country_code', 'code');
    }

    /**
     * Get only active beaches for this country (status_id = 1)
     */
    public function activeBeaches(): HasMany
    {
        return $this->hasMany(Beach::class, 'country_code', 'code')
            ->where('status_id', 1);
    }

    /**
     * Get the continent this country belongs to
     */
    public function continent(): BelongsTo
    {
        return $this->belongsTo(Continent::class, 'continent_code', 'code');
    }

    /**
     * Get all badges for this country
     */
    public function badges(): BelongsToMany
    {
        return $this->belongsToMany(Badge::class, 'badge_country', 'country_code', 'badge_id', 'code');
    }

    /**
     * Get all articles for this country
     */
    public function articles(): BelongsToMany
    {
        return $this->belongsToMany(Article::class, 'article_country', 'country_code', 'article_id', 'code');
    }

    /**
     * Scope to get only countries that have active beaches (status_id = 1)
     */
    public function scopeWithBeaches($query)
    {
        return $query->whereHas('beaches', function ($q) {
            $q->where('status_id', 1);
        });
    }

    /**
     * Scope to filter countries by continent
     */
    public function scopeByContinent($query, $continentCode)
    {
        return $query->where('continent_code', strtoupper($continentCode));
    }
}
