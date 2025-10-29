<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoadToParkingTypeTranslation extends Model
{
    use HasFactory;

    protected $table = 'road_to_parking_type_translation';

    protected $fillable = [
        'road_to_parking_type_id',
        'locale',
        'name',
        'slug',
    ];

    /**
     * Get the road to parking type that owns this translation
     */
    public function roadToParkingType(): BelongsTo
    {
        return $this->belongsTo(RoadToParkingType::class);
    }
}

