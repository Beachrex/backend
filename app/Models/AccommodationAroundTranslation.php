<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccommodationAroundTranslation extends Model
{
    use HasFactory;

    protected $table = 'accommodation_around_translation';

    protected $fillable = [
        'accommodation_around_id',
        'locale',
        'name',
        'slug',
    ];

    /**
     * Get the accommodation type that owns this translation
     */
    public function accommodationAround(): BelongsTo
    {
        return $this->belongsTo(AccommodationAround::class);
    }
}

