<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Traits\HasMedia;

class FirstPageContentItem extends Model
{
    use HasFactory, SoftDeletes, HasMedia;

    protected $table = 'first_page_content_items';

    protected $fillable = [
        'first_page_content_id',
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
        return $translation?->title ?? '';
    }

    /**
     * Get the parent content
     */
    public function firstPageContent(): BelongsTo
    {
        return $this->belongsTo(FirstPageContent::class);
    }

    /**
     * Get the status of this item
     */
    public function status(): BelongsTo
    {
        return $this->belongsTo(Status::class);
    }

    /**
     * Get all translations for this item
     */
    public function translations(): HasMany
    {
        return $this->hasMany(FirstPageContentItemTranslation::class);
    }

    /**
     * Scope to get only active items
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

