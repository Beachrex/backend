<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WaterbodyTranslation extends Model
{
    protected $table = 'waterbody_translation';

    protected $fillable = [
        'waterbody_id',
        'locale',
        'name',
        'slug',
        'filter_title',
    ];

    /**
     * Get the waterbody that owns this translation
     */
    public function waterbody(): BelongsTo
    {
        return $this->belongsTo(Waterbody::class);
    }
}

