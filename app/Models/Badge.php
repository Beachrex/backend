<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Badge extends Model
{
    use HasFactory;

    protected $table = 'badge';

    protected $fillable = [
        'code',
        'icon',
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
     * Get the status of this badge
     */
    public function status(): BelongsTo
    {
        return $this->belongsTo(Status::class);
    }

    /**
     * Get all translations for this badge
     */
    public function translations(): HasMany
    {
        return $this->hasMany(BadgeTranslation::class);
    }

    /**
     * Get all regions that have this badge
     */
    public function regions(): BelongsToMany
    {
        return $this->belongsToMany(Region::class, 'badge_region');
    }

    /**
     * Get all countries that have this badge
     */
    public function countries(): BelongsToMany
    {
        return $this->belongsToMany(Country::class, 'badge_country', 'badge_id', 'country_code');
    }

    /**
     * Get all islands that have this badge
     */
    public function islands(): BelongsToMany
    {
        return $this->belongsToMany(Island::class, 'badge_island');
    }

    /**
     * Get all cities that have this badge
     */
    public function cities(): BelongsToMany
    {
        return $this->belongsToMany(City::class, 'badge_city');
    }

    /**
     * Scope to get only active badges
     */
    public function scopeActive($query)
    {
        return $query->where('status_id', 1);
    }
}

