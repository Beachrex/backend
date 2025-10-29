<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ParkingCapacityTranslation extends Model
{
    use HasFactory;

    protected $table = 'parking_capacity_translation';

    protected $fillable = [
        'parking_capacity_id',
        'locale',
        'name',
        'slug',
    ];

    /**
     * Get the parking capacity that owns this translation
     */
    public function parkingCapacity(): BelongsTo
    {
        return $this->belongsTo(ParkingCapacity::class);
    }
}

