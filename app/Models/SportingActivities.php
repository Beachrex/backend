<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class SportingActivities extends Model
{
    use HasFactory;

    protected $table = 'sporting_activities';

    protected $fillable = [
        'code',
        'status_id',
    ];

    /**
     * Get all translations for this activity
     */
    public function translations(): HasMany
    {
        return $this->hasMany(SportingActivitiesTranslation::class);
    }

    /**
     * Get the status of this activity
     */
    public function status(): BelongsTo
    {
        return $this->belongsTo(Status::class);
    }

    /**
     * Get all beaches with this activity (through pivot table)
     */
    public function beaches(): BelongsToMany
    {
        return $this->belongsToMany(Beach::class, 'sporting_activities_beach')
            ->withPivot('status_id')
            ->withTimestamps();
    }

    /**
     * Scope to get only active activities
     */
    public function scopeActive($query)
    {
        return $query->where('status_id', 1);
    }

    /**
     * Scope to get activity by code
     */
    public function scopeByCode($query, string $code)
    {
        return $query->where('code', $code);
    }
}

