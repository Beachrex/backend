<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WaterBodyTypeTranslation extends Model
{
    use HasFactory;

    protected $table = 'water_body_type_translation';

    protected $fillable = [
        'water_body_type_id',
        'locale',
        'name',
        'slug',
    ];

    /**
     * Get the water body type that owns this translation
     */
    public function waterBodyType(): BelongsTo
    {
        return $this->belongsTo(WaterBodyType::class);
    }
}

