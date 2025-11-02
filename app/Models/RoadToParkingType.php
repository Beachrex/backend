<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoadToParkingType extends Model
{
    use HasFactory;

    protected $table = 'road_to_parking_type';

    protected $fillable = [
        'code',
        'status_id',
        'show_filter',
    ];

    protected $casts = [
        'show_filter' => 'boolean',
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
     * Get all translations for this road to parking type
     */
    public function translations(): HasMany
    {
        return $this->hasMany(RoadToParkingTypeTranslation::class);
    }

    /**
     * Get the status of this road to parking type
     */
    public function status(): BelongsTo
    {
        return $this->belongsTo(Status::class);
    }

    /**
     * Get all beaches with this road to parking type
     */
    public function beaches(): HasMany
    {
        return $this->hasMany(Beach::class);
    }

    /**
     * Scope to get only active road to parking types
     */
    public function scopeActive($query)
    {
        return $query->where('status_id', 1);
    }

    /**
     * Scope to get road to parking types shown in filters
     */
    public function scopeShowInFilter($query)
    {
        return $query->where('show_filter', true);
    }

    /**
     * Scope to get road to parking type by code
     */
    public function scopeByCode($query, string $code)
    {
        return $query->where('code', $code);
    }
}

