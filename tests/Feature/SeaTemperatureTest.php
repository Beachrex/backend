<?php

namespace Tests\Feature;

use App\Services\WeatherService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Regression: sea_temperature was the air temperature from OpenWeatherMap. Owner decision 2026-10-07: real SST from Open-Meteo Marine. */
class SeaTemperatureTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        config(['services.open_meteo.api_key' => null]);
    }

    private function marine(mixed $sst): array
    {
        return ['current' => ['time' => '2026-10-07T12:00', 'interval' => 900, 'sea_surface_temperature' => $sst]];
    }

    public function test_returns_sea_surface_temperature_from_the_free_endpoint(): void
    {
        Http::fake(['marine-api.open-meteo.com/*' => Http::response($this->marine(21.37))]);

        $this->assertSame(21.4, app(WeatherService::class)->getSeaTemperature(43.2566, 16.6347));

        Http::assertSent(fn (Request $r) => str_starts_with($r->url(), 'https://marine-api.open-meteo.com/v1/marine')
            && $r['current'] === 'sea_surface_temperature'
            && ! isset($r['apikey']));
    }

    public function test_uses_the_commercial_endpoint_with_an_api_key(): void
    {
        config(['services.open_meteo.api_key' => 'test-key']);
        Http::fake(['customer-marine-api.open-meteo.com/*' => Http::response($this->marine(19.0))]);

        $this->assertSame(19.0, app(WeatherService::class)->getSeaTemperature(43.2566, 16.6347));

        Http::assertSent(fn (Request $r) => str_starts_with($r->url(), 'https://customer-marine-api.open-meteo.com/v1/marine')
            && $r['apikey'] === 'test-key');
    }

    public function test_no_marine_data_is_null_and_cached(): void
    {
        Http::fake(['marine-api.open-meteo.com/*' => Http::response($this->marine(null))]);
        $service = app(WeatherService::class);

        $this->assertNull($service->getSeaTemperature(46.36, 14.09)); // Lake Bled: no marine grid
        $this->assertNull($service->getSeaTemperature(46.36, 14.09));

        Http::assertSentCount(1);
    }

    public function test_api_failure_is_null_and_not_cached(): void
    {
        Http::fakeSequence('marine-api.open-meteo.com/*')
            ->push('error', 500)
            ->push($this->marine(22.0));
        $service = app(WeatherService::class);

        $this->assertNull($service->getSeaTemperature(43.2566, 16.6347));
        $this->assertSame(22.0, $service->getSeaTemperature(43.2566, 16.6347));
    }

    public function test_value_is_cached(): void
    {
        Http::fake(['marine-api.open-meteo.com/*' => Http::response($this->marine(20.04))]);
        $service = app(WeatherService::class);

        $this->assertSame(20.0, $service->getSeaTemperature(43.2566, 16.6347));
        $this->assertSame(20.0, $service->getSeaTemperature(43.2566, 16.6347));

        Http::assertSentCount(1);
    }
}
