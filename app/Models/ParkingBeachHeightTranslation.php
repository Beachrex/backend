<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ParkingBeachHeightTranslation extends Model
{
    use HasFactory;

    protected $table = 'parking_beach_height_translation';

    protected $fillable = [
        'parking_beach_height_id',
        'locale',
        'name',
        'slug',
    ];

    /**
     * Get the parking beach height that owns this translation
     */
    public function parkingBeachHeight(): BelongsTo
    {
        return $this->belongsTo(ParkingBeachHeight::class);
    }
}

