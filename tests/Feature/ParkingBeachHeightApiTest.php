<?php

namespace Tests\Feature;

use App\Models\ParkingBeachHeight;
use App\Models\ParkingBeachHeightTranslation;
use App\Models\Status;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ParkingBeachHeightApiTest extends TestCase
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

    public function test_can_get_all_parking_beach_heights()
    {
        $item = ParkingBeachHeight::create([
            'code' => 'LEVEL',
            'status_id' => 1,
        ]);

        ParkingBeachHeightTranslation::create([
            'parking_beach_height_id' => $item->id,
            'locale' => 'en',
            'name' => 'Level',
        ]);

        $response = $this->getJson('/api/v1/parking-beach-heights');

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
                            'code' => 'LEVEL',
                            'name' => 'Level',
                        ]
                    ]
                ]);
    }

    public function test_can_get_specific_parking_beach_height()
    {
        $item = ParkingBeachHeight::create(['code' => 'LEVEL', 'status_id' => 1]);
        ParkingBeachHeightTranslation::create(['parking_beach_height_id' => $item->id, 'locale' => 'en', 'name' => 'Level']);

        $response = $this->getJson("/api/v1/parking-beach-heights/{$item->id}");

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'code' => 'LEVEL',
                        'name' => 'Level',
                    ]
                ]);
    }

    public function test_returns_404_for_nonexistent_parking_beach_height()
    {
        $response = $this->getJson('/api/v1/parking-beach-heights/999');

        $response->assertStatus(404)
                ->assertJson([
                    'success' => false,
                ]);
    }

    public function test_only_returns_active_parking_beach_heights()
    {
        $item1 = ParkingBeachHeight::create(['code' => 'LEVEL1', 'status_id' => 1]);
        $item2 = ParkingBeachHeight::create(['code' => 'LEVEL2', 'status_id' => 2]);

        ParkingBeachHeightTranslation::create(['parking_beach_height_id' => $item1->id, 'locale' => 'en', 'name' => 'Level 1']);
        ParkingBeachHeightTranslation::create(['parking_beach_height_id' => $item2->id, 'locale' => 'en', 'name' => 'Level 2']);

        $response = $this->getJson('/api/v1/parking-beach-heights');

        $response->assertStatus(200)
                ->assertJsonCount(1, 'data')
                ->assertJson([
                    'data' => [
                        ['code' => 'LEVEL1']
                    ]
                ]);
    }

    public function test_respects_accept_language_header()
    {
        $item = ParkingBeachHeight::create(['code' => 'LEVEL', 'status_id' => 1]);
        ParkingBeachHeightTranslation::create(['parking_beach_height_id' => $item->id, 'locale' => 'en', 'name' => 'Level']);
        ParkingBeachHeightTranslation::create(['parking_beach_height_id' => $item->id, 'locale' => 'sl', 'name' => 'Ravno']);

        $response = $this->getJson('/api/v1/parking-beach-heights', ['Accept-Language' => 'sl']);

        $response->assertStatus(200)
                ->assertJson([
                    'data' => [
                        ['name' => 'Ravno']
                    ]
                ]);
    }

    public function test_cache_headers_are_present()
    {
        \Illuminate\Support\Facades\Cache::flush();

        $item = ParkingBeachHeight::create(['code' => 'LEVEL', 'status_id' => 1]);
        ParkingBeachHeightTranslation::create(['parking_beach_height_id' => $item->id, 'locale' => 'en', 'name' => 'Level']);

        $response = $this->getJson('/api/v1/parking-beach-heights', ['Accept-Language' => 'en']);

        $response->assertStatus(200)
                ->assertHeader('X-Cache-Status')
                ->assertHeader('X-Cache-Key')
                ->assertHeader('X-Locale', 'en');
    }
}
