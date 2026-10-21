<?php

namespace App\Http\Resources;

use App\Support\BeachGallery;
use Illuminate\Http\Resources\Json\JsonResource;

class BeachDetailsResource extends JsonResource
{
    public function toArray($request)
    {
        $locale = app()->getLocale();

        // Get translation for current locale, fallback to English
        $translation = $this->translations->firstWhere('locale', $locale);
        $translationEn = $this->translations->firstWhere('locale', 'en');

        return [
            'basic_info' => $this->getBasicInfo($translation, $translationEn),
            'location' => $this->getLocationInfo($locale),
            'characteristics' => $this->getCharacteristics($locale),
            'accessibility' => $this->getAccessibilityInfo($locale),
            'dimensions' => $this->getDimensions($locale),
            'facilities' => $this->getFacilities($locale),
            'activities' => $this->getActivities($locale),
            'natural_conditions' => $this->getNaturalConditions($locale),
            'media' => $this->getMediaCollections(),
            'embeds' => $this->getEmbeds(),
            'webcams' => $this->getWebcams(),
            'swimming_pool_features' => $this->getSwimmingPoolFeatures(),
            'meta' => $this->getMeta(),
        ];
    }

    private function getBasicInfo($translation, $translationEn): array
    {
        return [
            'id' => $this->id,
            'name' => $translation?->name ?? $translationEn?->name ?? $this->name,
            'slug' => $translation?->slug ?? $translationEn?->slug ?? null,
            'description' => $translation?->description ?? $translationEn?->description ?? null,
            // TASK-007: only the requested language; an English snippet or FAQ on a Slovenian page is worse than none.
            'meta_description' => trim((string) $translation?->meta_description) !== '' ? $translation->meta_description : null,
            'faq' => $this->faq($translation?->faq),
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'folder_name' => $this->folder_name,
            'mobile_upload' => $this->mobile_upload,
        ];
    }

    /**
     * TASK-007: FAQ as a list of {q, a} (jsonb reorders keys; the admin repeater stores items under uuid keys),
     * without empty items.
     *
     * @return list<array{q: string, a: string}>
     */
    private function faq(mixed $items): array
    {
        $list = [];
        foreach (is_array($items) ? $items : [] as $item) {
            $q = is_array($item) ? trim((string) ($item['q'] ?? '')) : '';
            $a = is_array($item) ? trim((string) ($item['a'] ?? '')) : '';
            if ($q !== '' && $a !== '') {
                $list[] = ['q' => $q, 'a' => $a];
            }
        }

        return $list;
    }

    private function getLocationInfo(string $locale): array
    {
        return [
            'continent' => $this->when($this->continent, function () use ($locale) {
                $trans = $this->continent->translations->firstWhere('locale', $locale)
                    ?? $this->continent->translations->firstWhere('locale', 'en');
                $transEn = $this->continent->translations->firstWhere('locale', 'en');
                return [
                    'code' => $this->continent->code,
                    'name' => $trans?->name ?? $this->continent->code,
                    'slug' => $trans?->slug ?? $transEn?->slug ?? null,
                ];
            }),
            'country' => $this->when($this->country, function () use ($locale) {
                $trans = $this->country->translations->firstWhere('locale', $locale)
                    ?? $this->country->translations->firstWhere('locale', 'en');
                $transEn = $this->country->translations->firstWhere('locale', 'en');
                return [
                    'code' => $this->country->code,
                    'name' => $trans?->name ?? $this->country->code,
                    'full_name' => $trans?->full_name ?? null,
                    'slug' => $trans?->slug ?? $transEn?->slug ?? null,
                ];
            }),
            'region' => $this->when($this->region, function () use ($locale) {
                $trans = $this->region->translations->firstWhere('locale', $locale)
                    ?? $this->region->translations->firstWhere('locale', 'en');
                $transEn = $this->region->translations->firstWhere('locale', 'en');
                return [
                    'id' => $this->region->id,
                    'name' => $trans?->name ?? $transEn?->name ?? null,
                    'slug' => $trans?->slug ?? $transEn?->slug ?? null,
                ];
            }),
            'city' => $this->when($this->city, function () use ($locale) {
                $trans = $this->city->translations->firstWhere('locale', $locale)
                    ?? $this->city->translations->firstWhere('locale', 'en');
                $transEn = $this->city->translations->firstWhere('locale', 'en');
                return [
                    'id' => $this->city->id,
                    'name' => $trans?->name ?? $transEn?->name ?? null,
                    'slug' => $trans?->slug ?? $transEn?->slug ?? null,
                ];
            }),
            'island' => $this->when($this->island, function () use ($locale) {
                $trans = $this->island->translations->firstWhere('locale', $locale)
                    ?? $this->island->translations->firstWhere('locale', 'en');
                $transEn = $this->island->translations->firstWhere('locale', 'en');
                return [
                    'id' => $this->island->id,
                    'name' => $trans?->name ?? $transEn?->name ?? null,
                    'slug' => $trans?->slug ?? $transEn?->slug ?? null,
                ];
            }),
            'river' => $this->when($this->river, function () use ($locale) {
                $trans = $this->river->translations->firstWhere('locale', $locale)
                    ?? $this->river->translations->firstWhere('locale', 'en');
                $transEn = $this->river->translations->firstWhere('locale', 'en');
                return [
                    'id' => $this->river->id,
                    'name' => $trans?->name ?? $transEn?->name ?? null,
                ];
            }),
            'lake' => $this->when($this->lake, function () use ($locale) {
                $trans = $this->lake->translations->firstWhere('locale', $locale)
                    ?? $this->lake->translations->firstWhere('locale', 'en');
                $transEn = $this->lake->translations->firstWhere('locale', 'en');
                return [
                    'id' => $this->lake->id,
                    'name' => $trans?->name ?? $transEn?->name ?? null,
                ];
            }),
        ];
    }

