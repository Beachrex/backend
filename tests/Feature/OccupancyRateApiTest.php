<?php

namespace Tests\Feature;

use App\Models\OccupancyRate;
use App\Models\OccupancyRateTranslation;
use App\Models\Status;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OccupancyRateApiTest extends TestCase
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

    public function test_can_get_all_occupancy_rates()
    {
        $item = OccupancyRate::create([
            'code' => 'LOW',
            'status_id' => 1,
        ]);

        OccupancyRateTranslation::create([
            'occupancy_rate_id' => $item->id,
            'locale' => 'en',
            'name' => 'Low Occupancy',
        ]);

        $response = $this->getJson('/api/v1/occupancy-rates');

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
                            'code' => 'LOW',
                            'name' => 'Low Occupancy',
                        ]
                    ]
                ]);
    }

    public function test_can_get_specific_occupancy_rate()
    {
        $item = OccupancyRate::create(['code' => 'LOW', 'status_id' => 1]);
        OccupancyRateTranslation::create(['occupancy_rate_id' => $item->id, 'locale' => 'en', 'name' => 'Low Occupancy']);

        $response = $this->getJson("/api/v1/occupancy-rates/{$item->id}");

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'code' => 'LOW',
                        'name' => 'Low Occupancy',
                    ]
                ]);
    }

    public function test_returns_404_for_nonexistent_occupancy_rate()
    {
        $response = $this->getJson('/api/v1/occupancy-rates/999');

        $response->assertStatus(404)
                ->assertJson([
                    'success' => false,
                ]);
    }

    public function test_only_returns_active_occupancy_rates()
    {
        $item1 = OccupancyRate::create(['code' => 'LOW1', 'status_id' => 1]);
        $item2 = OccupancyRate::create(['code' => 'LOW2', 'status_id' => 2]);

        OccupancyRateTranslation::create(['occupancy_rate_id' => $item1->id, 'locale' => 'en', 'name' => 'Low Occupancy 1']);
        OccupancyRateTranslation::create(['occupancy_rate_id' => $item2->id, 'locale' => 'en', 'name' => 'Low Occupancy 2']);

        $response = $this->getJson('/api/v1/occupancy-rates');

        $response->assertStatus(200)
                ->assertJsonCount(1, 'data')
                ->assertJson([
                    'data' => [
                        ['code' => 'LOW1']
                    ]
                ]);
    }

    public function test_respects_accept_language_header()
    {
        $item = OccupancyRate::create(['code' => 'LOW', 'status_id' => 1]);
        OccupancyRateTranslation::create(['occupancy_rate_id' => $item->id, 'locale' => 'en', 'name' => 'Low Occupancy']);
        OccupancyRateTranslation::create(['occupancy_rate_id' => $item->id, 'locale' => 'sl', 'name' => 'Nizka zasedenost']);

        $response = $this->getJson('/api/v1/occupancy-rates', ['Accept-Language' => 'sl']);

        $response->assertStatus(200)
                ->assertJson([
                    'data' => [
                        ['name' => 'Nizka zasedenost']
                    ]
                ]);
    }

    public function test_cache_headers_are_present()
    {
        \Illuminate\Support\Facades\Cache::flush();

        $item = OccupancyRate::create(['code' => 'LOW', 'status_id' => 1]);
        OccupancyRateTranslation::create(['occupancy_rate_id' => $item->id, 'locale' => 'en', 'name' => 'Low Occupancy']);

        $response = $this->getJson('/api/v1/occupancy-rates', ['Accept-Language' => 'en']);

        $response->assertStatus(200)
                ->assertHeader('X-Cache-Status')
                ->assertHeader('X-Cache-Key')
                ->assertHeader('X-Locale', 'en');
    }
}
