<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AreaDescriptionTranslation extends Model
{
    use HasFactory;

    protected $table = 'area_description_translation';

    protected $fillable = [
        'area_description_id',
        'locale',
        'name',
        'slug',
    ];

    /**
     * Get the area description that owns this translation
     */
    public function areaDescription(): BelongsTo
    {
        return $this->belongsTo(AreaDescription::class);
    }
}

