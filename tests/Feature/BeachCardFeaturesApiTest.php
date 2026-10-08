<?php

namespace Tests\Feature;

use App\Models\Beach;
use App\Models\BeachType;
use App\Models\Continent;
use App\Models\Country;
use App\Models\LocationInfrastructure;
use App\Models\ParkingCapacity;
use App\Models\Status;
use App\Models\UserType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * QA #45: list and map cards showed the same stones / family / parking icons on every beach,
 * because the search, map and list APIs did not return those fields. They now return `features`.
 */
class BeachCardFeaturesApiTest extends TestCase
{
    use RefreshDatabase;

    private Beach $sandFamily;
    private Beach $rockParking;
    private Beach $bare;

    protected function setUp(): void
    {
        parent::setUp();
        Status::insert([['id' => 1, 'name' => 'active'], ['id' => 2, 'name' => 'inactive']]);
        Continent::create(['code' => 'EU']);
        Country::create(['code' => 'HR', 'continent_code' => 'EU']);
        $this->seed([
            \Database\Seeders\BeachTypeSeeder::class,
            \Database\Seeders\UserTypeSeeder::class,
            \Database\Seeders\LocationInfrastructureSeeder::class,
            \Database\Seeders\ParkingCapacitySeeder::class,
        ]);

        $base = ['country_code' => 'HR', 'status_id' => 1, 'latitude' => 43.30];

        $this->sandFamily = Beach::factory()->create($base + [
            'longitude' => 16.60,
            'beach_type_id' => BeachType::where('code', 'sand')->value('id'),
            'parking_capacity_id' => ParkingCapacity::where('code', 'private_parking_only')->value('id'),
            'beach_views' => 30,
        ]);
        $this->sandFamily->userTypes()->attach(UserType::where('code', 'family')->value('id'));

        $this->rockParking = Beach::factory()->create($base + [
            'longitude' => 16.61,
            'beach_type_id' => BeachType::where('code', 'rock_beach')->value('id'),
            'beach_views' => 20,
        ]);
        $this->rockParking->locationInfrastructures()->attach(LocationInfrastructure::where('code', 'parking')->value('id'));
        $this->rockParking->userTypes()->attach(UserType::where('code', 'youth')->value('id'));

        $this->bare = Beach::factory()->create($base + [
            'longitude' => 16.62,
            'beach_type_id' => null,
            'parking_capacity_id' => ParkingCapacity::where('code', '50')->value('id'),
            'beach_views' => 10,
        ]);
    }

    private function assertFeatures(array $byId): void
    {
        $this->assertSame('sand', data_get($byId[$this->sandFamily->id], 'features.beach_type.code'));
        $this->assertTrue(data_get($byId[$this->sandFamily->id], 'features.family'));
        $this->assertFalse(data_get($byId[$this->sandFamily->id], 'features.parking'), 'private parking only is not parking');

        $this->assertSame('rock_beach', data_get($byId[$this->rockParking->id], 'features.beach_type.code'));
        $this->assertFalse(data_get($byId[$this->rockParking->id], 'features.family'));
        $this->assertTrue(data_get($byId[$this->rockParking->id], 'features.parking'));

        $this->assertNull(data_get($byId[$this->bare->id], 'features.beach_type'));
        $this->assertFalse(data_get($byId[$this->bare->id], 'features.family'));
        $this->assertTrue(data_get($byId[$this->bare->id], 'features.parking'), 'capacity 50 is parking');
    }

    private function byId(array $rows): array
    {
        return collect($rows)->keyBy('id')->all();
    }

    public function test_bounding_box_returns_features(): void
    {
        $json = $this->getJson('/api/v1/spatial/beaches/bounding-box?min_lat=43&min_lon=16&max_lat=44&max_lon=17')->assertOk()->json();
        $rows = data_get($json, 'data.beaches') ?? data_get($json, 'data');

        $this->assertFeatures($this->byId($rows));
    }

    public function test_beaches_list_returns_features(): void
    {
        $json = $this->getJson('/api/v1/beaches?per_page=50')->assertOk()->json();

        $this->assertFeatures($this->byId(data_get($json, 'data')));
    }

    public function test_most_visited_returns_features(): void
    {
        $json = $this->getJson('/api/v1/beaches/most-visited?limit=10')->assertOk()->json();
        $rows = data_get($json, 'data.beaches') ?? data_get($json, 'data');

        $this->assertFeatures($this->byId($rows));
    }

    public function test_beach_type_name_is_translated(): void
    {
        $sl = $this->getJson('/api/v1/spatial/beaches/bounding-box?min_lat=43&min_lon=16&max_lat=44&max_lon=17', ['Accept-Language' => 'sl'])->assertOk()->json();
        $rows = $this->byId(data_get($sl, 'data.beaches') ?? data_get($sl, 'data'));
        $expected = \App\Models\BeachTypeTranslation::where('locale', 'sl')
            ->where('beach_type_id', $this->sandFamily->beach_type_id)->value('name');

        $this->assertNotNull($expected);
        $this->assertSame($expected, data_get($rows[$this->sandFamily->id], 'features.beach_type.name'));
    }
}
