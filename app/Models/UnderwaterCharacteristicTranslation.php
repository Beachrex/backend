<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UnderwaterCharacteristicTranslation extends Model
{
    use HasFactory;

    protected $table = 'underwater_characteristic_translation';

    protected $fillable = [
        'underwater_characteristic_id',
        'locale',
        'name',
        'slug',
    ];

    /**
     * Get the characteristic that owns this translation
     */
    public function underwaterCharacteristic(): BelongsTo
    {
        return $this->belongsTo(UnderwaterCharacteristic::class);
    }
}

