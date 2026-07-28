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
     * Cache invalidation: when a translation is created/updated/deleted, the
     * footer list (localized titles/slugs) and per-page show caches become
     * stale. The StaticPage::saved event does NOT fire on translation changes,
     * so we invalidate explicitly by delegating to StaticPage::clearCachesForPage.
     *
     * The old slug must also be cleared (renaming a slug leaves a stale show
     * key for the old slug), so on update we clear using both the original and
     * the freshly-saved parent page.
     */
    protected static function booted(): void
    {
        static::saved(function (StaticPageTranslation $tr) {
            // If the slug changed, the OLD slug's show cache key is now stale
            // (it would serve the old page under a URL that should 404). The
            // parent page only knows the NEW slug, so clear the original first.
            $originalSlug = $tr->getOriginal('slug');
            if ($originalSlug && $originalSlug !== $tr->slug) {
                StaticPage::clearShowCacheForSlug($originalSlug);
            }

            $page = $tr->staticPage()->with('translations')->first();
            if ($page) {
                StaticPage::clearCachesForPage($page);
            }
        });

        static::deleted(function (StaticPageTranslation $tr) {
            // On delete, clear the deleted translation's own slug show cache
            // (the parent page's translations relation no longer includes it).
            StaticPage::clearShowCacheForSlug($tr->slug);

            $page = $tr->staticPage()->with('translations')->first();
            if ($page) {
                StaticPage::clearCachesForPage($page);
            } else {
                // Parent gone (cascade delete): just clear all footer caches.
                StaticPage::clearFooterCache();
            }
        });
    }

    /**
     * Get the static page that owns this translation.
     */
    public function staticPage(): BelongsTo
    {
        return $this->belongsTo(StaticPage::class);
    }
}
