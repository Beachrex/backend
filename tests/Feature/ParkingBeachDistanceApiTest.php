<?php

namespace Tests\Feature;

use App\Models\ParkingBeachDistance;
use App\Models\ParkingBeachDistanceTranslation;
use App\Models\Status;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ParkingBeachDistanceApiTest extends TestCase
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

    public function test_can_get_all_parking_beach_distances()
    {
        $item = ParkingBeachDistance::create([
            'code' => 'NEAR',
            'status_id' => 1,
        ]);

        ParkingBeachDistanceTranslation::create([
            'parking_beach_distance_id' => $item->id,
            'locale' => 'en',
            'name' => 'Near',
        ]);

        $response = $this->getJson('/api/v1/parking-beach-distances');

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
                            'code' => 'NEAR',
                            'name' => 'Near',
                        ]
                    ]
                ]);
    }

    public function test_can_get_specific_parking_beach_distance()
    {
        $item = ParkingBeachDistance::create(['code' => 'NEAR', 'status_id' => 1]);
        ParkingBeachDistanceTranslation::create(['parking_beach_distance_id' => $item->id, 'locale' => 'en', 'name' => 'Near']);

        $response = $this->getJson("/api/v1/parking-beach-distances/{$item->id}");

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'code' => 'NEAR',
                        'name' => 'Near',
                    ]
                ]);
    }

    public function test_returns_404_for_nonexistent_parking_beach_distance()
    {
        $response = $this->getJson('/api/v1/parking-beach-distances/999');

        $response->assertStatus(404)
                ->assertJson([
                    'success' => false,
                ]);
    }

    public function test_only_returns_active_parking_beach_distances()
    {
        $item1 = ParkingBeachDistance::create(['code' => 'NEAR1', 'status_id' => 1]);
        $item2 = ParkingBeachDistance::create(['code' => 'NEAR2', 'status_id' => 2]);

        ParkingBeachDistanceTranslation::create(['parking_beach_distance_id' => $item1->id, 'locale' => 'en', 'name' => 'Near 1']);
        ParkingBeachDistanceTranslation::create(['parking_beach_distance_id' => $item2->id, 'locale' => 'en', 'name' => 'Near 2']);

        $response = $this->getJson('/api/v1/parking-beach-distances');

        $response->assertStatus(200)
                ->assertJsonCount(1, 'data')
                ->assertJson([
                    'data' => [
                        ['code' => 'NEAR1']
                    ]
                ]);
    }

    public function test_respects_accept_language_header()
    {
        $item = ParkingBeachDistance::create(['code' => 'NEAR', 'status_id' => 1]);
        ParkingBeachDistanceTranslation::create(['parking_beach_distance_id' => $item->id, 'locale' => 'en', 'name' => 'Near']);
        ParkingBeachDistanceTranslation::create(['parking_beach_distance_id' => $item->id, 'locale' => 'sl', 'name' => 'Blizu']);

        $response = $this->getJson('/api/v1/parking-beach-distances', ['Accept-Language' => 'sl']);

        $response->assertStatus(200)
                ->assertJson([
                    'data' => [
                        ['name' => 'Blizu']
                    ]
                ]);
    }

    public function test_cache_headers_are_present()
    {
        \Illuminate\Support\Facades\Cache::flush();

        $item = ParkingBeachDistance::create(['code' => 'NEAR', 'status_id' => 1]);
        ParkingBeachDistanceTranslation::create(['parking_beach_distance_id' => $item->id, 'locale' => 'en', 'name' => 'Near']);

        $response = $this->getJson('/api/v1/parking-beach-distances', ['Accept-Language' => 'en']);

        $response->assertStatus(200)
                ->assertHeader('X-Cache-Status')
                ->assertHeader('X-Cache-Key')
                ->assertHeader('X-Locale', 'en');
    }
}