    private function getCharacteristics(string $locale): array
    {
        return [
            'beach_type' => $this->when($this->beachType, function () use ($locale) {
                $trans = $this->beachType->translations->firstWhere('locale', $locale)
                    ?? $this->beachType->translations->firstWhere('locale', 'en');
                return [
                    'id' => $this->beachType->id,
                    'code' => $this->beachType->code,
                    'name' => $trans?->name ?? $this->beachType->code,
                ];
            }),
            'waterbody' => $this->when($this->waterbody, function () use ($locale) {
                $trans = $this->waterbody->translations->firstWhere('locale', $locale)
                    ?? $this->waterbody->translations->firstWhere('locale', 'en');
                return [
                    'id' => $this->waterbody->id,
                    'code' => $this->waterbody->code,
                    'name' => $trans?->name ?? $this->waterbody->code,
                ];
            }),
            'water_body_type' => $this->when($this->waterBodyType, function () use ($locale) {
                $trans = $this->waterBodyType->translations->firstWhere('locale', $locale)
                    ?? $this->waterBodyType->translations->firstWhere('locale', 'en');
                return [
                    'id' => $this->waterBodyType->id,
                    'code' => $this->waterBodyType->code,
                    'name' => $trans?->name ?? $this->waterBodyType->code,
                ];
            }),
            'area_description' => $this->when($this->areaDescription, function () use ($locale) {
                $trans = $this->areaDescription->translations->firstWhere('locale', $locale)
                    ?? $this->areaDescription->translations->firstWhere('locale', 'en');
                return [
                    'id' => $this->areaDescription->id,
                    'code' => $this->areaDescription->code,
                    'name' => $trans?->name ?? $this->areaDescription->code,
                ];
            }),
            'location_types' => $this->beachLocationTypes->map(function ($type) use ($locale) {
                $trans = $type->translations->firstWhere('locale', $locale)
                    ?? $type->translations->firstWhere('locale', 'en');
                return [
                    'id' => $type->id,
                    'code' => $type->code,
                    'name' => $trans?->name ?? $type->code,
                ];
            }),
            'boat_only' => $this->boat_only,
        ];
    }

