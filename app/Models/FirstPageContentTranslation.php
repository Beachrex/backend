<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
     * Get the content that owns this translation
     */
    public function firstPageContent(): BelongsTo
    {
        return $this->belongsTo(FirstPageContent::class);
    }
}

