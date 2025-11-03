<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArticleTranslation extends Model
{
    use HasFactory;

    protected $table = 'article_translation';

    protected $fillable = [
        'article_id',
        'locale',
        'title',
        'slug',
        'excerpt',
        'content',
    ];

    /**
     * Get the article that owns this translation
     */
    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }
}

