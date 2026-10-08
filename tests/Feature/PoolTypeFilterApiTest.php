<?php

namespace Tests\Feature;

use App\Models\Beach;
use App\Models\Continent;
use App\Models\Country;
use App\Models\Status;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** QA #70: the map could not tell indoor from outdoor pools; `pool_type` filters on the admin's pool counts. */
class PoolTypeFilterApiTest extends TestCase
{
    use RefreshDatabase;

    private array $ids = [];

    protected function setUp(): void
    {
        parent::setUp();
        Status::insert([['id' => 1, 'name' => 'active'], ['id' => 2, 'name' => 'inactive']]);
        Continent::create(['code' => 'EU']);
        Country::create(['code' => 'SI', 'continent_code' => 'EU']);

        $beaches = [
            'indoor' => [2, 0],
            'outdoor' => [0, 1],
            'both' => [1, 3],
            'none' => [0, 0],
        ];
        $lon = 13.70;
        foreach ($beaches as $name => [$indoor, $outdoor]) {
            $this->ids[$name] = Beach::factory()->create([
                'country_code' => 'SI',
                'status_id' => 1,
                'latitude' => 45.55,
                'longitude' => $lon += 0.01,
                'indoor_pools_count' => $indoor,
                'outdoor_pools_count' => $outdoor,
            ])->id;
        }
    }

    /** Ids from the bounding-box endpoint (raw SQL filter path, used by the map). */
    private function bboxIds(array $query): array
    {
        $response = $this->getJson('/api/v1/spatial/beaches/bounding-box?'.http_build_query(
            ['min_lat' => 45.0, 'min_lon' => 13.0, 'max_lat' => 46.0, 'max_lon' => 14.5] + $query
        ))->assertOk();

        $rows = data_get($response->json(), 'data.beaches') ?? data_get($response->json(), 'data') ?? [];

        return collect($rows)->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->all();
    }

    private function expected(string ...$names): array
    {
        return collect($names)->map(fn ($n) => $this->ids[$n])->sort()->values()->all();
    }

    public function test_indoor_matches_beaches_with_indoor_pools(): void
    {
        $this->assertSame($this->expected('indoor', 'both'), $this->bboxIds(['pool_type' => 'indoor']));
    }

    public function test_outdoor_matches_beaches_with_outdoor_pools(): void
    {
        $this->assertSame($this->expected('outdoor', 'both'), $this->bboxIds(['pool_type' => 'outdoor']));
    }

    public function test_both_values_match_either(): void
    {
        $this->assertSame($this->expected('indoor', 'outdoor', 'both'), $this->bboxIds(['pool_type' => 'indoor,outdoor']));
    }

    public function test_unknown_value_is_ignored_not_injected(): void
    {
        $all = $this->expected('indoor', 'outdoor', 'both', 'none');
        $this->assertSame($all, $this->bboxIds([]));
        $this->assertSame($all, $this->bboxIds(['pool_type' => 'x_pools_count > 0 OR 1=1; --']));
    }

    public function test_beaches_list_endpoint_filters_too(): void
    {
        $response = $this->getJson('/api/v1/beaches?pool_type=outdoor&per_page=50')->assertOk();
        $ids = collect(data_get($response->json(), 'data'))->pluck('id')->sort()->values()->all();

        $this->assertSame($this->expected('outdoor', 'both'), $ids);
    }

    public function test_filters_endpoint_offers_translated_pool_type(): void
    {
        $en = $this->getJson('/api/v1/beaches/filters', ['Accept-Language' => 'en'])->assertOk()->json();
        $this->assertSame(['indoor', 'outdoor'], collect(data_get($en, 'data.pool_type.options'))->pluck('code')->all());
        $this->assertSame('Indoor pool', data_get($en, 'data.pool_type.options.0.name'));

        $sl = $this->getJson('/api/v1/beaches/filters', ['Accept-Language' => 'sl'])->assertOk()->json();
        $this->assertSame('Vrsta bazena', data_get($sl, 'data.pool_type.title'));
        $this->assertSame('Zunanji bazen', data_get($sl, 'data.pool_type.options.1.name'));
    }
}
