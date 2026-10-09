<?php

namespace Tests\Feature;

use App\Models\Beach;
use App\Services\PostGISService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Regression: distances were measured in EPSG:3857, ~37% too long at Croatian latitudes. */
class PostGISDistanceTest extends TestCase
{
    use RefreshDatabase;

    // Zlatni rat (Bol, Brač) and Split riva. Geodesic distance ≈ 31.9 km; EPSG:3857 gave ≈ 43.9 km.
    private const BEACH = [43.2566, 16.6347];
    private const SPLIT = [43.5081, 16.4402];

    private function haversineKm(array $a, array $b): float
    {
        [$lat1, $lon1, $lat2, $lon2] = array_map('deg2rad', [$a[0], $a[1], $b[0], $b[1]]);
        $h = sin(($lat2 - $lat1) / 2) ** 2 + cos($lat1) * cos($lat2) * sin(($lon2 - $lon1) / 2) ** 2;

        return 2 * 6371.0088 * asin(sqrt($h));
    }

    public function test_calculate_distance_is_geodesic(): void
    {
        $expected = $this->haversineKm(self::BEACH, self::SPLIT);
        $actual = app(PostGISService::class)->calculateDistance(...[...self::BEACH, ...self::SPLIT]);

        $this->assertEqualsWithDelta($expected, $actual, $expected * 0.005, "got {$actual} km, expected ≈ {$expected} km");
    }

    public function test_radius_search_uses_real_distance(): void
    {
        $this->seed(\Database\Seeders\StatusSeeder::class);
        $beach = Beach::factory()->create(['latitude' => self::BEACH[0], 'longitude' => self::BEACH[1], 'status_id' => 1]);
        $this->assertNotNull(DB::table('beach')->where('id', $beach->id)->value('location'));
        $service = app(PostGISService::class);

        // ≈31.9 km away: inside a 35 km radius (EPSG:3857 said ≈43.9 km and left it out) …
        $inside = collect($service->getBeachesWithinRadius(self::SPLIT[0], self::SPLIT[1], 35));
        $this->assertSame([$beach->id], $inside->pluck('id')->all());
        $distance = $inside->first()['distance_km'] ?? $inside->first()->distance_km;
        $this->assertEqualsWithDelta($this->haversineKm(self::BEACH, self::SPLIT), (float) $distance, 0.3);

        // … and outside a 30 km radius.
        $this->assertCount(0, collect($service->getBeachesWithinRadius(self::SPLIT[0], self::SPLIT[1], 30)));
    }

    public function test_geography_index_exists(): void
    {
        $this->assertTrue((bool) DB::selectOne(
            "SELECT EXISTS (SELECT 1 FROM pg_indexes WHERE tablename = 'beach' AND indexname = 'beach_location_geography_idx') AS e"
        )->e);
    }
}
