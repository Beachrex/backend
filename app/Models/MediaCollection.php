<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MediaCollection extends Model
{
    use HasFactory;

    protected $table = 'media_collections';

    protected $fillable = [
        'code',
        'name',
        'description',
        'allowed_media_types',
        'max_items',
        'status_id',
    ];

    protected $casts = [
        'allowed_media_types' => 'array',
        'max_items' => 'integer',
    ];

    /**
     * Get the status of this collection
     */
    public function status(): BelongsTo
    {
        return $this->belongsTo(Status::class);
    }

    /**
     * Get all media in this collection
     */
    public function media(): HasMany
    {
        return $this->hasMany(Media::class);
    }

    /**
     * Scope to get only active collections
     */
    public function scopeActive($query)
    {
        return $query->where('status_id', 1);
    }

    /**
     * Check if a media type is allowed in this collection
     */
    public function isMediaTypeAllowed(string $mediaTypeCode): bool
    {
        if (empty($this->allowed_media_types)) {
            return true;
        }

        return in_array($mediaTypeCode, $this->allowed_media_types);
    }

    /**
     * Check if collection has reached max items for a specific entity
     */
    public function hasReachedMaxItems(string $mediableType, int $mediableId): bool
    {
        if ($this->max_items === null) {
            return false;
        }

        $count = $this->media()
            ->where('mediable_type', $mediableType)
            ->where('mediable_id', $mediableId)
            ->where('status_id', 1)
            ->count();

        return $count >= $this->max_items;
    }
}

