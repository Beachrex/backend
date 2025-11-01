<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WaterBodyType extends Model
{
    use HasFactory;

    protected $table = 'water_body_type';

    protected $fillable = [
        'code',
        'status_id',
    ];

    /**
     * Get all translations for this water body type
     */
    public function translations(): HasMany
    {
        return $this->hasMany(WaterBodyTypeTranslation::class);
    }

    /**
     * Get the status of this water body type
     */
    public function status(): BelongsTo
    {
        return $this->belongsTo(Status::class);
    }

    /**
     * Get all beaches with this water body type
     */
    public function beaches(): HasMany
    {
        return $this->hasMany(Beach::class);
    }

    /**
     * Scope to get only active water body types
     */
    public function scopeActive($query)
    {
        return $query->where('status_id', 1);
    }
}

