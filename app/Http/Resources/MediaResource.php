<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MediaResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'media_type' => [
                'id' => $this->mediaType->id,
                'code' => $this->mediaType->code,
                'name' => $this->mediaType->name,
            ],
            'media_collection' => $this->when($this->mediaCollection, [
                'id' => $this->mediaCollection?->id,
                'code' => $this->mediaCollection?->code,
                'name' => $this->mediaCollection?->name,
            ]),
            'file_name' => $this->file_name,
            'url' => $this->url,
            'thumbnail_url' => $this->when($this->isImage(), $this->thumbnail_url),

            'mime_type' => $this->mime_type,
            'size' => $this->size,
            'human_readable_size' => $this->human_readable_size,
            'width' => $this->when($this->width, $this->width),
            'height' => $this->when($this->height, $this->height),
            'duration' => $this->when($this->duration, $this->duration),
            'is_primary' => $this->is_primary,
            'order' => $this->order,
            'alt_text' => $this->alt_text,
            'caption' => $this->caption,
            'metadata' => $this->when($request->user(), $this->metadata),
            'uploaded_by' => $this->when($this->user, [
                'id' => $this->user?->id,
                'name' => $this->user?->name,
            ]),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}

