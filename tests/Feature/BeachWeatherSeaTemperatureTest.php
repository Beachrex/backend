<?php

namespace Tests\Feature;

use App\Models\Beach;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Regression: /beach-details/{id}/weather returned the air temperature as sea_temperature. */
class BeachWeatherSeaTemperatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_sea_temperature_is_the_marine_value_not_the_air_temperature(): void
    {
        $this->seed(\Database\Seeders\StatusSeeder::class);
        Cache::flush();
        config(['services.openweather.api_key' => 'test', 'services.open_meteo.api_key' => null]);
        Http::fake([
            'api.openweathermap.org/*' => Http::response([
                'main' => ['temp' => 28.0, 'feels_like' => 29.0, 'temp_min' => 27.0, 'temp_max' => 29.0, 'humidity' => 50, 'pressure' => 1013],
                'weather' => [['main' => 'Clear', 'description' => 'clear sky', 'icon' => '01d']],
                'wind' => ['speed' => 3.0, 'deg' => 180],
                'clouds' => ['all' => 0],
                'sys' => ['sunrise' => 1, 'sunset' => 2],
                'visibility' => 10000,
            ]),
            'marine-api.open-meteo.com/*' => Http::response(['current' => ['sea_surface_temperature' => 21.4]]),
        ]);
        $beach = Beach::factory()->create(['latitude' => 43.2566, 'longitude' => 16.6347, 'status_id' => 1]);

        $response = $this->getJson("/api/v1/beach-details/{$beach->id}/weather", ['Accept-Language' => 'en'])->assertOk();

        $this->assertEquals(28.0, $response->json('data.weather.temperature'));
        $this->assertEquals(21.4, $response->json('data.sea_temperature'));
    }
}
