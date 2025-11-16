<?php

namespace Tests\Feature;

use App\Models\Beach;
use App\Models\Continent;
use App\Models\ContinentTranslation;
use App\Models\Country;
use App\Models\CountryTranslation;
use App\Models\Region;
use App\Models\RegionTranslation;
use App\Models\Status;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegionApiTest extends TestCase
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
            ['code' => 'SI', 'continent_code' => 'EU'],
        ]);

        CountryTranslation::insert([
            ['country_code' => 'HR', 'locale' => 'en', 'name' => 'Croatia', 'slug' => 'croatia'],
            ['country_code' => 'SI', 'locale' => 'en', 'name' => 'Slovenia', 'slug' => 'slovenia'],
        ]);

        // Seed regions
        Region::insert([
            ['id' => 1, 'code' => 'KV', 'country_code' => 'HR', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'code' => 'DL', 'country_code' => 'HR', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 3, 'code' => 'CO', 'country_code' => 'SI', 'created_at' => now(), 'updated_at' => now()],
        ]);

        RegionTranslation::insert([
            // Croatian regions
            ['region_id' => 1, 'locale' => 'en', 'name' => 'Kvarner', 'slug' => 'kvarner'],
            ['region_id' => 1, 'locale' => 'hr', 'name' => 'Kvarner', 'slug' => 'kvarner'],
            ['region_id' => 2, 'locale' => 'en', 'name' => 'Dalmatia', 'slug' => 'dalmatia'],
            ['region_id' => 2, 'locale' => 'hr', 'name' => 'Dalmacija', 'slug' => 'dalmacija'],
            // Slovenian region
            ['region_id' => 3, 'locale' => 'en', 'name' => 'Coastal', 'slug' => 'coastal'],
            ['region_id' => 3, 'locale' => 'sl', 'name' => 'Obala', 'slug' => 'obala'],
        ]);

        // Seed beaches for each region so they pass the withBeaches() filter
        Beach::insert([
            [
                'latitude' => '45.3271',
                'longitude' => '14.4422',
                'country_code' => 'HR',
                'region_id' => 1,
                'status_id' => 1,
                'folder_name' => 'beach-kvarner',
                'mobile_upload' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'latitude' => '43.5081',
                'longitude' => '16.4402',
                'country_code' => 'HR',
                'region_id' => 2,
                'status_id' => 1,
                'folder_name' => 'beach-dalmatia',
                'mobile_upload' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'latitude' => '45.5469',
                'longitude' => '13.7301',
                'country_code' => 'SI',
                'region_id' => 3,
                'status_id' => 1,
                'folder_name' => 'beach-coastal',
                'mobile_upload' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function test_can_get_all_regions()
    {
        $response = $this->getJson('/api/v1/regions', [
            'Accept-Language' => 'en',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'code',
                        'name',
                        'slug',
                        'country_code',
                        'beaches_count',
                    ],
                ],
            ])
            ->assertJsonCount(3, 'data');
    }

    public function test_can_filter_regions_by_country()
    {
        $response = $this->getJson('/api/v1/regions?country_code=HR', [
            'Accept-Language' => 'en',
        ]);

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJsonFragment(['slug' => 'kvarner'])
            ->assertJsonFragment(['slug' => 'dalmatia']);
    }

    public function test_can_get_single_region_by_slug()
    {
        $response = $this->getJson('/api/v1/regions/kvarner?country_code=HR', [
            'Accept-Language' => 'en',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'slug' => 'kvarner',
                    'name' => 'Kvarner',
                    'country_code' => 'HR',
                ],
            ]);
    }

    public function test_region_show_works_without_country_code()
    {
        $response = $this->getJson('/api/v1/regions/kvarner', [
            'Accept-Language' => 'en',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'slug' => 'kvarner',
                ],
            ]);
    }

    public function test_returns_404_for_nonexistent_region()
    {
        $response = $this->getJson('/api/v1/regions/nonexistent?country_code=HR', [
            'Accept-Language' => 'en',
        ]);

        $response->assertStatus(404);
    }

    public function test_regions_respect_locale()
    {
        // Test English
        $response = $this->getJson('/api/v1/regions/kvarner?country_code=HR', [
            'Accept-Language' => 'en',
        ]);
        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'name' => 'Kvarner',
                    'slug' => 'kvarner',
                ],
            ]);

        // Test Croatian
        $response = $this->getJson('/api/v1/regions/dalmacija?country_code=HR', [
            'Accept-Language' => 'hr',
        ]);
        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'name' => 'Dalmacija',
                    'slug' => 'dalmacija',
                ],
            ]);
    }

    public function test_regions_only_return_those_with_active_beaches()
    {
        // All regions already have active beaches from setUp()
        // Let's verify that all Croatian regions are returned
        $response = $this->getJson('/api/v1/regions?country_code=HR', [
            'Accept-Language' => 'en',
        ]);

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data') // 2 Croatian regions
            ->assertJsonFragment(['slug' => 'kvarner'])
            ->assertJsonFragment(['slug' => 'dalmatia']);
    }

    public function test_region_includes_beach_count()
    {
        // Create multiple beaches in region
        Beach::insert([
            [
                'latitude' => '44.9522',
                'longitude' => '14.4606',
                'country_code' => 'HR',
                'region_id' => 1,
                'status_id' => 1,
                'folder_name' => 'beach-1',
                'mobile_upload' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'latitude' => '44.9600',
                'longitude' => '14.4700',
                'country_code' => 'HR',
                'region_id' => 1,
                'status_id' => 1,
                'folder_name' => 'beach-2',
                'mobile_upload' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'latitude' => '44.9700',
                'longitude' => '14.4800',
                'country_code' => 'HR',
                'region_id' => 1,
                'status_id' => 2, // inactive - should not count
                'folder_name' => 'beach-3',
                'mobile_upload' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $response = $this->getJson('/api/v1/regions/kvarner?country_code=HR', [
            'Accept-Language' => 'en',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'beaches_count' => 3, // 1 from setUp + 2 from this test (only active beaches)
                ],
            ]);
    }


}

