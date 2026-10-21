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
        'meta_description',
        'faq',
    ];

    /** TASK-007: `faq` is a list of ['q' => question, 'a' => answer]. */
    protected $casts = [
        'faq' => 'array',
    ];

    public function beach(): BelongsTo
    {
        return $this->belongsTo(Beach::class);
    }
}
