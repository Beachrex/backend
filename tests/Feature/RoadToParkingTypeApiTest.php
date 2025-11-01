<?php

namespace Tests\Feature;

use App\Models\RoadToParkingType;
use App\Models\RoadToParkingTypeTranslation;
use App\Models\Status;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoadToParkingTypeApiTest extends TestCase
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

    public function test_can_get_all_road_to_parking_types()
    {
        $item = RoadToParkingType::create([
            'code' => 'PAVED',
            'status_id' => 1,
        ]);

        RoadToParkingTypeTranslation::create([
            'road_to_parking_type_id' => $item->id,
            'locale' => 'en',
            'name' => 'Paved Road',
        ]);

        $response = $this->getJson('/api/v1/road-to-parking-types');

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
                            'code' => 'PAVED',
                            'name' => 'Paved Road',
                        ]
                    ]
                ]);
    }

    public function test_can_get_specific_road_to_parking_type()
    {
        $item = RoadToParkingType::create(['code' => 'PAVED', 'status_id' => 1]);
        RoadToParkingTypeTranslation::create(['road_to_parking_type_id' => $item->id, 'locale' => 'en', 'name' => 'Paved Road']);

        $response = $this->getJson("/api/v1/road-to-parking-types/{$item->id}");

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'code' => 'PAVED',
                        'name' => 'Paved Road',
                    ]
                ]);
    }

    public function test_returns_404_for_nonexistent_road_to_parking_type()
    {
        $response = $this->getJson('/api/v1/road-to-parking-types/999');

        $response->assertStatus(404)
                ->assertJson([
                    'success' => false,
                ]);
    }

    public function test_only_returns_active_road_to_parking_types()
    {
        $item1 = RoadToParkingType::create(['code' => 'PAVED1', 'status_id' => 1]);
        $item2 = RoadToParkingType::create(['code' => 'PAVED2', 'status_id' => 2]);

        RoadToParkingTypeTranslation::create(['road_to_parking_type_id' => $item1->id, 'locale' => 'en', 'name' => 'Paved Road 1']);
        RoadToParkingTypeTranslation::create(['road_to_parking_type_id' => $item2->id, 'locale' => 'en', 'name' => 'Paved Road 2']);

        $response = $this->getJson('/api/v1/road-to-parking-types');

        $response->assertStatus(200)
                ->assertJsonCount(1, 'data')
                ->assertJson([
                    'data' => [
                        ['code' => 'PAVED1']
                    ]
                ]);
    }

    public function test_respects_accept_language_header()
    {
        $item = RoadToParkingType::create(['code' => 'PAVED', 'status_id' => 1]);
        RoadToParkingTypeTranslation::create(['road_to_parking_type_id' => $item->id, 'locale' => 'en', 'name' => 'Paved Road']);
        RoadToParkingTypeTranslation::create(['road_to_parking_type_id' => $item->id, 'locale' => 'sl', 'name' => 'Asfaltirana cesta']);

        $response = $this->getJson('/api/v1/road-to-parking-types', ['Accept-Language' => 'sl']);

        $response->assertStatus(200)
                ->assertJson([
                    'data' => [
                        ['name' => 'Asfaltirana cesta']
                    ]
                ]);
    }

    public function test_cache_headers_are_present()
    {
        \Illuminate\Support\Facades\Cache::flush();

        $item = RoadToParkingType::create(['code' => 'PAVED', 'status_id' => 1]);
        RoadToParkingTypeTranslation::create(['road_to_parking_type_id' => $item->id, 'locale' => 'en', 'name' => 'Paved Road']);

        $response = $this->getJson('/api/v1/road-to-parking-types', ['Accept-Language' => 'en']);

        $response->assertStatus(200)
                ->assertHeader('X-Cache-Status')
                ->assertHeader('X-Cache-Key')
                ->assertHeader('X-Locale', 'en');
    }
}