    private function getAccessibilityInfo(string $locale): array
    {
        return [
            'accessibility' => $this->when($this->accessibility, function () use ($locale) {
                $trans = $this->accessibility->translations->firstWhere('locale', $locale)
                    ?? $this->accessibility->translations->firstWhere('locale', 'en');
                return [
                    'id' => $this->accessibility->id,
                    'code' => $this->accessibility->code,
                    'name' => $trans?->name ?? $this->accessibility->code,
                ];
            }),
            'parking' => $this->parking,
            'road_to_parking_type' => $this->when($this->roadToParkingType, function () use ($locale) {
                $trans = $this->roadToParkingType->translations->firstWhere('locale', $locale)
                    ?? $this->roadToParkingType->translations->firstWhere('locale', 'en');
                return [
                    'id' => $this->roadToParkingType->id,
                    'code' => $this->roadToParkingType->code,
                    'name' => $trans?->name ?? $this->roadToParkingType->code,
                ];
            }),
            'parking_capacity' => $this->when($this->parkingCapacity, function () use ($locale) {
                $trans = $this->parkingCapacity->translations->firstWhere('locale', $locale)
                    ?? $this->parkingCapacity->translations->firstWhere('locale', 'en');
                return [
                    'id' => $this->parkingCapacity->id,
                    'code' => $this->parkingCapacity->code,
                    'name' => $trans?->name ?? $this->parkingCapacity->code,
                ];
            }),
            'parking_beach_distance' => $this->when($this->parkingBeachDistance, function () use ($locale) {
                $trans = $this->parkingBeachDistance->translations->firstWhere('locale', $locale)
                    ?? $this->parkingBeachDistance->translations->firstWhere('locale', 'en');
                return [
                    'id' => $this->parkingBeachDistance->id,
                    'code' => $this->parkingBeachDistance->code,
                    'name' => $trans?->name ?? $this->parkingBeachDistance->code,
                ];
            }),
            'parking_beach_height' => $this->when($this->parkingBeachHeight, function () use ($locale) {
                $trans = $this->parkingBeachHeight->translations->firstWhere('locale', $locale)
                    ?? $this->parkingBeachHeight->translations->firstWhere('locale', 'en');
                return [
                    'id' => $this->parkingBeachHeight->id,
                    'code' => $this->parkingBeachHeight->code,
                    'name' => $trans?->name ?? $this->parkingBeachHeight->code,
                ];
            }),
            'recommended_transport_types' => $this->recommendedTransportTypes->map(function ($type) use ($locale) {
                $trans = $type->translations->firstWhere('locale', $locale)
                    ?? $type->translations->firstWhere('locale', 'en');
                return [
                    'id' => $type->id,
                    'code' => $type->code,
                    'name' => $trans?->name ?? $type->code,
                ];
            }),
        ];
    }

    private function getDimensions(string $locale): array
    {
        return [
            'location_length' => $this->when($this->locationLength, function () use ($locale) {
                $trans = $this->locationLength->translations->firstWhere('locale', $locale)
                    ?? $this->locationLength->translations->firstWhere('locale', 'en');
                return [
                    'id' => $this->locationLength->id,
                    'code' => $this->locationLength->code,
                    'name' => $trans?->name ?? $this->locationLength->code,
                ];
            }),
            'bay_length' => $this->when($this->bayLength, function () use ($locale) {
                $trans = $this->bayLength->translations->firstWhere('locale', $locale)
                    ?? $this->bayLength->translations->firstWhere('locale', 'en');
                return [
                    'id' => $this->bayLength->id,
                    'code' => $this->bayLength->code,
                    'name' => $trans?->name ?? $this->bayLength->code,
                ];
            }),
            'beach_inclination' => $this->when($this->beachInclination, function () use ($locale) {
                $trans = $this->beachInclination->translations->firstWhere('locale', $locale)
                    ?? $this->beachInclination->translations->firstWhere('locale', 'en');
                return [
                    'id' => $this->beachInclination->id,
                    'code' => $this->beachInclination->code,
                    'name' => $trans?->name ?? $this->beachInclination->code,
                ];
            }),
            'swimming_depth_of_one_m' => $this->when($this->swimmingDepthOfOneM, function () use ($locale) {
                $trans = $this->swimmingDepthOfOneM->translations->firstWhere('locale', $locale)
                    ?? $this->swimmingDepthOfOneM->translations->firstWhere('locale', 'en');
                return [
                    'id' => $this->swimmingDepthOfOneM->id,
                    'code' => $this->swimmingDepthOfOneM->code,
                    'name' => $trans?->name ?? $this->swimmingDepthOfOneM->code,
                ];
            }),
            'occupancy_rate' => $this->when($this->occupancyRate, function () use ($locale) {
                $trans = $this->occupancyRate->translations->firstWhere('locale', $locale)
                    ?? $this->occupancyRate->translations->firstWhere('locale', 'en');
                return [
                    'id' => $this->occupancyRate->id,
                    'code' => $this->occupancyRate->code,
                    'name' => $trans?->name ?? $this->occupancyRate->code,
                ];
            }),
        ];
    }

