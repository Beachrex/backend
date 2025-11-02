<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CountryTranslation extends Model
{
    protected $table = 'country_translation';

    protected $fillable = [
        'country_code',
        'locale',
        'name',
        'full_name',
        'slug',
    ];

    /**
     * Get the country that owns this translation
     */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'country_code', 'code');
    }
}
