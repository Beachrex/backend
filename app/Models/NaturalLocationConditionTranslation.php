<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NaturalLocationConditionTranslation extends Model
{
    use HasFactory;

    protected $table = 'natural_location_condition_translation';

    protected $fillable = [
        'natural_location_condition_id',
        'locale',
        'name',
        'slug',
    ];

    /**
     * Get the condition that owns this translation
     */
    public function naturalLocationCondition(): BelongsTo
    {
        return $this->belongsTo(NaturalLocationCondition::class);
    }
}

