<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BayLengthTranslation extends Model
{
    use HasFactory;

    protected $table = 'bay_length_translation';

    protected $fillable = [
        'bay_length_id',
        'locale',
        'name',
        'slug',
    ];

    /**
     * Get the bay length that owns this translation
     */
    public function bayLength(): BelongsTo
    {
        return $this->belongsTo(BayLength::class);
    }
}

