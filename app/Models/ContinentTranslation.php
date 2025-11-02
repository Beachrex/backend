<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContinentTranslation extends Model
{
    protected $table = 'continent_translation';

    protected $fillable = [
        'continent_code',
        'locale',
        'name',
        'slug',
    ];

    /**
     * Get the continent that owns this translation
     */
    public function continent(): BelongsTo
    {
        return $this->belongsTo(Continent::class, 'continent_code', 'code');
    }
}
