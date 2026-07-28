<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaticPageTranslation extends Model
{
    protected $table = 'static_page_translation';

    protected $fillable = [
        'static_page_id',
        'locale',
        'title',
        'slug',
        'content',
        'meta_title',
        'meta_description',
    ];

    /**
     * Get the static page that owns this translation.
     */
    public function staticPage(): BelongsTo
    {
        return $this->belongsTo(StaticPage::class);
    }
}
