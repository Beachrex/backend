<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SportingActivitiesBeach extends Model
{
    use HasFactory;

    protected $table = 'sporting_activities_beach';

    protected $fillable = [
        'beach_id',
        'sporting_activities_id',
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
     * Get the activity
     */
    public function sportingActivities(): BelongsTo
    {
        return $this->belongsTo(SportingActivities::class);
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

