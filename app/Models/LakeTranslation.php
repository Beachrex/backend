<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LakeTranslation extends Model
{
    protected $table = 'lake_translation';

    protected $fillable = [
        'lake_id',
        'locale',
        'name',
        'slug',
    ];

    /**
     * Get the lake that owns this translation
     */
    public function lake(): BelongsTo
    {
        return $this->belongsTo(Lake::class);
    }
}

