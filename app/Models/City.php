<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class City extends Model
{
    use HasFactory;

    protected $table = 'city';

    protected $fillable = [
        'code',
        'country_code',
        'region_id',
        'island_id',
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
     * Get the status of this city
     */
    public function status(): BelongsTo
    {
        return $this->belongsTo(Status::class);
    }

    /**
     * Get the country this city belongs to
     */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'country_code', 'code');
    }

    /**
     * Get the region this city belongs to
     */
    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    /**
     * Get the island this city belongs to
     */
    public function island(): BelongsTo
    {
        return $this->belongsTo(Island::class);
    }

    /**
     * Get all translations for this city
     */
    public function translations(): HasMany
    {
        return $this->hasMany(CityTranslation::class);
    }

    /**
     * Get all beaches in this city
     */
    public function beaches(): HasMany
    {
        return $this->hasMany(Beach::class);
    }

    /**
     * Get all badges for this city
     */
    public function badges(): BelongsToMany
    {
        return $this->belongsToMany(Badge::class, 'badge_city');
    }

    /**
     * Get all articles for this city
     */
    public function articles(): BelongsToMany
    {
        return $this->belongsToMany(Article::class, 'article_city');
    }

    /**
     * Scope to get only active cities
     */
    public function scopeActive($query)
    {
        return $query->where('status_id', 1);
    }

    /**
     * Scope to filter cities by country
     */
    public function scopeByCountry($query, $countryCode)
    {
        return $query->where('country_code', strtoupper($countryCode));
    }

    /**
     * Scope to filter cities by region
     */
    public function scopeByRegion($query, $regionId)
    {
        return $query->where('region_id', $regionId);
    }

    /**
     * Scope to get only cities that have active beaches (status_id = 1)
     */
    public function scopeWithBeaches($query)
    {
        return $query->whereHas('beaches', function ($q) {
            $q->where('status_id', 1);
        });
    }
}
