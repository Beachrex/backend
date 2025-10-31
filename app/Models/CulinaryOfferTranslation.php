<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CulinaryOfferTranslation extends Model
{
    use HasFactory;

    protected $table = 'culinary_offer_translation';

    protected $fillable = [
        'culinary_offer_id',
        'locale',
        'name',
        'slug',
    ];

    /**
     * Get the culinary offer that owns this translation
     */
    public function culinaryOffer(): BelongsTo
    {
        return $this->belongsTo(CulinaryOffer::class);
    }
}

