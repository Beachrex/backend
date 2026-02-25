<?php

namespace Tests\Feature;

use App\Models\Beach;
use App\Models\City;
use App\Models\CityTranslation;
use App\Models\Continent;
use App\Models\ContinentTranslation;
use App\Models\Country;
use App\Models\CountryTranslation;
use App\Models\Region;
use App\Models\RegionTranslation;
use App\Models\Status;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NearbyCitiesApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed statuses
        Status::insert([
            ['id' => 1, 'name' => 'active'],
            ['id' => 2, 'name' => 'inactive'],
        ]);

        // Seed continents
        Continent::insert([
            ['code' => 'EU'],
        ]);

        ContinentTranslation::insert([
            ['continent_code' => 'EU', 'locale' => 'en', 'name' => 'Europe', 'slug' => 'europe'],
        ]);

        // Seed countries
        Country::insert([
            ['code' => 'HR', 'continent_code' => 'EU'],
        ]);

        CountryTranslation::insert([
            ['country_code' => 'HR', 'locale' => 'en', 'name' => 'Croatia', 'slug' => 'croatia'],
        ]);

        // Seed region
        Region::insert([
            ['id' => 1, 'code' => 'DAL', 'country_code' => 'HR', 'status_id' => 1, 'created_at' => now(), 'updated_at' => now()],
        ]);

        RegionTranslation::insert([
            ['region_id' => 1, 'locale' => 'en', 'name' => 'Dalmatia', 'slug' => 'dalmatia'],
        ]);

        // Seed cities with coordinates
        City::insert([
            ['id' => 1, 'code' => 'SPL', 'country_code' => 'HR', 'region_id' => 1, 'latitude' => 43.5081, 'longitude' => 16.4402, 'status_id' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'code' => 'TRG', 'country_code' => 'HR', 'region_id' => 1, 'latitude' => 43.5167, 'longitude' => 16.2500, 'status_id' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 3, 'code' => 'DBK', 'country_code' => 'HR', 'region_id' => 1, 'latitude' => 42.6507, 'longitude' => 18.0944, 'status_id' => 1, 'created_at' => now(), 'updated_at' => now()],
            // Inactive city - should not appear
            ['id' => 4, 'code' => 'INA', 'country_code' => 'HR', 'region_id' => 1, 'latitude' => 43.5100, 'longitude' => 16.4500, 'status_id' => 2, 'created_at' => now(), 'updated_at' => now()],
            // Active city without beaches - should not appear
            ['id' => 5, 'code' => 'NOB', 'country_code' => 'HR', 'region_id' => 1, 'latitude' => 43.5200, 'longitude' => 16.4600, 'status_id' => 1, 'created_at' => now(), 'updated_at' => now()],
        ]);

        CityTranslation::insert([
            ['city_id' => 1, 'locale' => 'en', 'name' => 'Split', 'slug' => 'split'],
            ['city_id' => 2, 'locale' => 'en', 'name' => 'Trogir', 'slug' => 'trogir'],
            ['city_id' => 3, 'locale' => 'en', 'name' => 'Dubrovnik', 'slug' => 'dubrovnik'],
            ['city_id' => 4, 'locale' => 'en', 'name' => 'Inactive City', 'slug' => 'inactive-city'],
            ['city_id' => 5, 'locale' => 'en', 'name' => 'No Beaches City', 'slug' => 'no-beaches-city'],
        ]);

        // Seed beaches for cities 1, 2, 3 (not 4 or 5)
        Beach::insert([
            ['latitude' => '43.5081', 'longitude' => '16.4402', 'country_code' => 'HR', 'city_id' => 1, 'status_id' => 1, 'folder_name' => 'beach-split-1', 'mobile_upload' => false, 'created_at' => now(), 'updated_at' => now()],
            ['latitude' => '43.5100', 'longitude' => '16.4500', 'country_code' => 'HR', 'city_id' => 1, 'status_id' => 1, 'folder_name' => 'beach-split-2', 'mobile_upload' => false, 'created_at' => now(), 'updated_at' => now()],
            ['latitude' => '43.5167', 'longitude' => '16.2500', 'country_code' => 'HR', 'city_id' => 2, 'status_id' => 1, 'folder_name' => 'beach-trogir', 'mobile_upload' => false, 'created_at' => now(), 'updated_at' => now()],
            ['latitude' => '42.6507', 'longitude' => '18.0944', 'country_code' => 'HR', 'city_id' => 3, 'status_id' => 1, 'folder_name' => 'beach-dubrovnik', 'mobile_upload' => false, 'created_at' => now(), 'updated_at' => now()],
            // Inactive beach for city 4
            ['latitude' => '43.5100', 'longitude' => '16.4500', 'country_code' => 'HR', 'city_id' => 4, 'status_id' => 2, 'folder_name' => 'beach-inactive', 'mobile_upload' => false, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function test_nearby_cities_returns_valid_response()
    {
        // Search near Split (43.5081, 16.4402) with 50km radius
        $response = $this->getJson('/api/v1/spatial/cities/nearby?latitude=43.5&longitude=16.4&radius=50', [
            'Accept-Language' => 'en',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'slug',
                        'country_code',
                        'country_name',
                        'country_slug',
                        'continent_slug',
                        'region_slug',
                        'island_slug',
                        'latitude',
                        'longitude',
                        'beaches_count',
                        'main_image',
                        'distance_km',
                    ],
                ],
                'meta' => [
                    'reference_point' => ['latitude', 'longitude'],
                    'radius_km',
                    'limit',
                    'count',
                ],
            ]);

        // Should include Split and Trogir (within 50km), but not Dubrovnik (~230km away)
        $data = $response->json('data');
        $cityNames = collect($data)->pluck('name')->toArray();
        $this->assertContains('Split', $cityNames);
        $this->assertContains('Trogir', $cityNames);
        $this->assertNotContains('Dubrovnik', $cityNames);

        // Should NOT include inactive city or city without beaches
        $this->assertNotContains('Inactive City', $cityNames);
        $this->assertNotContains('No Beaches City', $cityNames);

        // Verify beaches_count for Split (has 2 active beaches)
        $split = collect($data)->firstWhere('name', 'Split');
        $this->assertEquals(2, $split['beaches_count']);

        // Verify results are ordered by distance
        $distances = collect($data)->pluck('distance_km')->toArray();
        $this->assertEquals($distances, collect($distances)->sort()->values()->toArray());
    }

    public function test_nearby_cities_returns_422_for_missing_latitude()
    {
        $response = $this->getJson('/api/v1/spatial/cities/nearby?longitude=16.4', [
            'Accept-Language' => 'en',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['latitude']);
    }

    public function test_nearby_cities_returns_422_for_missing_longitude()
    {
        $response = $this->getJson('/api/v1/spatial/cities/nearby?latitude=43.5', [
            'Accept-Language' => 'en',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['longitude']);
    }

    public function test_nearby_cities_returns_422_for_missing_both_params()
    {
        $response = $this->getJson('/api/v1/spatial/cities/nearby', [
            'Accept-Language' => 'en',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['latitude', 'longitude']);
    }

    public function test_nearby_cities_returns_empty_for_remote_location()
    {
        // Search in the middle of the Pacific Ocean with small radius
        $response = $this->getJson('/api/v1/spatial/cities/nearby?latitude=0&longitude=-150&radius=10', [
            'Accept-Language' => 'en',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('meta.count', 0)
            ->assertJsonCount(0, 'data');
    }

    public function test_nearby_cities_respects_limit_parameter()
    {
        // Search near Split with large radius to get all cities, but limit to 1
        $response = $this->getJson('/api/v1/spatial/cities/nearby?latitude=43.5&longitude=16.4&radius=500&limit=1', [
            'Accept-Language' => 'en',
        ]);

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_nearby_cities_uses_default_radius_and_limit()
    {
        $response = $this->getJson('/api/v1/spatial/cities/nearby?latitude=43.5&longitude=16.4', [
            'Accept-Language' => 'en',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('meta.radius_km', 50.0)
            ->assertJsonPath('meta.limit', 10);
    }
}
