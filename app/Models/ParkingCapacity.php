<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ParkingCapacity extends Model
{
    use HasFactory;

    protected $table = 'parking_capacity';

    protected $fillable = [
        'code',
        'status_id',
    ];

    /**
     * Get all translations for this parking capacity
     */
    public function translations(): HasMany
    {
        return $this->hasMany(ParkingCapacityTranslation::class);
    }

    /**
     * Get the status of this parking capacity
     */
    public function status(): BelongsTo
    {
        return $this->belongsTo(Status::class);
    }

    /**
     * Get all beaches with this parking capacity
     */
    public function beaches(): HasMany
    {
        return $this->hasMany(Beach::class);
    }

    /**
     * Scope to get only active parking capacities
     */
    public function scopeActive($query)
    {
        return $query->where('status_id', 1);
    }

    /**
     * Scope to get parking capacity by code
     */
    public function scopeByCode($query, string $code)
    {
        return $query->where('code', $code);
    }
}

