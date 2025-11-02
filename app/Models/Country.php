<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
    ];

    /**
     * Get all translations for this country
     */
    public function translations(): HasMany
    {
        return $this->hasMany(CountryTranslation::class, 'country_code', 'code');
    }

    /**
     * Get all beaches for this country
     */
    public function beaches(): HasMany
    {
        return $this->hasMany(Beach::class, 'country_code', 'code');
    }

    /**
     * Get the continent this country belongs to
     */
    public function continent(): BelongsTo
    {
        return $this->belongsTo(Continent::class, 'continent_code', 'code');
    }

    /**
     * Scope to get only countries that have beaches
     */
    public function scopeWithBeaches($query)
    {
        return $query->whereHas('beaches');
    }

    /**
     * Scope to filter countries by continent
     */
    public function scopeByContinent($query, $continentCode)
    {
        return $query->where('continent_code', strtoupper($continentCode));
    }
}
