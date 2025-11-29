<?php

namespace App\Traits;

use App\Services\WeatherService;
use Illuminate\Http\JsonResponse;

/**
 * Trait ProvidesWeather
 * 
 * Provides weather data functionality for entity detail controllers.
 * Can be used by BeachDetailsController, RegionDetailsController, CityDetailsController, etc.
 */
trait ProvidesWeather
{
    /**
     * Get weather data for given coordinates
     *
     * @param float $latitude
     * @param float $longitude
     * @return array|null Weather data or null if unavailable
     */
    protected function getWeatherForCoordinates(float $latitude, float $longitude): ?array
    {
        $weatherService = app(WeatherService::class);
        return $weatherService->getCurrentWeather($latitude, $longitude);
    }

    /**
     * Build standardized weather response for any entity type
     *
     * @param object $entity The entity (Beach, Region, City, Island, etc.)
     * @param string $entityType Entity type name (beach, region, city, island)
     * @param string|null $slug Entity slug in current locale
     * @return JsonResponse
     */
    protected function buildWeatherResponse(
        object $entity,
        string $entityType,
        ?string $slug
    ): JsonResponse {
        // Check if entity has coordinates
        if (!$entity->latitude || !$entity->longitude) {
            return response()->json([
                'success' => false,
                'message' => ucfirst($entityType) . ' has no coordinates for weather data',
                'data' => [
                    $entityType . '_id' => $entity->id,
                    $entityType . '_slug' => $slug,
                    'weather' => null,
                    'sea_temperature' => null,
                ]
            ], 400);
        }

        // Get weather data from WeatherService (cached for 1 hour)
        $weatherData = $this->getWeatherForCoordinates(
            (float) $entity->latitude,
            (float) $entity->longitude
        );

        // If weather service fails, return 503
        if ($weatherData === null) {
            return response()->json([
                'success' => false,
                'message' => 'Weather data temporarily unavailable',
                'data' => [
                    $entityType . '_id' => $entity->id,
                    $entityType . '_slug' => $slug,
                    'coordinates' => [
                        'latitude' => $entity->latitude,
                        'longitude' => $entity->longitude,
                    ],
                    'weather' => null,
                    'sea_temperature' => null,
                ]
            ], 503);
        }

        // Success response with weather data
        return response()->json([
            'success' => true,
            'data' => [
                $entityType . '_id' => $entity->id,
                $entityType . '_slug' => $slug,
                'coordinates' => [
                    'latitude' => $entity->latitude,
                    'longitude' => $entity->longitude,
                ],
                'weather' => $weatherData,
                'sea_temperature' => $weatherData['temperature'] ?? null,
                'cached_at' => now()->toIso8601String(),
            ]
        ], 200);
    }
}