    private function getFacilities(string $locale): array
    {
        return [
            'accommodation_around' => $this->accommodationArounds->map(function ($item) use ($locale) {
                $trans = $item->translations->firstWhere('locale', $locale)
                    ?? $item->translations->firstWhere('locale', 'en');
                return [
                    'id' => $item->id,
                    'code' => $item->code,
                    'name' => $trans?->name ?? $item->code,
                ];
            }),
            'culinary_offers' => $this->culinaryOffers->map(function ($item) use ($locale) {
                $trans = $item->translations->firstWhere('locale', $locale)
                    ?? $item->translations->firstWhere('locale', 'en');
                return [
                    'id' => $item->id,
                    'code' => $item->code,
                    'name' => $trans?->name ?? $item->code,
                ];
            }),
            'location_infrastructure' => $this->locationInfrastructures->map(function ($item) use ($locale) {
                $trans = $item->translations->firstWhere('locale', $locale)
                    ?? $item->translations->firstWhere('locale', 'en');
                return [
                    'id' => $item->id,
                    'code' => $item->code,
                    'name' => $trans?->name ?? $item->code,
                ];
            }),
        ];
    }

    private function getActivities(string $locale): array
    {
        return [
            'sporting_activities' => $this->sportingActivities->map(function ($item) use ($locale) {
                $trans = $item->translations->firstWhere('locale', $locale)
                    ?? $item->translations->firstWhere('locale', 'en');
                return [
                    'id' => $item->id,
                    'code' => $item->code,
                    'name' => $trans?->name ?? $item->code,
                ];
            }),
            'user_types' => $this->userTypes->map(function ($item) use ($locale) {
                $trans = $item->translations->firstWhere('locale', $locale)
                    ?? $item->translations->firstWhere('locale', 'en');
                return [
                    'id' => $item->id,
                    'code' => $item->code,
                    'name' => $trans?->name ?? $item->code,
                ];
            }),
        ];
    }

    private function getNaturalConditions(string $locale): array
    {
        return [
            'natural_location_conditions' => $this->naturalLocationConditions->map(function ($item) use ($locale) {
                $trans = $item->translations->firstWhere('locale', $locale)
                    ?? $item->translations->firstWhere('locale', 'en');
                return [
                    'id' => $item->id,
                    'code' => $item->code,
                    'name' => $trans?->name ?? $item->code,
                ];
            }),
            'underwater_characteristics' => $this->underwaterCharacteristics->map(function ($item) use ($locale) {
                $trans = $item->translations->firstWhere('locale', $locale)
                    ?? $item->translations->firstWhere('locale', 'en');
                return [
                    'id' => $item->id,
                    'code' => $item->code,
                    'name' => $trans?->name ?? $item->code,
                ];
            }),
        ];
    }

    private function getMediaCollections(): array
    {
        $mediaByCollection = $this->media->groupBy('mediaCollection.code');

        return [
            'gallery' => $this->formatMediaCollection(BeachGallery::pick($mediaByCollection)),
            'videos' => $this->formatMediaCollection($mediaByCollection->get('beach_videos', collect())),
            'spheres_360' => $this->formatMediaCollection($mediaByCollection->get('beach_360_sphere', collect()), 'beach_360_sphere'),
            'parking_images' => $this->formatMediaCollection($mediaByCollection->get('parking_images', collect())),
            'spot_images' => $this->formatMediaCollection($mediaByCollection->get('beach_spot_images', collect())),
            'infrastructure_images' => $this->formatMediaCollection($mediaByCollection->get('infrastructure_images', collect())),
            'info_images' => $this->formatMediaCollection($mediaByCollection->get('info_images', collect())),
        ];
    }

