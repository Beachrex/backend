<?php

namespace Tests\Feature;

use App\Models\AccommodationAround;
use App\Models\AccommodationAroundTranslation;
use App\Models\Status;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccommodationAroundApiTest extends TestCase
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

    public function test_can_get_all_accommodation_around()
    {
        $item = AccommodationAround::create([
            'code' => 'HOTEL',
            'status_id' => 1,
        ]);

        AccommodationAroundTranslation::create([
            'accommodation_around_id' => $item->id,
            'locale' => 'en',
            'name' => 'Hotel',
        ]);

        $response = $this->getJson('/api/v1/accommodation-around');

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
                            'code' => 'HOTEL',
                            'name' => 'Hotel',
                        ]
                    ]
                ]);
    }

    public function test_can_get_specific_accommodation_around()
    {
        $item = AccommodationAround::create(['code' => 'HOTEL', 'status_id' => 1]);
        AccommodationAroundTranslation::create(['accommodation_around_id' => $item->id, 'locale' => 'en', 'name' => 'Hotel']);

        $response = $this->getJson("/api/v1/accommodation-around/{$item->id}");

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'code' => 'HOTEL',
                        'name' => 'Hotel',
                    ]
                ]);
    }

    public function test_returns_404_for_nonexistent_accommodation_around()
    {
        $response = $this->getJson('/api/v1/accommodation-around/999');

        $response->assertStatus(404)
                ->assertJson([
                    'success' => false,
                ]);
    }

    public function test_only_returns_active_accommodation_around()
    {
        $item1 = AccommodationAround::create(['code' => 'HOTEL1', 'status_id' => 1]);
        $item2 = AccommodationAround::create(['code' => 'HOTEL2', 'status_id' => 2]);

        AccommodationAroundTranslation::create(['accommodation_around_id' => $item1->id, 'locale' => 'en', 'name' => 'Hotel 1']);
        AccommodationAroundTranslation::create(['accommodation_around_id' => $item2->id, 'locale' => 'en', 'name' => 'Hotel 2']);

        $response = $this->getJson('/api/v1/accommodation-around');

        $response->assertStatus(200)
                ->assertJsonCount(1, 'data')
                ->assertJson([
                    'data' => [
                        ['code' => 'HOTEL1']
                    ]
                ]);
    }

    public function test_respects_accept_language_header()
    {
        $item = AccommodationAround::create(['code' => 'HOTEL', 'status_id' => 1]);
        AccommodationAroundTranslation::create(['accommodation_around_id' => $item->id, 'locale' => 'en', 'name' => 'Hotel']);
        AccommodationAroundTranslation::create(['accommodation_around_id' => $item->id, 'locale' => 'sl', 'name' => 'Hotel']);

        $response = $this->getJson('/api/v1/accommodation-around', ['Accept-Language' => 'sl']);

        $response->assertStatus(200)
                ->assertJson([
                    'data' => [
                        ['name' => 'Hotel']
                    ]
                ]);
    }

    public function test_cache_headers_are_present()
    {
        \Illuminate\Support\Facades\Cache::flush();

        $item = AccommodationAround::create(['code' => 'HOTEL', 'status_id' => 1]);
        AccommodationAroundTranslation::create(['accommodation_around_id' => $item->id, 'locale' => 'en', 'name' => 'Hotel']);

        $response = $this->getJson('/api/v1/accommodation-around', ['Accept-Language' => 'en']);

        $response->assertStatus(200)
                ->assertHeader('X-Cache-Status')
                ->assertHeader('X-Cache-Key')
                ->assertHeader('X-Locale', 'en');
    }
}
