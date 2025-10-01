<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContinentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'code' => $this->code,
            'name' => $this->name,
            'beaches_count' => $this->when(
                $this->relationLoaded('beaches') || isset($this->beaches_count),
                $this->beaches_count ?? $this->beaches->count()
            ),
        ];
    }
}
