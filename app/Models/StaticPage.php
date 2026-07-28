<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;

class StaticPage extends Model
{
    use SoftDeletes;

    protected $table = 'static_pages';

    protected $fillable = [
        'code',
        'sort_order',
        'is_in_footer',
        'status_id',
    ];

    protected $casts = [
        'is_in_footer' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected $appends = ['title'];

    /**
     * Locales supported by the application (matches SetLocaleFromHeader supported locales,
     * using the database codes — Slovenian is 'sl', not the frontend 'si').
     */
    private const SUPPORTED_LOCALES = [
        'en', 'sl', 'de', 'hr', 'fr', 'es', 'it', 'ua', 'ru',
        'pl', 'cs', 'hu', 'bg', 'sk', 'da', 'pt', 'nl', 'el', 'sq',
    ];

    /**
     * Get the English title from translations (used for Filament list / global search).
     */
    public function getTitleAttribute(): string
    {
        $translation = $this->translations()->where('locale', 'en')->first();
        return $translation?->title ?? $this->code;
    }

    /**
     * Get the status of this page.
     */
    public function status(): BelongsTo
    {
        return $this->belongsTo(Status::class);
    }

    /**
     * Get all translations for this page.
     */
    public function translations(): HasMany
    {
        return $this->hasMany(StaticPageTranslation::class);
    }

    /**
     * Scope to get only active pages (status_id = 1).
     */
    public function scopeActive($query)
    {
        return $query->where('status_id', 1);
    }

    /**
     * Scope to get only pages flagged for the footer.
     */
    public function scopeInFooter($query)
    {
        return $query->where('is_in_footer', true);
    }

    /**
     * Cache invalidation: clear the static-pages cache whenever an admin
     * creates, updates, or deletes a page (or one of its translations).
     * Keys are written by StaticPageController using app()->getLocale()
     * (the backend DB locale), so we clear for all supported backend locales.
     */
    protected static function booted(): void
    {
        static::saved(function (StaticPage $page) {
            self::clearCachesForPage($page);
        });

        static::deleted(function (StaticPage $page) {
            self::clearCachesForPage($page);
        });
    }

    /**
     * Clear the footer list cache (all locales) and the per-page show caches
     * for every slug of the given page across all locales.
     *
     * Public so StaticPageTranslation can delegate to it when a translation is
     * saved/deleted (translation changes do not fire the StaticPage::saved
     * event, so they must invalidate explicitly).
     */
    public static function clearCachesForPage(StaticPage $page): void
    {
        // Clear the footer list cache for every supported locale.
        self::clearFooterCache();

        // Clear per-page show caches for every slug of this page across locales.
        // A new translation may introduce a slug that was never requested before;
        // forgetting a non-existent key is a safe no-op.
        foreach ($page->translations as $tr) {
            foreach (self::SUPPORTED_LOCALES as $locale) {
                Cache::forget("static_pages:show:{$locale}:{$tr->slug}");
            }
        }
    }

    /**
     * Clear the show cache for a single slug across all supported locales.
     * Used when a translation's slug is renamed — the OLD slug's show key
     * must be forgotten, but the freshly-saved parent page only knows the
     * NEW slug, so we clear the original slug explicitly here.
     */
    public static function clearShowCacheForSlug(string $slug): void
    {
        foreach (self::SUPPORTED_LOCALES as $locale) {
            Cache::forget("static_pages:show:{$locale}:{$slug}");
        }
    }

    /**
     * Clear the footer list cache for every supported locale.
     * Public so StaticPageTranslation can clear it without a parent page
     * (e.g. when the parent was cascade-deleted).
     */
    public static function clearFooterCache(): void
    {
        foreach (self::SUPPORTED_LOCALES as $locale) {
            Cache::forget("static_pages:footer:{$locale}");
        }
    }
}
