<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BadgeTranslation extends Model
{
    use HasFactory;

    protected $table = 'badge_translation';

    protected $fillable = [
        'badge_id',
        'locale',
        'name',
        'slug',
    ];

    /**
     * Get the badge that owns this translation
     */
    public function badge(): BelongsTo
    {
        return $this->belongsTo(Badge::class);
    }
}

