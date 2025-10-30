<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OccupancyRate extends Model
{
    use HasFactory;

    protected $table = 'occupancy_rate';

    protected $fillable = [
        'code',
        'status_id',
    ];

    /**
     * Get all translations for this occupancy rate
     */
    public function translations(): HasMany
    {
        return $this->hasMany(OccupancyRateTranslation::class);
    }

    /**
     * Get the status of this occupancy rate
     */
    public function status(): BelongsTo
    {
        return $this->belongsTo(Status::class);
    }

    /**
     * Get all beaches with this occupancy rate
     */
    public function beaches(): HasMany
    {
        return $this->hasMany(Beach::class);
    }

    /**
     * Scope to get only active occupancy rates
     */
    public function scopeActive($query)
    {
        return $query->where('status_id', 1);
    }

    /**
     * Scope to get occupancy rate by code
     */
    public function scopeByCode($query, string $code)
    {
        return $query->where('code', $code);
    }
}

