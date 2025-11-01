<?php

namespace Tests\Feature;

use App\Models\ParkingCapacity;
use App\Models\ParkingCapacityTranslation;
use App\Models\Status;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ParkingCapacityApiTest extends TestCase
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

    public function test_can_get_all_parking_capacities()
    {
        $item = ParkingCapacity::create([
            'code' => 'LARGE',
            'status_id' => 1,
        ]);

        ParkingCapacityTranslation::create([
            'parking_capacity_id' => $item->id,
            'locale' => 'en',
            'name' => 'Large Capacity',
        ]);

        $response = $this->getJson('/api/v1/parking-capacities');

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
                            'code' => 'LARGE',
                            'name' => 'Large Capacity',
                        ]
                    ]
                ]);
    }

    public function test_can_get_specific_parking_capacitie()
    {
        $item = ParkingCapacity::create(['code' => 'LARGE', 'status_id' => 1]);
        ParkingCapacityTranslation::create(['parking_capacity_id' => $item->id, 'locale' => 'en', 'name' => 'Large Capacity']);

        $response = $this->getJson("/api/v1/parking-capacities/{$item->id}");

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'code' => 'LARGE',
                        'name' => 'Large Capacity',
                    ]
                ]);
    }

    public function test_returns_404_for_nonexistent_parking_capacitie()
    {
        $response = $this->getJson('/api/v1/parking-capacities/999');

        $response->assertStatus(404)
                ->assertJson([
                    'success' => false,
                ]);
    }

    public function test_only_returns_active_parking_capacities()
    {
        $item1 = ParkingCapacity::create(['code' => 'LARGE1', 'status_id' => 1]);
        $item2 = ParkingCapacity::create(['code' => 'LARGE2', 'status_id' => 2]);

        ParkingCapacityTranslation::create(['parking_capacity_id' => $item1->id, 'locale' => 'en', 'name' => 'Large Capacity 1']);
        ParkingCapacityTranslation::create(['parking_capacity_id' => $item2->id, 'locale' => 'en', 'name' => 'Large Capacity 2']);

        $response = $this->getJson('/api/v1/parking-capacities');

        $response->assertStatus(200)
                ->assertJsonCount(1, 'data')
                ->assertJson([
                    'data' => [
                        ['code' => 'LARGE1']
                    ]
                ]);
    }

    public function test_respects_accept_language_header()
    {
        $item = ParkingCapacity::create(['code' => 'LARGE', 'status_id' => 1]);
        ParkingCapacityTranslation::create(['parking_capacity_id' => $item->id, 'locale' => 'en', 'name' => 'Large Capacity']);
        ParkingCapacityTranslation::create(['parking_capacity_id' => $item->id, 'locale' => 'sl', 'name' => 'Velika kapaciteta']);

        $response = $this->getJson('/api/v1/parking-capacities', ['Accept-Language' => 'sl']);

        $response->assertStatus(200)
                ->assertJson([
                    'data' => [
                        ['name' => 'Velika kapaciteta']
                    ]
                ]);
    }

    public function test_cache_headers_are_present()
    {
        \Illuminate\Support\Facades\Cache::flush();

        $item = ParkingCapacity::create(['code' => 'LARGE', 'status_id' => 1]);
        ParkingCapacityTranslation::create(['parking_capacity_id' => $item->id, 'locale' => 'en', 'name' => 'Large Capacity']);

        $response = $this->getJson('/api/v1/parking-capacities', ['Accept-Language' => 'en']);

        $response->assertStatus(200)
                ->assertHeader('X-Cache-Status')
                ->assertHeader('X-Cache-Key')
                ->assertHeader('X-Locale', 'en');
    }
}
