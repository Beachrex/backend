<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FirstPageContentItemTranslation extends Model
{
    use HasFactory;

    protected $table = 'first_page_content_item_translation';

    protected $fillable = [
        'first_page_content_item_id',
        'locale',
        'title',
        'description',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    /**
     * Get the item that owns this translation
     */
    public function firstPageContentItem(): BelongsTo
    {
        return $this->belongsTo(FirstPageContentItem::class);
    }
}

