<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class AccommodationAround extends Model
{
    use HasFactory;

    protected $table = 'accommodation_around';

    protected $fillable = [
        'code',
        'status_id',
    ];

    /**
     * Get all translations for this accommodation type
     */
    public function translations(): HasMany
    {
        return $this->hasMany(AccommodationAroundTranslation::class);
    }

    /**
     * Get the status of this accommodation type
     */
    public function status(): BelongsTo
    {
        return $this->belongsTo(Status::class);
    }

    /**
     * Get all beaches with this accommodation type (through pivot table)
     */
    public function beaches(): BelongsToMany
    {
        return $this->belongsToMany(Beach::class, 'accommodation_around_beach')
            ->withPivot('status_id')
            ->withTimestamps();
    }

    /**
     * Scope to get only active accommodation types
     */
    public function scopeActive($query)
    {
        return $query->where('status_id', 1);
    }

    /**
     * Scope to get accommodation type by code
     */
    public function scopeByCode($query, string $code)
    {
        return $query->where('code', $code);
    }
}

