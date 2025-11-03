<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CityTranslation extends Model
{
    use HasFactory;

    protected $table = 'city_translation';

    protected $fillable = [
        'city_id',
        'locale',
        'name',
        'slug',
    ];

    /**
     * Get the city that owns this translation
     */
    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }
}
