<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class LocationInfrastructure extends Model
{
    use HasFactory;

    protected $table = 'location_infrastructure';

    protected $fillable = [
        'code',
        'status_id',
    ];

    /**
     * Get all translations for this infrastructure
     */
    public function translations(): HasMany
    {
        return $this->hasMany(LocationInfrastructureTranslation::class);
    }

    /**
     * Get the status of this infrastructure
     */
    public function status(): BelongsTo
    {
        return $this->belongsTo(Status::class);
    }

    /**
     * Get all beaches with this infrastructure (through pivot table)
     */
    public function beaches(): BelongsToMany
    {
        return $this->belongsToMany(Beach::class, 'location_infrastructure_beach')
            ->withPivot('status_id')
            ->withTimestamps();
    }

    /**
     * Scope to get only active infrastructure
     */
    public function scopeActive($query)
    {
        return $query->where('status_id', 1);
    }

    /**
     * Scope to get infrastructure by code
     */
    public function scopeByCode($query, string $code)
    {
        return $query->where('code', $code);
    }
}

