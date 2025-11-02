<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IslandTranslation extends Model
{
    protected $table = 'island_translation';

    protected $fillable = [
        'island_id',
        'locale',
        'name',
        'slug',
    ];

    /**
     * Get the island that owns this translation
     */
    public function island(): BelongsTo
    {
        return $this->belongsTo(Island::class);
    }
}
