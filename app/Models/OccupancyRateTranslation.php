<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OccupancyRateTranslation extends Model
{
    use HasFactory;

    protected $table = 'occupancy_rate_translation';

    protected $fillable = [
        'occupancy_rate_id',
        'locale',
        'name',
        'slug',
    ];

    /**
     * Get the occupancy rate that owns this translation
     */
    public function occupancyRate(): BelongsTo
    {
        return $this->belongsTo(OccupancyRate::class);
    }
}

