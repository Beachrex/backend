<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LocationLength extends Model
{
    use HasFactory;

    protected $table = 'location_length';

    protected $fillable = [
        'code',
        'status_id',
    ];

    /**
     * Get all translations for this location length
     */
    public function translations(): HasMany
    {
        return $this->hasMany(LocationLengthTranslation::class);
    }

    /**
     * Get the status of this location length
     */
    public function status(): BelongsTo
    {
        return $this->belongsTo(Status::class);
    }

    /**
     * Get all beaches with this location length
     */
    public function beaches(): HasMany
    {
        return $this->hasMany(Beach::class);
    }

    /**
     * Scope to get only active location lengths
     */
    public function scopeActive($query)
    {
        return $query->where('status_id', 1);
    }
}
