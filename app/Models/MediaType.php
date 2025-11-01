<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MediaType extends Model
{
    use HasFactory;

    protected $table = 'media_types';

    protected $fillable = [
        'code',
        'name',
        'mime_types',
        'max_file_size',
        'status_id',
    ];

    protected $casts = [
        'mime_types' => 'array',
        'max_file_size' => 'integer',
    ];

    /**
     * Get the status of this media type
     */
    public function status(): BelongsTo
    {
        return $this->belongsTo(Status::class);
    }

    /**
     * Get all media of this type
     */
    public function media(): HasMany
    {
        return $this->hasMany(Media::class);
    }

    /**
     * Scope to get only active media types
     */
    public function scopeActive($query)
    {
        return $query->where('status_id', 1);
    }

    /**
     * Check if a MIME type is allowed for this media type
     */
    public function isMimeTypeAllowed(string $mimeType): bool
    {
        if (empty($this->mime_types)) {
            return true;
        }

        return in_array($mimeType, $this->mime_types);
    }

    /**
     * Check if file size is within limit
     */
    public function isFileSizeAllowed(int $fileSize): bool
    {
        if ($this->max_file_size === null) {
            return true;
        }

        return $fileSize <= $this->max_file_size;
    }
}

