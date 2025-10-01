<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Country extends Model
{
    protected $table = 'countries';
    
    // Primary key is 'code' instead of 'id'
    protected $primaryKey = 'code';
    
    // Primary key is not auto-incrementing
    public $incrementing = false;
    
    // Primary key is a string
    protected $keyType = 'string';
    
    // No timestamps in countries table
    public $timestamps = false;

    protected $fillable = [
        'code',
        'name',
        'full_name',
        'iso3',
        'number',
        'continent_code',
    ];

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
