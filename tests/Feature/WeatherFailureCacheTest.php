<?php

namespace Tests\Feature;

use App\Services\WeatherService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Audit 2026-10-09: Cache::remember() does not store null, so while OpenWeatherMap failed every page request
 * called it again and waited up to the (then 10 s) timeout, holding a PHP worker.
 */
class WeatherFailureCacheTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['services.openweather.api_key' => 'test-key', 'cache.default' => 'array']);
        Cache::flush();
    }

    public function test_a_failed_lookup_is_remembered_and_not_retried_on_every_request(): void
    {
        Http::fake(['api.openweathermap.org/*' => Http::response('down', 503)]);
        $service = new WeatherService();

        $this->assertNull($service->getCurrentWeather(45.1, 14.2));
        $this->assertNull($service->getCurrentWeather(45.1, 14.2));

        Http::assertSentCount(1);
    }

    public function test_a_successful_lookup_is_cached(): void
    {
        Http::fake(['api.openweathermap.org/*' => Http::response([
            'main' => ['temp' => 24.5, 'feels_like' => 25, 'humidity' => 60],
            'weather' => [['main' => 'Clear', 'description' => 'clear sky', 'icon' => '01d']],
            'wind' => ['speed' => 3.2],
            'name' => 'Cres',
        ])]);
        $service = new WeatherService();

        $first = $service->getCurrentWeather(45.2, 14.3);
        $this->assertNotNull($first);
        $this->assertEquals($first, $service->getCurrentWeather(45.2, 14.3));
        Http::assertSentCount(1);
    }
}