    private function formatMediaCollection($mediaItems, ?string $collectionCode = null): array
    {
        // Build the vtour URL only when we actually have sphere media.
        // The path must respect the mobile_upload flag: mobile-uploaded
        // beaches store their files under img/beaches_mobile/, while regular
        // uploads use img/beaches/. Using the wrong base folder caused the 360°
        // tour to 404 on mobile-uploaded beaches (e.g. Albania beaches).
        $vtourUrl = null;
        if ($collectionCode === 'beach_360_sphere' && $this->folder_name && $mediaItems->isNotEmpty()) {
            $baseFolder = $this->mobile_upload ? 'beaches_mobile' : 'beaches';
            $vtourUrl = "img/{$baseFolder}/{$this->folder_name}/vtour/tour.html";
        }

        return $mediaItems->map(function ($media) use ($vtourUrl, $collectionCode) {
            $baseData = [
                'id' => $media->id,
                'file_name' => $media->file_name,
                'url' => $media->url,
                'thumbnail_url' => $media->thumbnail_url,
                'tour_url' => $collectionCode === 'beach_360_sphere' ? $vtourUrl : null,
                'mime_type' => $media->mime_type,
                'width' => $media->width,
                'height' => $media->height,
                'duration' => $media->duration,
                'is_primary' => $media->is_primary,
                'order' => $media->order,
            ];

            return $baseData;
        })->values()->toArray();
    }

    /** TASK-006: verified Windy webcams, primary first. The site embeds `player_url`; `page_url` is the Windy link. */
    private function getWebcams(): array
    {
        return $this->shownWebcams->map(fn ($row) => [
            'windy_id' => $row->windy_id,
            'title' => $row->webcam->title,
            'is_primary' => $row->is_primary,
            'player_url' => $row->webcam->playerUrl(),
            'page_url' => $row->webcam->publicUrl(),
        ])->values()->all();
    }

    private function getEmbeds(): array
    {
        return [
            'video_url' => $this->video_url,
            'webcam' => $this->webcam,
            'windy_webcam_id' => $this->shownWebcams->first()?->windy_id,
            'map_url' => $this->path_to_beach_map_url,
            'weather' => $this->beach_weather,
            'water_temperature' => $this->water_temperature,
        ];
    }

    private function getSwimmingPoolFeatures(): array
    {
        return [
            'has_swimming_pool' => $this->has_swimming_pool,
            'indoor_pools_count' => $this->indoor_pools_count,
            'outdoor_pools_count' => $this->outdoor_pools_count,
            'indoor_area_m2' => $this->indoor_area_m2,
            'outdoor_area_m2' => $this->outdoor_area_m2,
            'features' => [
                'indoor_swimming_lanes' => $this->has_indoor_swimming_lanes,
                'outdoor_swimming_lanes' => $this->has_outdoor_swimming_lanes,
                'indoor_children_pool' => $this->has_indoor_children_pool,
                'outdoor_children_pool' => $this->has_outdoor_children_pool,
                'indoor_diving_board' => $this->has_indoor_diving_board,
                'outdoor_diving_board' => $this->has_outdoor_diving_board,
                'thermal_pool' => $this->has_thermal_pool,
                'indoor_climbing_wall' => $this->has_indoor_climbing_wall,
                'outdoor_climbing_wall' => $this->has_outdoor_climbing_wall,
                'indoor_lazy_river' => $this->has_indoor_lazy_river,
                'outdoor_lazy_river' => $this->has_outdoor_lazy_river,
                'indoor_wave_pool' => $this->has_indoor_wave_pool,
                'outdoor_wave_pool' => $this->has_outdoor_wave_pool,
                'indoor_whirlpool' => $this->has_indoor_whirlpool,
                'outdoor_whirlpool' => $this->has_outdoor_whirlpool,
                'indoor_surfing' => $this->has_indoor_surfing,
                'outdoor_surfing' => $this->has_outdoor_surfing,
                'sauna' => $this->has_sauna,
                'wellness' => $this->has_wellness,
            ],
        ];
    }

    private function getMeta(): array
    {
        return [
            'status' => $this->status?->name ?? null,
            'views' => $this->beach_views,
            'created_by' => $this->user ? [
                'id' => $this->user->id,
                'name' => $this->user->name,
            ] : null,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}

