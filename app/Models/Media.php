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

        // Set status to deleted (3) when soft deleting
        static::deleting(function ($media) {
            // Only set status_id if this is a soft delete (not force delete)
            if (!$media->isForceDeleting()) {
                $media->status_id = 3; // Deleted status
                // Save without triggering events to avoid infinite loop
                $media->saveQuietly();
            }
        });

        // Set status back to active (1) when restoring
        static::restoring(function ($media) {
            $media->status_id = 1; // Active status
        });

        // Delete file from storage when media is deleted (both soft delete and force delete)
        static::deleting(function ($media) {
            try {
                // For DigitalOcean Spaces, we need to add 'public/' prefix to the path
                $filePath = $media->file_path;

                // If using spaces disk and path doesn't start with 'public/', add it
                if ($media->disk === 'spaces' && !str_starts_with($filePath, 'public/')) {
                    $filePath = 'public/' . $filePath;
                }

                // Delete the file from storage
                if (Storage::disk($media->disk)->exists($filePath)) {
                    Storage::disk($media->disk)->delete($filePath);
                    \Log::info('Deleted media file from storage', [
                        'disk' => $media->disk,
                        'path' => $filePath,
                        'media_id' => $media->id
                    ]);
                }
            } catch (\Exception $e) {
                \Log::error('Failed to delete media file from storage', [
                    'error' => $e->getMessage(),
                    'media_id' => $media->id,
                    'file_path' => $media->file_path,
                    'disk' => $media->disk
                ]);
            }
        });
    }
}

