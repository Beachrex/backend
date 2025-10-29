<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ParkingBeachDistanceTranslation extends Model
{
    use HasFactory;

    protected $table = 'parking_beach_distance_translation';

    protected $fillable = [
        'parking_beach_distance_id',
        'locale',
        'name',
        'slug',
    ];

    /**
     * Get the parking beach distance that owns this translation
     */
    public function parkingBeachDistance(): BelongsTo
    {
        return $this->belongsTo(ParkingBeachDistance::class);
    }
}

