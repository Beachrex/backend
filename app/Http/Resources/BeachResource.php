<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class BeachResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'status' => $this->status->name ?? null,
            'name' => $this->translations->firstWhere('locale', app()->getLocale())->name ?? $this->name,
            'description' => $this->translations->firstWhere('locale', app()->getLocale())->description ?? null,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
