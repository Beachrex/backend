<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CountryResource extends JsonResource
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
            'full_name' => $this->full_name,
            'iso3' => $this->iso3,
            'continent_code' => $this->continent_code,
            'continent' => $this->when(
                $this->relationLoaded('continent'),
                new ContinentResource($this->continent)
            ),
            'beaches_count' => $this->when(
                $this->relationLoaded('beaches') || isset($this->beaches_count),
                $this->beaches_count ?? $this->beaches->count()
            ),
        ];
    }
}
