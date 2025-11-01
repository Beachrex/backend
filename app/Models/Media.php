<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Media extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'media';

    protected $fillable = [
        'mediable_type',
        'mediable_id',
        'media_type_id',
        'media_collection_id',
        'user_id',
        'file_name',
        'file_path',
        'disk',
        'mime_type',
        'size',
        'width',
        'height',
        'duration',
        'is_primary',
        'order',
        'metadata',
        'status_id',
    ];

    protected $casts = [
        'size' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
        'duration' => 'integer',
        'is_primary' => 'boolean',
        'order' => 'integer',
        'metadata' => 'array',
    ];

    protected $appends = ['url', 'thumbnail_url'];

    /**
     * Get the parent mediable model (Beach, Region, City, etc.)
     */
    public function mediable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the media type
     */
    public function mediaType(): BelongsTo
    {
        return $this->belongsTo(MediaType::class);
    }

    /**
     * Get the media collection
     */
    public function mediaCollection(): BelongsTo
    {
        return $this->belongsTo(MediaCollection::class);
    }

    /**
     * Get the user who uploaded this media
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the status
     */
    public function status(): BelongsTo
    {
        return $this->belongsTo(Status::class);
    }

    /**
     * Scope to get only active media
     */
    public function scopeActive($query)
    {
        return $query->where('status_id', 1);
    }

    /**
     * Scope to get primary media
     */
    public function scopePrimary($query)
    {
        return $query->where('is_primary', true);
    }

    /**
     * Scope to order by display order
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('order')->orderBy('created_at');
    }

    /**
     * Scope to filter by media type code
     */
    public function scopeOfType($query, string $typeCode)
    {
        return $query->whereHas('mediaType', function ($q) use ($typeCode) {
            $q->where('code', $typeCode);
        });
    }

    /**
     * Scope to filter by collection code
     */
    public function scopeInCollection($query, string $collectionCode)
    {
        return $query->whereHas('mediaCollection', function ($q) use ($collectionCode) {
            $q->where('code', $collectionCode);
        });
    }

    /**
     * Get the full URL to the media file
     */
    public function getUrlAttribute(): string
    {
        return Storage::disk($this->disk)->url($this->file_path);
    }

    /**
     * Get the thumbnail URL (for images)
     * This is a placeholder - implement actual thumbnail generation logic
     */
    public function getThumbnailUrlAttribute(): ?string
    {
        if (!$this->isImage()) {
            return null;
        }

        // TODO: Implement thumbnail generation
        // For now, return the original URL
        return $this->url;
    }

    /**
     * Check if media is an image
     */
    public function isImage(): bool
    {
        return str_starts_with($this->mime_type, 'image/');
    }

    /**
     * Check if media is a video
     */
    public function isVideo(): bool
    {
        return str_starts_with($this->mime_type, 'video/');
    }

    /**
     * Get human-readable file size
     */
    public function getHumanReadableSizeAttribute(): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = $this->size;
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Get alt text from metadata
     */
    public function getAltTextAttribute(): ?string
    {
        return $this->metadata['alt_text'] ?? null;
    }

    /**
     * Get caption from metadata
     */
    public function getCaptionAttribute(): ?string
    {
        return $this->metadata['caption'] ?? null;
    }

    /**
     * Delete the physical file when model is deleted
     */
    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($media) {
            if ($media->isForceDeleting()) {
                Storage::disk($media->disk)->delete($media->file_path);
            }
        });
    }
}

