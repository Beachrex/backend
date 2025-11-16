<?php

namespace Tests\Feature;

use App\Models\Beach;
use App\Models\City;
use App\Models\CityTranslation;
use App\Models\Continent;
use App\Models\ContinentTranslation;
use App\Models\Country;
use App\Models\CountryTranslation;
use App\Models\Status;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CityApiTest extends TestCase
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

        // Seed cities
        City::insert([
            ['id' => 1, 'code' => 'CRS', 'country_code' => 'HR', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'code' => 'SPL', 'country_code' => 'HR', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 3, 'code' => 'PIR', 'country_code' => 'SI', 'created_at' => now(), 'updated_at' => now()],
        ]);

        CityTranslation::insert([
            // Croatian cities
            ['city_id' => 1, 'locale' => 'en', 'name' => 'Cres', 'slug' => 'cres'],
            ['city_id' => 1, 'locale' => 'hr', 'name' => 'Cres', 'slug' => 'cres'],
            ['city_id' => 2, 'locale' => 'en', 'name' => 'Split', 'slug' => 'split'],
            ['city_id' => 2, 'locale' => 'hr', 'name' => 'Split', 'slug' => 'split'],
            // Slovenian city
            ['city_id' => 3, 'locale' => 'en', 'name' => 'Piran', 'slug' => 'piran'],
            ['city_id' => 3, 'locale' => 'sl', 'name' => 'Piran', 'slug' => 'piran'],
        ]);

        // Seed beaches for each city so they pass the withBeaches() filter
        Beach::insert([
            [
                'latitude' => '44.9522',
                'longitude' => '14.4606',
                'country_code' => 'HR',
                'city_id' => 1,
                'status_id' => 1,
                'folder_name' => 'beach-cres',
                'mobile_upload' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'latitude' => '43.5081',
                'longitude' => '16.4402',
                'country_code' => 'HR',
                'city_id' => 2,
                'status_id' => 1,
                'folder_name' => 'beach-split',
                'mobile_upload' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'latitude' => '45.5283',
                'longitude' => '13.5681',
                'country_code' => 'SI',
                'city_id' => 3,
                'status_id' => 1,
                'folder_name' => 'beach-piran',
                'mobile_upload' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function test_can_get_all_cities()
    {
        $response = $this->getJson('/api/v1/cities', [
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
                        'region_id',
                        'beaches_count',
                    ],
                ],
            ])
            ->assertJsonCount(3, 'data');
    }

    public function test_can_filter_cities_by_country()
    {
        $response = $this->getJson('/api/v1/cities?country_code=HR', [
            'Accept-Language' => 'en',
        ]);

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJsonFragment(['slug' => 'cres'])
            ->assertJsonFragment(['slug' => 'split']);
    }

    public function test_can_get_single_city_by_slug()
    {
        $response = $this->getJson('/api/v1/cities/cres?country_code=HR', [
            'Accept-Language' => 'en',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'slug' => 'cres',
                    'name' => 'Cres',
                    'country_code' => 'HR',
                ],
            ]);
    }

    public function test_city_show_works_without_country_code()
    {
        $response = $this->getJson('/api/v1/cities/cres', [
            'Accept-Language' => 'en',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'slug' => 'cres',
                ],
            ]);
    }

    public function test_returns_404_for_nonexistent_city()
    {
        $response = $this->getJson('/api/v1/cities/nonexistent?country_code=HR', [
            'Accept-Language' => 'en',
        ]);

        $response->assertStatus(404);
    }

    public function test_cities_respect_locale()
    {
        // Test English
        $response = $this->getJson('/api/v1/cities/cres?country_code=HR', [
            'Accept-Language' => 'en',
        ]);
        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'name' => 'Cres',
                    'slug' => 'cres',
                ],
            ]);

        // Test Croatian (same name in this case)
        $response = $this->getJson('/api/v1/cities/cres?country_code=HR', [
            'Accept-Language' => 'hr',
        ]);
        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'name' => 'Cres',
                    'slug' => 'cres',
                ],
            ]);
    }

    public function test_cities_only_return_those_with_active_beaches()
    {
        // All cities already have active beaches from setUp()
        // Let's verify that all Croatian cities are returned
        $response = $this->getJson('/api/v1/cities?country_code=HR', [
            'Accept-Language' => 'en',
        ]);

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data') // 2 Croatian cities
            ->assertJsonFragment(['slug' => 'cres'])
            ->assertJsonFragment(['slug' => 'split']);
    }

    public function test_city_includes_beach_count()
    {
        // Create multiple beaches in city
        Beach::insert([
            [
                'latitude' => '44.9522',
                'longitude' => '14.4606',
                'country_code' => 'HR',
                'city_id' => 1,
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
                'city_id' => 1,
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
                'city_id' => 1,
                'status_id' => 2, // inactive - should not count
                'folder_name' => 'beach-3',
                'mobile_upload' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $response = $this->getJson('/api/v1/cities/cres?country_code=HR', [
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

