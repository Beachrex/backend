<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LocationLengthTranslation extends Model
{
    use HasFactory;

    protected $table = 'location_length_translation';

    protected $fillable = [
        'location_length_id',
        'locale',
        'name',
        'slug',
    ];

    /**
     * Get the location length that owns this translation
     */
    public function locationLength(): BelongsTo
    {
        return $this->belongsTo(LocationLength::class);
    }
}
