<?php

namespace Tests\Feature;

use App\Models\Beach;
use App\Models\BeachTranslation;
use App\Models\City;
use App\Models\CityTranslation;
use App\Models\Continent;
use App\Models\ContinentTranslation;
use App\Models\Country;
use App\Models\CountryTranslation;
use App\Models\Island;
use App\Models\IslandTranslation;
use App\Models\Region;
use App\Models\RegionTranslation;
use App\Models\Status;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SlugResolverApiTest extends TestCase
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
        Continent::create(['code' => 'EU']);
        ContinentTranslation::insert([
            ['continent_code' => 'EU', 'locale' => 'en', 'name' => 'Europe', 'slug' => 'europe'],
            ['continent_code' => 'EU', 'locale' => 'hr', 'name' => 'Europa', 'slug' => 'europa'],
        ]);

        // Seed countries
        Country::create(['code' => 'HR', 'continent_code' => 'EU']);
        CountryTranslation::insert([
            ['country_code' => 'HR', 'locale' => 'en', 'name' => 'Croatia', 'slug' => 'croatia'],
            ['country_code' => 'HR', 'locale' => 'hr', 'name' => 'Hrvatska', 'slug' => 'hrvatska'],
        ]);

        // Seed region
        Region::insert([
            ['id' => 1, 'code' => 'KV', 'country_code' => 'HR', 'created_at' => now(), 'updated_at' => now()],
        ]);
        RegionTranslation::insert([
            ['region_id' => 1, 'locale' => 'en', 'name' => 'Kvarner', 'slug' => 'kvarner'],
            ['region_id' => 1, 'locale' => 'hr', 'name' => 'Kvarner', 'slug' => 'kvarner'],
        ]);

        // Seed island
        Island::insert([
            ['id' => 1, 'code' => 'CRS', 'created_at' => now(), 'updated_at' => now()],
        ]);
        IslandTranslation::insert([
            ['island_id' => 1, 'locale' => 'en', 'name' => 'Island Cres', 'slug' => 'island-cres'],
            ['island_id' => 1, 'locale' => 'hr', 'name' => 'Otok Cres', 'slug' => 'otok-cres'],
        ]);

        // Seed city
        City::insert([
            ['id' => 1, 'code' => 'CRS', 'country_code' => 'HR', 'created_at' => now(), 'updated_at' => now()],
        ]);
        CityTranslation::insert([
            ['city_id' => 1, 'locale' => 'en', 'name' => 'Cres', 'slug' => 'cres'],
            ['city_id' => 1, 'locale' => 'hr', 'name' => 'Cres', 'slug' => 'cres'],
        ]);
    }

    public function test_can_resolve_beach_slug()
    {
        $beach = Beach::create([
            'latitude' => '44.9522',
            'longitude' => '14.4606',
            'country_code' => 'HR',
            'region_id' => 1,
            'status_id' => 1,
            'folder_name' => 'test-beach',
            'mobile_upload' => false,
        ]);

        BeachTranslation::create([
            'beach_id' => $beach->id,
            'locale' => 'en',
            'name' => 'Test Beach',
            'slug' => 'beach-test',
        ]);

        $response = $this->getJson('/api/v1/resolve-slug/beach-test?country_code=HR', [
            'Accept-Language' => 'en',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'type' => 'beach',
                    'slug' => 'beach-test',
                ],
            ]);
    }

    public function test_can_resolve_region_slug()
    {
        $response = $this->getJson('/api/v1/resolve-slug/kvarner?country_code=HR', [
            'Accept-Language' => 'en',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'type' => 'region',
                    'slug' => 'kvarner',
                ],
            ]);
    }

    public function test_can_resolve_island_slug()
    {
        $response = $this->getJson('/api/v1/resolve-slug/island-cres?country_code=HR', [
            'Accept-Language' => 'en',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'type' => 'island',
                    'slug' => 'island-cres',
                ],
            ]);
    }

    public function test_can_resolve_city_slug()
    {
        $response = $this->getJson('/api/v1/resolve-slug/cres?country_code=HR', [
            'Accept-Language' => 'en',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'type' => 'city',
                    'slug' => 'cres',
                ],
            ]);
    }

    public function test_returns_404_for_nonexistent_slug()
    {
        $response = $this->getJson('/api/v1/resolve-slug/nonexistent-slug?country_code=HR', [
            'Accept-Language' => 'en',
        ]);

        $response->assertStatus(404)
            ->assertJson([
                'message' => 'Slug not found',
            ]);
    }

    public function test_works_without_country_code()
    {
        $response = $this->getJson('/api/v1/resolve-slug/kvarner', [
            'Accept-Language' => 'en',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'type' => 'region',
                    'slug' => 'kvarner',
                ],
            ]);
    }

    public function test_uses_segment_count_for_optimization()
    {
        // Create a beach with slug that could be ambiguous
        $beach = Beach::create([
            'latitude' => '44.9522',
            'longitude' => '14.4606',
            'country_code' => 'HR',
            'region_id' => 1,
            'status_id' => 1,
            'folder_name' => 'test',
            'mobile_upload' => false,
        ]);

        BeachTranslation::create([
            'beach_id' => $beach->id,
            'locale' => 'en',
            'name' => 'Test',
            'slug' => 'test',
        ]);

        // With segment_count=4, should prioritize beach search
        $response = $this->getJson('/api/v1/resolve-slug/test?country_code=HR&segment_count=4', [
            'Accept-Language' => 'en',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'type' => 'beach',
                ],
            ]);

        // With segment_count=1, should prioritize region search
        $response = $this->getJson('/api/v1/resolve-slug/kvarner?country_code=HR&segment_count=1', [
            'Accept-Language' => 'en',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'type' => 'region',
                ],
            ]);
    }

    public function test_respects_locale()
    {
        $response = $this->getJson('/api/v1/resolve-slug/kvarner?country_code=HR', [
            'Accept-Language' => 'hr',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'type' => 'region',
                    'slug' => 'kvarner',
                ],
            ]);
    }

    public function test_ignores_inactive_beaches()
    {
        $beach = Beach::create([
            'latitude' => '44.9522',
            'longitude' => '14.4606',
            'country_code' => 'HR',
            'region_id' => 1,
            'status_id' => 2, // inactive
            'folder_name' => 'inactive',
            'mobile_upload' => false,
        ]);

        BeachTranslation::create([
            'beach_id' => $beach->id,
            'locale' => 'en',
            'name' => 'Inactive Beach',
            'slug' => 'inactive-beach',
        ]);

        $response = $this->getJson('/api/v1/resolve-slug/inactive-beach?country_code=HR', [
            'Accept-Language' => 'en',
        ]);

        $response->assertStatus(404);
    }

    public function test_can_use_parent_slug_for_context()
    {
        // Create a city with same name as island (like Cres)
        $response = $this->getJson('/api/v1/resolve-slug/cres?country_code=HR&parent_slug=island-cres', [
            'Accept-Language' => 'en',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'type' => 'city',
                    'slug' => 'cres',
                ],
            ]);
    }
}

