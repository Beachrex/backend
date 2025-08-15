<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BeachTranslation extends Model
{
    protected $table = 'beach_translation';

    protected $fillable = [
        'beach_id',
        'locale',
        'name',
        'description',
        'slug',
    ];

    public function beach(): BelongsTo
    {
        return $this->belongsTo(Beach::class);
    }
}
