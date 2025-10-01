<?php

namespace Tests\Feature;

use App\Models\Beach;
use App\Models\Continent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContinentApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Seed continents
        Continent::insert([
            ['code' => 'EU', 'name' => 'Europe'],
            ['code' => 'AS', 'name' => 'Asia'],
            ['code' => 'NA', 'name' => 'North America'],
        ]);
    }

    public function test_can_get_continents_with_beaches()
    {
        // Create beaches in different continents
        Beach::create([
            'user_id' => null,
            'name' => 'Test Beach EU',
            'latitude' => 45.0,
            'longitude' => 10.0,
            'status_id' => 1,
            'continent_code' => 'EU'
        ]);

        Beach::create([
            'user_id' => null,
            'name' => 'Test Beach AS',
            'latitude' => 35.0,
            'longitude' => 120.0,
            'status_id' => 1,
            'continent_code' => 'AS'
        ]);

        $response = $this->getJson('/api/v1/continents');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        '*' => [
                            'code',
                            'name',
                            'beaches_count'
                        ]
                    ],
                    'message'
                ])
                ->assertJsonCount(2, 'data'); // Only continents with beaches
    }

    public function test_can_get_specific_continent()
    {
        Beach::create([
            'user_id' => null,
            'name' => 'Test Beach EU',
            'latitude' => 45.0,
            'longitude' => 10.0,
            'status_id' => 1,
            'continent_code' => 'EU'
        ]);

        $response = $this->getJson('/api/v1/continents/EU');

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'code' => 'EU',
                        'name' => 'Europe',
                        'beaches_count' => 1
                    ]
                ]);
    }

    public function test_returns_404_for_nonexistent_continent()
    {
        $response = $this->getJson('/api/v1/continents/XX');

        $response->assertStatus(404)
                ->assertJson([
                    'success' => false,
                    'message' => 'Continent not found'
                ]);
    }

    public function test_returns_continent_with_zero_beaches()
    {
        $response = $this->getJson('/api/v1/continents/NA');

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'code' => 'NA',
                        'name' => 'North America',
                        'beaches_count' => 0
                    ]
                ]);
    }
}
