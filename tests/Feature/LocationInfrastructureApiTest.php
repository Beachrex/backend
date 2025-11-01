<?php

namespace Tests\Feature;

use App\Models\LocationInfrastructure;
use App\Models\LocationInfrastructureTranslation;
use App\Models\Status;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocationInfrastructureApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Create status
        Status::insert([
            ['id' => 1, 'name' => 'active'],
            ['id' => 2, 'name' => 'inactive'],
        ]);
    }

    public function test_can_get_all_location_infrastructure()
    {
        $item = LocationInfrastructure::create([
            'code' => 'SHOWER',
            'status_id' => 1,
        ]);

        LocationInfrastructureTranslation::create([
            'location_infrastructure_id' => $item->id,
            'locale' => 'en',
            'name' => 'Shower',
        ]);

        $response = $this->getJson('/api/v1/location-infrastructure');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        '*' => [
                            'id',
                            'code',
                            'name',
                            'beaches_count',
                        ]
                    ],
                    'message'
                ])
                ->assertJson([
                    'success' => true,
                    'data' => [
                        [
                            'code' => 'SHOWER',
                            'name' => 'Shower',
                        ]
                    ]
                ]);
    }

    public function test_can_get_specific_location_infrastructure()
    {
        $item = LocationInfrastructure::create(['code' => 'SHOWER', 'status_id' => 1]);
        LocationInfrastructureTranslation::create(['location_infrastructure_id' => $item->id, 'locale' => 'en', 'name' => 'Shower']);

        $response = $this->getJson("/api/v1/location-infrastructure/{$item->id}");

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'code' => 'SHOWER',
                        'name' => 'Shower',
                    ]
                ]);
    }

    public function test_returns_404_for_nonexistent_location_infrastructure()
    {
        $response = $this->getJson('/api/v1/location-infrastructure/999');

        $response->assertStatus(404)
                ->assertJson([
                    'success' => false,
                ]);
    }

    public function test_only_returns_active_location_infrastructure()
    {
        $item1 = LocationInfrastructure::create(['code' => 'SHOWER1', 'status_id' => 1]);
        $item2 = LocationInfrastructure::create(['code' => 'SHOWER2', 'status_id' => 2]);

        LocationInfrastructureTranslation::create(['location_infrastructure_id' => $item1->id, 'locale' => 'en', 'name' => 'Shower 1']);
        LocationInfrastructureTranslation::create(['location_infrastructure_id' => $item2->id, 'locale' => 'en', 'name' => 'Shower 2']);

        $response = $this->getJson('/api/v1/location-infrastructure');

        $response->assertStatus(200)
                ->assertJsonCount(1, 'data')
                ->assertJson([
                    'data' => [
                        ['code' => 'SHOWER1']
                    ]
                ]);
    }

    public function test_respects_accept_language_header()
    {
        $item = LocationInfrastructure::create(['code' => 'SHOWER', 'status_id' => 1]);
        LocationInfrastructureTranslation::create(['location_infrastructure_id' => $item->id, 'locale' => 'en', 'name' => 'Shower']);
        LocationInfrastructureTranslation::create(['location_infrastructure_id' => $item->id, 'locale' => 'sl', 'name' => 'Tuš']);

        $response = $this->getJson('/api/v1/location-infrastructure', ['Accept-Language' => 'sl']);

        $response->assertStatus(200)
                ->assertJson([
                    'data' => [
                        ['name' => 'Tuš']
                    ]
                ]);
    }

    public function test_cache_headers_are_present()
    {
        \Illuminate\Support\Facades\Cache::flush();

        $item = LocationInfrastructure::create(['code' => 'SHOWER', 'status_id' => 1]);
        LocationInfrastructureTranslation::create(['location_infrastructure_id' => $item->id, 'locale' => 'en', 'name' => 'Shower']);

        $response = $this->getJson('/api/v1/location-infrastructure', ['Accept-Language' => 'en']);

        $response->assertStatus(200)
                ->assertHeader('X-Cache-Status')
                ->assertHeader('X-Cache-Key')
                ->assertHeader('X-Locale', 'en');
    }
}
