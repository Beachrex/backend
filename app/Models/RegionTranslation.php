<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RegionTranslation extends Model
{
    use HasFactory;

    protected $table = 'region_translation';

    protected $fillable = [
        'region_id',
        'locale',
        'name',
        'slug',
    ];

    /**
     * Get the region that owns this translation
     */
    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }
}

