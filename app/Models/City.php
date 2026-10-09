<?php

namespace App\Models;

use App\Models\Traits\HasAsciiPaths;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Scout\Searchable;

class City extends Model
{
    use HasFactory, Searchable, HasAsciiPaths;

    /** Storage paths kept ASCII-only (TASK-002). */
    protected array $asciiPathAttributes = ['main_image'];

    protected $table = 'city';

    protected $fillable = [
        'code',
        'country_code',
        'region_id',
        'island_id',
        'latitude',
        'longitude',
        'main_image',
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
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query)
    {
        return $query->where('status_id', 1);
    }

    /**
     * Scope to filter cities by country code
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $countryCode Two-letter country code (e.g., HR, IT, SI)
     * @return \Illuminate\Database\Eloquent\Builder
     *
     * @example City::byCountry('HR')->get()  // Get all cities in Croatia
     */
    public function scopeByCountry($query, $countryCode)
    {
        return $query->where('country_code', strtoupper($countryCode));
    }

    /**
     * Scope to filter cities by region (accepts region ID or code)
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int|string $regionIdOrCode Region ID (numeric) or region code (string, e.g., istria, kvarner)
     * @return \Illuminate\Database\Eloquent\Builder
     *
     * @example City::byRegion('istria')->get()  // Get all cities in Istria region
     * @example City::byRegion(5)->get()         // Get all cities in region with ID 5
     */
    public function scopeByRegion($query, $regionIdOrCode)
    {
        // Check if it's numeric (ID) or string (code)
        if (is_numeric($regionIdOrCode)) {
            return $query->where('region_id', $regionIdOrCode);
        }

        // Filter by region code using relationship
        return $query->whereHas('region', function ($q) use ($regionIdOrCode) {
            $q->where('code', $regionIdOrCode);
        });
    }

    /**
     * Scope to filter cities by island (accepts island ID or code)
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int|string $islandIdOrCode Island ID (numeric) or island code (string, e.g., krk, rab, hvar)
     * @return \Illuminate\Database\Eloquent\Builder
     *
     * @example City::byIsland('krk')->get()  // Get all cities on Krk island
     * @example City::byIsland(10)->get()     // Get all cities on island with ID 10
     */
    public function scopeByIsland($query, $islandIdOrCode)
    {
        // Check if it's numeric (ID) or string (code)
        if (is_numeric($islandIdOrCode)) {
            return $query->where('island_id', $islandIdOrCode);
        }

        // Filter by island code using relationship
        return $query->whereHas('island', function ($q) use ($islandIdOrCode) {
            $q->where('code', $islandIdOrCode);
        });
    }

    /**
     * Scope to get only cities that have active beaches (status_id = 1)
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     *
     * @example City::withBeaches()->get()  // Get only cities that have at least one active beach
     */
    public function scopeWithBeaches($query)
    {
        return $query->whereHas('beaches', function ($q) {
            $q->where('status_id', 1);
        });
    }

    /**
     * Get the indexable data array for the model (for Meilisearch)
     *
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        // Load relationships if not already loaded
        $this->loadMissing(['country', 'region', 'island', 'translations']);

        // Get English translation as default
        $enTranslation = $this->translations->where('locale', 'en')->first();

        // Collect all translated names for multi-language search
        $translatedNames = [];
        foreach ($this->translations as $translation) {
            if ($translation->name) {
                $translatedNames[] = $translation->name;
            }
        }

        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $enTranslation?->name ?? $this->code,
            'slug' => $enTranslation?->slug ?? $this->code,
            'country_code' => $this->country_code,
            'country_name' => $this->country?->name ?? '',
            'region_name' => $this->region?->name ?? '',
            'island_name' => $this->island?->name ?? '',
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'main_image' => $this->main_image,
            'type' => 'city',
            // Add all translated names for multi-language search
            'translated_names' => implode(' ', array_unique($translatedNames)),
        ];
    }

    /**
     * Get the index name for the model
     *
     * @return string
     */
    public function searchableAs(): string
    {
        return 'cities';
    }

    /**
     * Determine if the model should be searchable
     *
     * @return bool
     */
    public function shouldBeSearchable(): bool
    {
        // Only index cities with active beaches
        return $this->status_id === 1 && $this->beaches()->where('status_id', 1)->exists();
    }
}
