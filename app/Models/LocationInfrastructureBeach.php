<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LocationInfrastructureBeach extends Model
{
    use HasFactory;

    protected $table = 'location_infrastructure_beach';

    protected $fillable = [
        'beach_id',
        'location_infrastructure_id',
        'status_id',
    ];

    /**
     * Get the beach
     */
    public function beach(): BelongsTo
    {
        return $this->belongsTo(Beach::class);
    }

    /**
     * Get the infrastructure
     */
    public function locationInfrastructure(): BelongsTo
    {
        return $this->belongsTo(LocationInfrastructure::class);
    }

    /**
     * Get the status
     */
    public function status(): BelongsTo
    {
        return $this->belongsTo(Status::class);
    }

    /**
     * Scope to get only active records
     */
    public function scopeActive($query)
    {
        return $query->where('status_id', 1);
    }
}

