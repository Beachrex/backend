<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BeachLocationTypeTranslation extends Model
{
    use HasFactory;

    protected $table = 'beach_location_type_translation';

    protected $fillable = [
        'beach_location_type_id',
        'locale',
        'name',
        'slug',
    ];

    /**
     * Get the beach location type that owns this translation
     */
    public function beachLocationType(): BelongsTo
    {
        return $this->belongsTo(BeachLocationType::class);
    }
}

