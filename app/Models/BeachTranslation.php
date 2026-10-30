<?php

namespace App\Models;

use App\Support\BeachDetailsCache;
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

    /** A saved or deleted translation clears its beach's details cache (owner 2026-10-30). */
    protected static function booted(): void
    {
        $forget = fn (self $translation) => BeachDetailsCache::forget((int) $translation->beach_id);
        static::saved($forget);
        static::deleted($forget);
    }

    public function beach(): BelongsTo
    {
        return $this->belongsTo(Beach::class);
    }
}
