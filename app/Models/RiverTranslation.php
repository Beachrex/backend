<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RiverTranslation extends Model
{
    protected $table = 'river_translation';

    protected $fillable = [
        'river_id',
        'locale',
        'name',
        'slug',
    ];

    /**
     * Get the river that owns this translation
     */
    public function river(): BelongsTo
    {
        return $this->belongsTo(River::class);
    }
}

