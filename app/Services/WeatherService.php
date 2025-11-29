<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WeatherService
{
    private const CACHE_TTL = 3600; // 1 hour cache
    private const API_TIMEOUT = 10; // 10 seconds timeout
    
    private string $apiKey;
    private string $baseUrl = 'https://api.openweathermap.org/data/2.5';

    public function __construct()
    {
        $this->apiKey = config('services.openweather.api_key', '');
    }

    /**
     * Get current weather data for a location
     *
     * @param float $latitude
     * @param float $longitude
     * @return array|null Weather data or null if API call fails
     */
    public function getCurrentWeather(float $latitude, float $longitude): ?array
    {
        if (empty($this->apiKey)) {
            Log::warning('OpenWeatherMap API key not configured');
            return null;
        }

        $cacheKey = $this->getCacheKey('weather', $latitude, $longitude);

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($latitude, $longitude) {
            try {
                $response = Http::timeout(self::API_TIMEOUT)
                    ->get("{$this->baseUrl}/weather", [
                        'lat' => $latitude,
                        'lon' => $longitude,
                        'appid' => $this->apiKey,
                        'units' => 'metric', // Celsius
                    ]);

                if (!$response->successful()) {
                    Log::error('OpenWeatherMap API error', [
                        'status' => $response->status(),
                        'body' => $response->body(),
                    ]);
                    return null;
                }

                $data = $response->json();

                return $this->formatWeatherData($data);
            } catch (\Exception $e) {
                Log::error('Failed to fetch weather data', [
                    'error' => $e->getMessage(),
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                ]);
                return null;
            }
        });
    }

    /**
     * Get sea surface temperature for a location
     * Note: OpenWeatherMap doesn't provide dedicated sea temperature in the free tier.
     * This returns the general temperature which is close to sea temperature for coastal areas.
     * For more accurate sea temperature, consider using a specialized marine API.
     *
     * @param float $latitude
     * @param float $longitude
     * @return float|null Temperature in Celsius or null if unavailable
     */
    public function getSeaTemperature(float $latitude, float $longitude): ?float
    {
        $weather = $this->getCurrentWeather($latitude, $longitude);
        
        return $weather['temperature'] ?? null;
    }

    /**
     * Format raw OpenWeatherMap API response into a clean structure
     *
     * @param array $data Raw API response
     * @return array Formatted weather data
     */
    private function formatWeatherData(array $data): array
    {
        return [
            'temperature' => round($data['main']['temp'] ?? 0, 1),
            'feels_like' => round($data['main']['feels_like'] ?? 0, 1),
            'temp_min' => round($data['main']['temp_min'] ?? 0, 1),
            'temp_max' => round($data['main']['temp_max'] ?? 0, 1),
            'pressure' => $data['main']['pressure'] ?? null,
            'humidity' => $data['main']['humidity'] ?? null,
            'condition' => $data['weather'][0]['main'] ?? 'Unknown',
            'description' => $data['weather'][0]['description'] ?? 'No description',
            'icon' => $data['weather'][0]['icon'] ?? null,
            'icon_url' => isset($data['weather'][0]['icon']) 
                ? "https://openweathermap.org/img/wn/{$data['weather'][0]['icon']}@2x.png"
                : null,
            'wind_speed' => $data['wind']['speed'] ?? null,
            'wind_deg' => $data['wind']['deg'] ?? null,
            'clouds' => $data['clouds']['all'] ?? null,
            'visibility' => $data['visibility'] ?? null,
            'sunrise' => isset($data['sys']['sunrise']) ? date('H:i', $data['sys']['sunrise']) : null,
            'sunset' => isset($data['sys']['sunset']) ? date('H:i', $data['sys']['sunset']) : null,
            'location_name' => $data['name'] ?? null,
        ];
    }

    /**
     * Generate cache key for weather data
     *
     * @param string $type
     * @param float $latitude
     * @param float $longitude
     * @return string
     */
    private function getCacheKey(string $type, float $latitude, float $longitude): string
    {
        // Round coordinates to 2 decimal places for cache efficiency
        // This means locations within ~1km will share the same cache
        $lat = round($latitude, 2);
        $lon = round($longitude, 2);
        
        return "weather:{$type}:{$lat}:{$lon}";
    }

    /**
     * Clear weather cache for a specific location
     *
     * @param float $latitude
     * @param float $longitude
     * @return void
     */
    public function clearCache(float $latitude, float $longitude): void
    {
        $cacheKey = $this->getCacheKey('weather', $latitude, $longitude);
        Cache::forget($cacheKey);
    }

    /**
     * Check if API key is configured
     *
     * @return bool
     */
    public function isConfigured(): bool
    {
        return !empty($this->apiKey);
    }
}

