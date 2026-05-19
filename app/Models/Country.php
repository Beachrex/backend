<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Laravel\Scout\Searchable;

class Country extends Model
{
    use Searchable;
    protected $table = 'countries';

    // No timestamps in countries table
    public $timestamps = false;

    protected $fillable = [
        'code',
        'iso3',
        'number',
        'continent_code',
        'latitude',
        'longitude',
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
     * Get all islands for this country
     */
    public function islands(): HasMany
    {
        return $this->hasMany(Island::class, 'country_code', 'code');
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

    /**
     * Get the indexable data array for the model (for Meilisearch)
     *
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        // Load relationships if not already loaded
        $this->loadMissing(['continent', 'translations']);

        // Get English translation as default
        $enTranslation = $this->translations->where('locale', 'en')->first();

        // Collect all translated names for multi-language search
        $translatedNames = [];
        $translatedFullNames = [];
        foreach ($this->translations as $translation) {
            if ($translation->name) {
                $translatedNames[] = $translation->name;
            }
            if ($translation->full_name) {
                $translatedFullNames[] = $translation->full_name;
            }
        }

        return [
            'id' => $this->id,
            'code' => $this->code,
            'iso3' => $this->iso3,
            'name' => $enTranslation?->name ?? $this->code,
            'full_name' => $enTranslation?->full_name ?? $this->code,
            'slug' => $enTranslation?->slug ?? $this->code,
            'continent_code' => $this->continent_code,
            'continent_name' => $this->continent?->name ?? '',
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'main_image' => $this->image_path,
            'type' => 'country',
            // Add all translated names for multi-language search
            'translated_names' => implode(' ', array_unique($translatedNames)),
            'translated_full_names' => implode(' ', array_unique($translatedFullNames)),
        ];
    }

    /**
     * Get the index name for the model
     *
     * @return string
     */
    public function searchableAs(): string
    {
        return 'countries';
    }

    /**
     * Determine if the model should be searchable
     *
     * @return bool
     */
    public function shouldBeSearchable(): bool
    {
        // Only index countries with active beaches
        return $this->beaches()->where('status_id', 1)->exists();
    }
}
