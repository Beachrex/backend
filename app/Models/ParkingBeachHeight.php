<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ParkingBeachHeight extends Model
{
    use HasFactory;

    protected $table = 'parking_beach_height';

    protected $fillable = [
        'code',
        'status_id',
    ];

    /**
     * Get all translations for this parking beach height
     */
    public function translations(): HasMany
    {
        return $this->hasMany(ParkingBeachHeightTranslation::class);
    }

    /**
     * Get the status of this parking beach height
     */
    public function status(): BelongsTo
    {
        return $this->belongsTo(Status::class);
    }

    /**
     * Get all beaches with this parking beach height
     */
    public function beaches(): HasMany
    {
        return $this->hasMany(Beach::class);
    }

    /**
     * Scope to get only active parking beach heights
     */
    public function scopeActive($query)
    {
        return $query->where('status_id', 1);
    }

    /**
     * Scope to get parking beach height by code
     */
    public function scopeByCode($query, string $code)
    {
        return $query->where('code', $code);
    }
}

