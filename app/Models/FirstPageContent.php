<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Traits\HasMedia;
use App\Traits\Cacheable;
use Illuminate\Support\Facades\Cache;

class FirstPageContent extends Model
{
    use HasFactory, SoftDeletes, HasMedia, Cacheable;

    protected $table = 'first_page_content';

    protected $fillable = [
        'code',
        'section_type',
        'order',
        'config',
        'status_id',
    ];

    protected $casts = [
        'config' => 'array',
        'order' => 'integer',
    ];

    protected $appends = ['title'];

    /**
     * The API caches the homepage for 30 days under "first_page_content:index:{locale}" and
     * "first_page_content:show:{code}:{locale}". Saving a section in admin (status, order, config such as
     * `showcase_beach_id`) must clear both, like a translation save already does. `Cacheable` only clears
     * `reference:FirstPageContent:*`, which these keys are not.
     */
    protected static function booted(): void
    {
        $forget = static function (FirstPageContent $content): void {
            $codes = array_unique(array_filter([$content->code, $content->getOriginal('code')]));
            foreach (\App\Http\Middleware\SetLocaleFromHeader::SUPPORTED_LOCALES as $locale) {
                Cache::forget("first_page_content:index:{$locale}");
                foreach ($codes as $code) {
                    Cache::forget("first_page_content:show:{$code}:{$locale}");
                }
            }
        };

        static::saved($forget);
        static::deleted($forget);
    }

    /**
     * Get the English title from translations
     */
    public function getTitleAttribute(): string
    {
        $translation = $this->translations()->where('locale', 'en')->first();
        return $translation?->title ?? $this->code;
    }

    /**
     * Get the status of this content
     */
    public function status(): BelongsTo
    {
        return $this->belongsTo(Status::class);
    }

    /**
     * Get all translations for this content
     */
    public function translations(): HasMany
    {
        return $this->hasMany(FirstPageContentTranslation::class);
    }

    /**
     * Get all items for this content (for repeater sections)
     */
    public function items(): HasMany
    {
        return $this->hasMany(FirstPageContentItem::class)->orderBy('order');
    }

    /**
     * Scope to get only active content
     */
    public function scopeActive($query)
    {
        return $query->where('status_id', 1);
    }

    /**
     * Scope to order by display order
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('order');
    }
}

