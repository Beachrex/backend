<?php

namespace App\Models;

use App\Models\Traits\HasAsciiPaths;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Traits\IndexesPlaceNames;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Scout\Searchable;

class Region extends Model
{
    use HasFactory, Searchable, HasAsciiPaths, IndexesPlaceNames;

    /** Storage paths kept ASCII-only (TASK-002). */
    protected array $asciiPathAttributes = ['main_image'];

    protected $table = 'region';

    protected $fillable = [
        'code',
        'country_code',
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
     * Get the status of this region
     */
    public function status(): BelongsTo
    {
        return $this->belongsTo(Status::class);
    }

    /**
     * Get the country this region belongs to
     */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'country_code', 'code');
    }

    /**
     * Get all translations for this region
     */
    public function translations(): HasMany
    {
        return $this->hasMany(RegionTranslation::class);
    }

    /**
     * Get all beaches in this region
     */
    public function beaches(): HasMany
    {
        return $this->hasMany(Beach::class);
    }

    /**
     * Get all islands in this region
     */
    public function islands(): HasMany
    {
        return $this->hasMany(Island::class);
    }

    /**
     * Get all badges for this region
     */
    public function badges(): BelongsToMany
    {
        return $this->belongsToMany(Badge::class, 'badge_region');
    }

    /**
     * Get all articles for this region
     */
    public function articles(): BelongsToMany
    {
        return $this->belongsToMany(Article::class, 'article_region');
    }

    /**
     * Scope to get only active regions
     */
    public function scopeActive($query)
    {
        return $query->where('status_id', 1);
    }

    /**
     * Scope to filter regions by country
     */
    public function scopeByCountry($query, $countryCode)
    {
        return $query->where('country_code', strtoupper($countryCode));
    }

    /**
     * Scope to get only regions that have active beaches (status_id = 1)
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
        $this->loadMissing(['country', 'translations']);

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
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'main_image' => $this->main_image,
            'type' => 'region',
            // Add all translated names for multi-language search
            'translated_names' => implode(' ', array_unique($translatedNames)),
            // Parent places in every language (search by "Grčija", "Dalmacija" …)
            'place_names' => $this->placeNames(),
        ];
    }

    /**
     * Get the index name for the model
     *
     * @return string
     */
    public function searchableAs(): string
    {
        return 'regions';
    }

    /**
     * Determine if the model should be searchable
     *
     * @return bool
     */
    public function shouldBeSearchable(): bool
    {
        // Only index regions with active beaches
        return $this->status_id === 1 && $this->beaches()->where('status_id', 1)->exists();
    }

    /** @return list<string> parent places indexed as `place_names` (search). */
    protected function placeNameRelations(): array
    {
        return ['country'];
    }

    /** Eager-load the place translations for `scout:import` (one query per relation, not per record). */
    protected function makeAllSearchableUsing(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->with($this->placeNameEagerLoads());
    }
}

