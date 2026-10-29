<?php

namespace Tests\Feature;

use App\Models\Beach;
use App\Models\Continent;
use App\Models\Country;
use App\Models\Waterbody;
use Database\Seeders\StatusSeeder;
use Database\Seeders\WaterbodySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Owner 2026-10-29: the water body filter shows indoor and outdoor pools under "Pool". The web sends
 * `waterbody=pool` (every pool place) and/or `pool_type=indoor|outdoor` (the home page tiles send only
 * `pool_type`). Both parameters combine with AND, like every other pair of filters.
 */
class PoolWaterbodyFilterApiTest extends TestCase
{
    use RefreshDatabase;

    private array $ids = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([StatusSeeder::class, WaterbodySeeder::class]);
        Continent::create(['code' => 'EU']);
        Country::create(['code' => 'SI', 'continent_code' => 'EU']);

        $sea = Waterbody::where('code', 'sea')->value('id');
        $pool = Waterbody::where('code', 'pool')->value('id');

        // name => [waterbody id, indoor pools, outdoor pools]
        $places = [
            'indoor_pool' => [$pool, 2, null],
            'outdoor_pool' => [$pool, 0, 3],
            'both_pool' => [$pool, 1, 1],
            'unclassified_pool' => [$pool, null, null],
            'sea_hotel_indoor' => [$sea, 1, 0],
            'sea' => [$sea, null, null],
        ];
        $lon = 13.70;
        foreach ($places as $name => [$waterbodyId, $indoor, $outdoor]) {
            $this->ids[$name] = Beach::factory()->create([
                'country_code' => 'SI',
                'status_id' => 1,
                'waterbody_id' => $waterbodyId,
                'latitude' => 45.55,
                'longitude' => $lon += 0.01,
                'indoor_pools_count' => $indoor,
                'outdoor_pools_count' => $outdoor,
            ])->id;
        }
    }

    /** Ids from the bounding-box endpoint (the map). */
    private function bboxIds(array $query): array
    {
        $response = $this->getJson('/api/v1/spatial/beaches/bounding-box?'.http_build_query(
            ['min_lat' => 45.0, 'min_lon' => 13.0, 'max_lat' => 46.0, 'max_lon' => 14.5] + $query
        ))->assertOk();

        $rows = data_get($response->json(), 'data.beaches') ?? data_get($response->json(), 'data') ?? [];

        return collect($rows)->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->all();
    }

    /** Ids from the list endpoint (listing pages). */
    private function listIds(array $query): array
    {
        $response = $this->getJson('/api/v1/beaches?'.http_build_query($query + ['per_page' => 50]))->assertOk();

        return collect(data_get($response->json(), 'data'))->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->all();
    }

    private function expected(string ...$names): array
    {
        return collect($names)->map(fn ($n) => $this->ids[$n])->sort()->values()->all();
    }

    public function test_waterbody_pool_returns_indoor_and_outdoor_pool_places(): void
    {
        $pools = $this->expected('indoor_pool', 'outdoor_pool', 'both_pool', 'unclassified_pool');
        $this->assertSame($pools, $this->bboxIds(['waterbody' => 'pool']));
        $this->assertSame($pools, $this->listIds(['waterbody' => 'pool']));
    }

    public function test_home_page_tile_params_return_results(): void
    {
        // Exactly what the home page tiles send: ?pool_type=indoor / ?pool_type=outdoor
        $this->assertSame($this->expected('indoor_pool', 'both_pool', 'sea_hotel_indoor'), $this->bboxIds(['pool_type' => 'indoor']));
        $this->assertSame($this->expected('outdoor_pool', 'both_pool'), $this->bboxIds(['pool_type' => 'outdoor']));
    }

    public function test_pool_with_indoor_returns_only_indoor_pool_places(): void
    {
        $expected = $this->expected('indoor_pool', 'both_pool');
        $this->assertSame($expected, $this->bboxIds(['waterbody' => 'pool', 'pool_type' => 'indoor']));
        $this->assertSame($expected, $this->listIds(['waterbody' => 'pool', 'pool_type' => 'indoor']));
    }

    public function test_pool_with_outdoor_returns_only_outdoor_pool_places(): void
    {
        $expected = $this->expected('outdoor_pool', 'both_pool');
        $this->assertSame($expected, $this->bboxIds(['waterbody' => 'pool', 'pool_type' => 'outdoor']));
        $this->assertSame($expected, $this->listIds(['waterbody' => 'pool', 'pool_type' => 'outdoor']));
    }

    public function test_pool_with_both_types_leaves_out_unclassified_pools(): void
    {
        $this->assertSame(
            $this->expected('indoor_pool', 'outdoor_pool', 'both_pool'),
            $this->bboxIds(['waterbody' => 'pool', 'pool_type' => 'indoor,outdoor'])
        );
    }

    public function test_unknown_pool_type_is_ignored(): void
    {
        $pools = $this->expected('indoor_pool', 'outdoor_pool', 'both_pool', 'unclassified_pool');
        $this->assertSame($pools, $this->bboxIds(['waterbody' => 'pool', 'pool_type' => 'heated']));
    }

    public function test_filters_endpoint_keeps_pool_and_pool_types_while_a_pool_type_is_selected(): void
    {
        // The web nests the pool types under "Pool" and asks for the options with the current selection
        $json = $this->getJson('/api/v1/beaches/filters?pool_type=indoor', ['Accept-Language' => 'sl'])->assertOk()->json();

        $this->assertContains('pool', collect(data_get($json, 'data.waterbody.options'))->pluck('code')->all());
        $this->assertSame(
            ['Notranji bazen', 'Zunanji bazen'],
            collect(data_get($json, 'data.pool_type.options'))->pluck('name')->all()
        );
    }
}
