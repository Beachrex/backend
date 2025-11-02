<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BeachTypeTranslation extends Model
{
    use HasFactory;

    protected $table = 'beach_type_translation';

    protected $fillable = [
        'beach_type_id',
        'locale',
        'name',
        'slug',
        'filter_title',
    ];

    /**
     * Get the beach type that owns this translation
     */
    public function beachType(): BelongsTo
    {
        return $this->belongsTo(BeachType::class);
    }
}

