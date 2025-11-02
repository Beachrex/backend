<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ParkingBeachDistance extends Model
{
    use HasFactory;

    protected $table = 'parking_beach_distance';

    protected $fillable = [
        'code',
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
     * Get all translations for this parking beach distance
     */
    public function translations(): HasMany
    {
        return $this->hasMany(ParkingBeachDistanceTranslation::class);
    }

    /**
     * Get the status of this parking beach distance
     */
    public function status(): BelongsTo
    {
        return $this->belongsTo(Status::class);
    }

    /**
     * Get all beaches with this parking beach distance
     */
    public function beaches(): HasMany
    {
        return $this->hasMany(Beach::class);
    }

    /**
     * Scope to get only active parking beach distances
     */
    public function scopeActive($query)
    {
        return $query->where('status_id', 1);
    }

    /**
     * Scope to get parking beach distance by code
     */
    public function scopeByCode($query, string $code)
    {
        return $query->where('code', $code);
    }
}

