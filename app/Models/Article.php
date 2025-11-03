<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Article extends Model
{
    use HasFactory;

    protected $table = 'article';

    protected $fillable = [
        'code',
        'category',
        'feature_image',
        'status_id',
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
     * Get the status of this article
     */
    public function status(): BelongsTo
    {
        return $this->belongsTo(Status::class);
    }

    /**
     * Get all translations for this article
     */
    public function translations(): HasMany
    {
        return $this->hasMany(ArticleTranslation::class);
    }

    /**
     * Get all regions that have this article
     */
    public function regions(): BelongsToMany
    {
        return $this->belongsToMany(Region::class, 'article_region');
    }

    /**
     * Get all countries that have this article
     */
    public function countries(): BelongsToMany
    {
        return $this->belongsToMany(Country::class, 'article_country', 'article_id', 'country_code');
    }

    /**
     * Get all islands that have this article
     */
    public function islands(): BelongsToMany
    {
        return $this->belongsToMany(Island::class, 'article_island');
    }

    /**
     * Get all cities that have this article
     */
    public function cities(): BelongsToMany
    {
        return $this->belongsToMany(City::class, 'article_city');
    }

    /**
     * Scope to get only active articles
     */
    public function scopeActive($query)
    {
        return $query->where('status_id', 1);
    }

    /**
     * Scope to filter articles by category
     */
    public function scopeByCategory($query, $category)
    {
        return $query->where('category', $category);
    }
}

