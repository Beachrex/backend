<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WeatherService
{
    private const CACHE_TTL = 3600; // 1 hour cache
    // Short: the call runs inside a page request and holds a PHP worker while it waits (audit 2026-10-09)
    private const API_TIMEOUT = 3;
    /** A failed lookup is remembered this long, so an outage does not make every request wait again. */
    private const FAILURE_TTL = 300;
    private const SEA_CACHE_TTL = 10800; // 3 hours
    private const MARINE_TIMEOUT = 3;
    private const NO_VALUE = 'none';
    
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

        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached === self::NO_VALUE ? null : $cached;
        }

        $weather = (function () use ($latitude, $longitude) {
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
        })();

        // Cache::remember() does not store null, so a failing API was called again on every request.
        Cache::put($cacheKey, $weather ?? self::NO_VALUE, $weather === null ? self::FAILURE_TTL : self::CACHE_TTL);

        return $weather;
    }

    /**
     * Sea surface temperature (°C) from the Open-Meteo Marine API, or null when unavailable
     * (no marine data for the point, e.g. inland lakes, or the API failed).
     *
     * Without OPEN_METEO_API_KEY the free endpoint is used, which Open-Meteo allows for
     * non-commercial use only. With a key, the commercial customer endpoint is used.
     */
    public function getSeaTemperature(float $latitude, float $longitude): ?float
    {
        $cacheKey = $this->getCacheKey('sea', $latitude, $longitude);
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached === self::NO_VALUE ? null : (float) $cached;
        }

        $apiKey = (string) config('services.open_meteo.api_key', '');
        $baseUrl = $apiKey !== '' ? 'https://customer-marine-api.open-meteo.com' : 'https://marine-api.open-meteo.com';
        $query = ['latitude' => $latitude, 'longitude' => $longitude, 'current' => 'sea_surface_temperature'];
        if ($apiKey !== '') {
            $query['apikey'] = $apiKey;
        }

        try {
            $response = Http::timeout(self::MARINE_TIMEOUT)->get("{$baseUrl}/v1/marine", $query);
            if (! $response->successful()) {
                Log::warning('Open-Meteo Marine API error', ['status' => $response->status()]);

                return null; // not cached: retry on the next request
            }
            $value = $response->json('current.sea_surface_temperature');
        } catch (\Exception $e) {
            Log::warning('Failed to fetch sea temperature', ['error' => $e->getMessage()]);

            return null;
        }

        $temperature = is_numeric($value) ? round((float) $value, 1) : null;
        // Sea temperature changes slowly; "no marine data here" is cached too.
        Cache::put($cacheKey, $temperature ?? self::NO_VALUE, self::SEA_CACHE_TTL);

        return $temperature;
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

