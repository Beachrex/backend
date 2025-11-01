<?php

namespace Tests\Feature;

use App\Models\Beach;
use App\Models\Continent;
use App\Models\Country;
use App\Models\Status;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CountryApiTest extends TestCase
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
            ['code' => 'EU', 'name' => 'Europe'],
            ['code' => 'AS', 'name' => 'Asia'],
            ['code' => 'NA', 'name' => 'North America'],
        ]);

        // Seed countries
        Country::insert([
            [
                'code' => 'SI',
                'name' => 'Slovenia',
                'full_name' => 'Republic of Slovenia',
                'iso3' => 'SVN',
                'continent_code' => 'EU'
            ],
            [
                'code' => 'US',
                'name' => 'United States of America',
                'full_name' => 'United States of America',
                'iso3' => 'USA',
                'continent_code' => 'NA'
            ],
            [
                'code' => 'DE',
                'name' => 'Germany',
                'full_name' => 'Federal Republic of Germany',
                'iso3' => 'DEU',
                'continent_code' => 'EU'
            ],
        ]);
    }

    public function test_can_get_countries_with_beaches()
    {
        // Create beaches in different countries
        Beach::create([
            'user_id' => null,
            'name' => 'Test Beach SI',
            'latitude' => 45.0,
            'longitude' => 14.0,
            'status_id' => 1,
            'continent_code' => 'EU',
            'country_code' => 'SI'
        ]);

        Beach::create([
            'user_id' => null,
            'name' => 'Test Beach US',
            'latitude' => 40.0,
            'longitude' => -74.0,
            'status_id' => 1,
            'continent_code' => 'NA',
            'country_code' => 'US'
        ]);

        $response = $this->getJson('/api/v1/countries');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        '*' => [
                            'code',
                            'name',
                            'full_name',
                            'iso3',
                            'continent_code',
                            'beaches_count'
                        ]
                    ],
                    'message'
                ])
                ->assertJsonCount(2, 'data'); // Only countries with beaches
    }

    public function test_can_filter_countries_by_continent()
    {
        // Create beaches in different countries
        Beach::create([
            'user_id' => null,
            'name' => 'Test Beach SI',
            'latitude' => 45.0,
            'longitude' => 14.0,
            'status_id' => 1,
            'continent_code' => 'EU',
            'country_code' => 'SI'
        ]);

        Beach::create([
            'user_id' => null,
            'name' => 'Test Beach US',
            'latitude' => 40.0,
            'longitude' => -74.0,
            'status_id' => 1,
            'continent_code' => 'NA',
            'country_code' => 'US'
        ]);

        $response = $this->getJson('/api/v1/countries?continent=EU');

        $response->assertStatus(200)
                ->assertJsonCount(1, 'data')
                ->assertJsonPath('data.0.code', 'SI')
                ->assertJsonPath('data.0.continent_code', 'EU');
    }

    public function test_can_get_specific_country_with_continent()
    {
        Beach::create([
            'user_id' => null,
            'name' => 'Test Beach SI',
            'latitude' => 45.0,
            'longitude' => 14.0,
            'status_id' => 1,
            'continent_code' => 'EU',
            'country_code' => 'SI'
        ]);

        $response = $this->getJson('/api/v1/countries/SI');

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'code' => 'SI',
                        'name' => 'Slovenia',
                        'full_name' => 'Republic of Slovenia',
                        'iso3' => 'SVN',
                        'continent_code' => 'EU',
                        'beaches_count' => 1,
                        'continent' => [
                            'code' => 'EU',
                            'name' => 'Europe'
                        ]
                    ]
                ]);
    }

    public function test_returns_404_for_nonexistent_country()
    {
        $response = $this->getJson('/api/v1/countries/XX');

        $response->assertStatus(404)
                ->assertJson([
                    'success' => false,
                    'message' => 'Country not found'
                ]);
    }

    public function test_returns_country_with_zero_beaches()
    {
        $response = $this->getJson('/api/v1/countries/DE');

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'code' => 'DE',
                        'name' => 'Germany',
                        'beaches_count' => 0
                    ]
                ]);
    }
}
