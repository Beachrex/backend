<?php

namespace App\Models\Traits;

use App\Models\Media;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasMedia
{
    /**
     * Get all media for this model
     */
    public function media(): MorphMany
    {
        return $this->morphMany(Media::class, 'mediable')->ordered();
    }

    /**
     * Get active media only
     */
    public function activeMedia(): MorphMany
    {
        return $this->morphMany(Media::class, 'mediable')->active()->ordered();
    }

    /**
     * Get media by collection code
     */
    public function mediaInCollection(string $collectionCode): MorphMany
    {
        return $this->morphMany(Media::class, 'mediable')
            ->inCollection($collectionCode)
            ->active()
            ->ordered();
    }

    /**
     * Get media by type code
     */
    public function mediaOfType(string $typeCode): MorphMany
    {
        return $this->morphMany(Media::class, 'mediable')
            ->ofType($typeCode)
            ->active()
            ->ordered();
    }

    /**
     * Get primary media
     */
    public function primaryMedia(): ?Media
    {
        return $this->morphMany(Media::class, 'mediable')
            ->active()
            ->primary()
            ->first();
    }

    /**
     * Get primary media for a specific collection
     */
    public function primaryMediaInCollection(string $collectionCode): ?Media
    {
        return $this->morphMany(Media::class, 'mediable')
            ->inCollection($collectionCode)
            ->active()
            ->primary()
            ->first();
    }

    /**
     * Get all images
     */
    public function images(): MorphMany
    {
        return $this->mediaOfType('image');
    }

    /**
     * Get all videos
     */
    public function videos(): MorphMany
    {
        return $this->mediaOfType('video');
    }

    /**
     * Get 360 spheres
     */
    public function spheres360(): MorphMany
    {
        return $this->mediaOfType('sphere_360');
    }

    /**
     * Check if model has any media
     */
    public function hasMedia(): bool
    {
        return $this->media()->active()->exists();
    }

    /**
     * Check if model has media in a specific collection
     */
    public function hasMediaInCollection(string $collectionCode): bool
    {
        return $this->mediaInCollection($collectionCode)->exists();
    }

    /**
     * Get the count of media items
     */
    public function getMediaCountAttribute(): int
    {
        return $this->media()->active()->count();
    }
}

