<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BeachInclinationTranslation extends Model
{
    use HasFactory;

    protected $table = 'beach_inclination_translation';

    protected $fillable = [
        'beach_inclination_id',
        'locale',
        'name',
        'slug',
    ];

    /**
     * Get the beach inclination that owns this translation
     */
    public function beachInclination(): BelongsTo
    {
        return $this->belongsTo(BeachInclination::class);
    }
}

