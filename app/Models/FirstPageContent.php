<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Traits\HasMedia;
use App\Traits\Cacheable;

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

