<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

class FirstPageContentTranslation extends Model
{
    use HasFactory;

    protected $table = 'first_page_content_translation';

    protected $fillable = [
        'first_page_content_id',
        'locale',
        'title',
        'description',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    /**
     * Cache invalidation: when a translation is created/updated/deleted, the
     * FirstPageContentController caches the full content list per locale with
     * the key "first_page_content:index:{locale}" and per-code with
     * "first_page_content:show:{code}:{locale}". Clear both for every locale.
     */
    protected static function booted(): void
    {
        $locales = \App\Http\Middleware\SetLocaleFromHeader::SUPPORTED_LOCALES;

        static::saved(function (FirstPageContentTranslation $tr) use ($locales) {
            foreach ($locales as $locale) {
                Cache::forget("first_page_content:index:{$locale}");
            }
            // Also clear the show cache for the parent content's code, if loaded.
            if ($tr->firstPageContent) {
                foreach ($locales as $locale) {
                    Cache::forget("first_page_content:show:{$tr->firstPageContent->code}:{$locale}");
                }
            }
        });

        static::deleted(function (FirstPageContentTranslation $tr) use ($locales) {
            foreach ($locales as $locale) {
                Cache::forget("first_page_content:index:{$locale}");
            }
            if ($tr->firstPageContent) {
                foreach ($locales as $locale) {
                    Cache::forget("first_page_content:show:{$tr->firstPageContent->code}:{$locale}");
                }
            }
        });
    }

    /**
     * Get the content that owns this translation
     */
    public function firstPageContent(): BelongsTo
    {
        return $this->belongsTo(FirstPageContent::class);
    }
}

