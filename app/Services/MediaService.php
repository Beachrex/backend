<?php

namespace App\Services;

use App\Models\Media;
use App\Models\MediaType;
use App\Models\MediaCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Exception;

class MediaService
{
    /**
     * Upload media file and create media record
     *
     * @param UploadedFile $file
     * @param Model $model The model to attach media to (Beach, Region, etc.)
     * @param string $mediaTypeCode Media type code (image, video, etc.)
     * @param string|null $collectionCode Optional collection code
     * @param array $options Additional options (is_primary, order, metadata, etc.)
     * @return Media
     * @throws Exception
     */
    public function upload(
        UploadedFile $file,
        Model $model,
        string $mediaTypeCode,
        ?string $collectionCode = null,
        array $options = []
    ): Media {
        // Validate media type
        $mediaType = MediaType::where('code', $mediaTypeCode)->active()->firstOrFail();

        // Validate MIME type
        if (!$mediaType->isMimeTypeAllowed($file->getMimeType())) {
            throw new Exception("File type {$file->getMimeType()} is not allowed for media type {$mediaTypeCode}");
        }

        // Validate file size
        if (!$mediaType->isFileSizeAllowed($file->getSize())) {
            $maxSize = $mediaType->max_file_size / 1048576; // Convert to MB
            throw new Exception("File size exceeds maximum allowed size of {$maxSize}MB");
        }

        // Validate collection if provided
        $mediaCollection = null;
        if ($collectionCode) {
            $mediaCollection = MediaCollection::where('code', $collectionCode)->active()->firstOrFail();

            // Check if media type is allowed in collection
            if (!$mediaCollection->isMediaTypeAllowed($mediaTypeCode)) {
                throw new Exception("Media type {$mediaTypeCode} is not allowed in collection {$collectionCode}");
            }

            // Check if collection has reached max items
            if ($mediaCollection->hasReachedMaxItems(get_class($model), $model->id)) {
                throw new Exception("Collection {$collectionCode} has reached maximum items limit");
            }
        }

        DB::beginTransaction();
        try {
            // Generate unique filename
            $originalName = $file->getClientOriginalName();
            $extension = $file->getClientOriginalExtension();
            $filename = Str::slug(pathinfo($originalName, PATHINFO_FILENAME)) . '_' . time() . '_' . Str::random(8) . '.' . $extension;

            // Determine storage path
            $modelName = class_basename($model);
            $folder = $collectionCode ?? $mediaTypeCode;
            $path = "{$modelName}/{$model->id}/{$folder}/{$filename}";

            // Store file
            $disk = $options['disk'] ?? config('filesystems.default');
            $storedPath = Storage::disk($disk)->putFileAs(
                dirname($path),
                $file,
                basename($path)
            );

            // Get image dimensions if it's an image
            $dimensions = $this->getImageDimensions($file);

            // Get video duration if it's a video
            $duration = $this->getVideoDuration($file);

            // Determine order
            $order = $options['order'] ?? $this->getNextOrder($model, $collectionCode);

            // Handle is_primary flag
            $isPrimary = $options['is_primary'] ?? false;
            if ($isPrimary) {
                $this->unsetPrimaryMedia($model, $collectionCode);
            }

            // Create media record
            $media = new Media([
                'mediable_type' => get_class($model),
                'mediable_id' => $model->id,
                'media_type_id' => $mediaType->id,
                'media_collection_id' => $mediaCollection?->id,
                'user_id' => $options['user_id'] ?? auth()->id(),
                'file_name' => $originalName,
                'file_path' => $storedPath,
                'disk' => $disk,
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
                'width' => $dimensions['width'] ?? null,
                'height' => $dimensions['height'] ?? null,
                'duration' => $duration,
                'is_primary' => $isPrimary,
                'order' => $order,
                'metadata' => $options['metadata'] ?? [],
                'status_id' => $options['status_id'] ?? 1,
            ]);

            $media->save();

            DB::commit();

            return $media->load(['mediaType', 'mediaCollection', 'user']);

        } catch (Exception $e) {
            DB::rollBack();

            // Clean up uploaded file if it exists
            if (isset($storedPath)) {
                Storage::disk($disk ?? config('filesystems.default'))->delete($storedPath);
            }

            throw $e;
        }
    }

    /**
     * Update media metadata
     */
    public function updateMetadata(Media $media, array $metadata): Media
    {
        $currentMetadata = $media->metadata ?? [];
        $media->metadata = array_merge($currentMetadata, $metadata);
        $media->save();

        return $media;
    }

    /**
     * Set media as primary
     */
    public function setPrimary(Media $media): Media
    {
        DB::transaction(function () use ($media) {
            // Unset other primary media
            $this->unsetPrimaryMedia(
                $media->mediable,
                $media->mediaCollection?->code
            );

            // Set this as primary
            $media->is_primary = true;
            $media->save();
        });

        return $media;
    }

    /**
     * Reorder media items
     */
    public function reorder(array $mediaIds): void
    {
        DB::transaction(function () use ($mediaIds) {
            foreach ($mediaIds as $order => $mediaId) {
                Media::where('id', $mediaId)->update(['order' => $order]);
            }
        });
    }

    /**
     * Delete media
     */
    public function delete(Media $media, bool $force = false): bool
    {
        if ($force) {
            return $media->forceDelete();
        }

        return $media->delete();
    }

    /**
     * Get image dimensions
     */
    protected function getImageDimensions(UploadedFile $file): ?array
    {
        if (!str_starts_with($file->getMimeType(), 'image/')) {
            return null;
        }

        try {
            $imageSize = getimagesize($file->getRealPath());
            return [
                'width' => $imageSize[0] ?? null,
                'height' => $imageSize[1] ?? null,
            ];
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Get video duration (placeholder - requires FFmpeg)
     */
    protected function getVideoDuration(UploadedFile $file): ?int
    {
        if (!str_starts_with($file->getMimeType(), 'video/')) {
            return null;
        }

        // TODO: Implement FFmpeg integration for video duration
        // For now, return null
        return null;
    }

    /**
     * Get next order number for media
     */
    protected function getNextOrder(Model $model, ?string $collectionCode = null): int
    {
        $query = Media::where('mediable_type', get_class($model))
            ->where('mediable_id', $model->id);

        if ($collectionCode) {
            $query->whereHas('mediaCollection', function ($q) use ($collectionCode) {
                $q->where('code', $collectionCode);
            });
        }

        return $query->max('order') + 1;
    }

    /**
     * Unset primary media for a model/collection
     */
    protected function unsetPrimaryMedia(Model $model, ?string $collectionCode = null): void
    {
        $query = Media::where('mediable_type', get_class($model))
            ->where('mediable_id', $model->id)
            ->where('is_primary', true);

        if ($collectionCode) {
            $query->whereHas('mediaCollection', function ($q) use ($collectionCode) {
                $q->where('code', $collectionCode);
            });
        }

        $query->update(['is_primary' => false]);
    }
}

