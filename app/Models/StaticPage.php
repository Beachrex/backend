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
     * creates, updates, or deletes a page. Keys are written by
     * StaticPageController using app()->getLocale() (the backend DB locale),
     * so we clear for all supported backend locales.
     */
    protected static function booted(): void
    {
        static::saved(function (StaticPage $page) {
            // Clear the footer list cache for every supported locale.
            foreach (self::SUPPORTED_LOCALES as $locale) {
                Cache::forget("static_pages:footer:{$locale}");
            }

            // Clear per-page show caches for every slug of this page across locales.
            // Translations are loaded fresh here (after save), so we walk each
            // translation's slug and clear the show key for every locale that may
            // have looked it up (request locale + English fallback both possible).
            foreach ($page->translations as $tr) {
                foreach (self::SUPPORTED_LOCALES as $locale) {
                    Cache::forget("static_pages:show:{$locale}:{$tr->slug}");
                }
            }
        });

        static::deleted(function (StaticPage $page) {
            foreach (self::SUPPORTED_LOCALES as $locale) {
                Cache::forget("static_pages:footer:{$locale}");
            }

            // On force-delete (no soft-deleted row retained), translations are
            // cascade-deleted; clear show caches for any slug we previously had.
            foreach ($page->translations as $tr) {
                foreach (self::SUPPORTED_LOCALES as $locale) {
                    Cache::forget("static_pages:show:{$locale}:{$tr->slug}");
                }
            }
        });
    }
}
