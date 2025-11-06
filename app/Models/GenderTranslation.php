<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GenderTranslation extends Model
{
    protected $table = 'gender_translation';

    protected $fillable = [
        'gender_id',
        'locale',
        'name',
        'slug',
    ];

    /**
     * Get the gender that owns this translation
     */
    public function gender(): BelongsTo
    {
        return $this->belongsTo(Gender::class);
    }
}

