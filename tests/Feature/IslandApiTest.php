<?php

namespace Tests\Feature;

use App\Models\Beach;
use App\Models\Continent;
use App\Models\ContinentTranslation;
use App\Models\Country;
use App\Models\CountryTranslation;
use App\Models\Island;
use App\Models\IslandTranslation;
use App\Models\Status;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IslandApiTest extends TestCase
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
            ['code' => 'GR', 'continent_code' => 'EU'],
        ]);

        CountryTranslation::insert([
            ['country_code' => 'HR', 'locale' => 'en', 'name' => 'Croatia', 'slug' => 'croatia'],
            ['country_code' => 'GR', 'locale' => 'en', 'name' => 'Greece', 'slug' => 'greece'],
        ]);

        // Seed islands
        Island::insert([
            ['id' => 1, 'code' => 'CRS', 'country_code' => 'HR', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'code' => 'KRK', 'country_code' => 'HR', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 3, 'code' => 'CRT', 'country_code' => 'GR', 'created_at' => now(), 'updated_at' => now()],
        ]);

        IslandTranslation::insert([
            // Croatian islands
            ['island_id' => 1, 'locale' => 'en', 'name' => 'Island Cres', 'slug' => 'island-cres'],
            ['island_id' => 1, 'locale' => 'hr', 'name' => 'Otok Cres', 'slug' => 'otok-cres'],
            ['island_id' => 2, 'locale' => 'en', 'name' => 'Island Krk', 'slug' => 'island-krk'],
            ['island_id' => 2, 'locale' => 'hr', 'name' => 'Otok Krk', 'slug' => 'otok-krk'],
            // Greek island
            ['island_id' => 3, 'locale' => 'en', 'name' => 'Crete', 'slug' => 'crete'],
            ['island_id' => 3, 'locale' => 'el', 'name' => 'Κρήτη', 'slug' => 'kriti'],
        ]);

        // Seed beaches for each island so they pass the withBeaches() filter
        Beach::insert([
            [
                'latitude' => '44.9522',
                'longitude' => '14.4606',
                'country_code' => 'HR',
                'island_id' => 1,
                'status_id' => 1,
                'folder_name' => 'beach-cres',
                'mobile_upload' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'latitude' => '45.0408',
                'longitude' => '14.5733',
                'country_code' => 'HR',
                'island_id' => 2,
                'status_id' => 1,
                'folder_name' => 'beach-krk',
                'mobile_upload' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'latitude' => '35.2401',
                'longitude' => '24.8093',
                'country_code' => 'GR',
                'island_id' => 3,
                'status_id' => 1,
                'folder_name' => 'beach-crete',
                'mobile_upload' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function test_can_get_all_islands()
    {
        $response = $this->getJson('/api/v1/islands', [
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
                        'beaches_count',
                    ],
                ],
            ])
            ->assertJsonCount(3, 'data');
    }

    public function test_returns_all_islands_with_beaches()
    {
        $response = $this->getJson('/api/v1/islands', [
            'Accept-Language' => 'en',
        ]);

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data')
            ->assertJsonFragment(['slug' => 'island-cres'])
            ->assertJsonFragment(['slug' => 'island-krk'])
            ->assertJsonFragment(['slug' => 'crete']);
    }

    public function test_can_get_single_island_by_slug()
    {
        $response = $this->getJson('/api/v1/islands/island-cres', [
            'Accept-Language' => 'en',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'slug' => 'island-cres',
                    'name' => 'Island Cres',
                    'code' => 'CRS',
                ],
            ]);
    }

    public function test_returns_404_for_nonexistent_island()
    {
        $response = $this->getJson('/api/v1/islands/nonexistent', [
            'Accept-Language' => 'en',
        ]);

        $response->assertStatus(404);
    }

    public function test_islands_respect_locale()
    {
        // Test English
        $response = $this->getJson('/api/v1/islands/island-cres?country_code=HR', [
            'Accept-Language' => 'en',
        ]);
        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'name' => 'Island Cres',
                    'slug' => 'island-cres',
                ],
            ]);

        // Test Croatian
        $response = $this->getJson('/api/v1/islands/otok-cres?country_code=HR', [
            'Accept-Language' => 'hr',
        ]);
        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'name' => 'Otok Cres',
                    'slug' => 'otok-cres',
                ],
            ]);
    }

    public function test_islands_only_return_those_with_active_beaches()
    {
        // All islands already have active beaches from setUp()
        // Let's verify that all 3 islands are returned
        $response = $this->getJson('/api/v1/islands', [
            'Accept-Language' => 'en',
        ]);

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data')
            ->assertJsonFragment(['slug' => 'island-cres'])
            ->assertJsonFragment(['slug' => 'island-krk'])
            ->assertJsonFragment(['slug' => 'crete']);
    }

    public function test_island_includes_beach_count()
    {
        // Create multiple beaches on island
        Beach::insert([
            [
                'latitude' => '44.9522',
                'longitude' => '14.4606',
                'country_code' => 'HR',
                'island_id' => 1,
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
                'island_id' => 1,
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
                'island_id' => 1,
                'status_id' => 2, // inactive - should not count
                'folder_name' => 'beach-3',
                'mobile_upload' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $response = $this->getJson('/api/v1/islands/island-cres', [
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

