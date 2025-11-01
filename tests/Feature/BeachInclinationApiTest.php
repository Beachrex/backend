<?php

namespace Tests\Feature;

use App\Models\BeachInclination;
use App\Models\BeachInclinationTranslation;
use App\Models\Status;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BeachInclinationApiTest extends TestCase
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

    public function test_can_get_all_beach_inclinations()
    {
        $item = BeachInclination::create([
            'code' => 'GENTLE',
            'status_id' => 1,
        ]);

        BeachInclinationTranslation::create([
            'beach_inclination_id' => $item->id,
            'locale' => 'en',
            'name' => 'Gentle Slope',
        ]);

        $response = $this->getJson('/api/v1/beach-inclinations');

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
                            'code' => 'GENTLE',
                            'name' => 'Gentle Slope',
                        ]
                    ]
                ]);
    }

    public function test_can_get_specific_beach_inclination()
    {
        $item = BeachInclination::create(['code' => 'GENTLE', 'status_id' => 1]);
        BeachInclinationTranslation::create(['beach_inclination_id' => $item->id, 'locale' => 'en', 'name' => 'Gentle Slope']);

        $response = $this->getJson("/api/v1/beach-inclinations/{$item->id}");

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'code' => 'GENTLE',
                        'name' => 'Gentle Slope',
                    ]
                ]);
    }

    public function test_returns_404_for_nonexistent_beach_inclination()
    {
        $response = $this->getJson('/api/v1/beach-inclinations/999');

        $response->assertStatus(404)
                ->assertJson([
                    'success' => false,
                ]);
    }

    public function test_only_returns_active_beach_inclinations()
    {
        $item1 = BeachInclination::create(['code' => 'GENTLE1', 'status_id' => 1]);
        $item2 = BeachInclination::create(['code' => 'GENTLE2', 'status_id' => 2]);

        BeachInclinationTranslation::create(['beach_inclination_id' => $item1->id, 'locale' => 'en', 'name' => 'Gentle Slope 1']);
        BeachInclinationTranslation::create(['beach_inclination_id' => $item2->id, 'locale' => 'en', 'name' => 'Gentle Slope 2']);

        $response = $this->getJson('/api/v1/beach-inclinations');

        $response->assertStatus(200)
                ->assertJsonCount(1, 'data')
                ->assertJson([
                    'data' => [
                        ['code' => 'GENTLE1']
                    ]
                ]);
    }

    public function test_respects_accept_language_header()
    {
        $item = BeachInclination::create(['code' => 'GENTLE', 'status_id' => 1]);
        BeachInclinationTranslation::create(['beach_inclination_id' => $item->id, 'locale' => 'en', 'name' => 'Gentle Slope']);
        BeachInclinationTranslation::create(['beach_inclination_id' => $item->id, 'locale' => 'sl', 'name' => 'Rahel naklon']);

        $response = $this->getJson('/api/v1/beach-inclinations', ['Accept-Language' => 'sl']);

        $response->assertStatus(200)
                ->assertJson([
                    'data' => [
                        ['name' => 'Rahel naklon']
                    ]
                ]);
    }

    public function test_cache_headers_are_present()
    {
        \Illuminate\Support\Facades\Cache::flush();

        $item = BeachInclination::create(['code' => 'GENTLE', 'status_id' => 1]);
        BeachInclinationTranslation::create(['beach_inclination_id' => $item->id, 'locale' => 'en', 'name' => 'Gentle Slope']);

        $response = $this->getJson('/api/v1/beach-inclinations', ['Accept-Language' => 'en']);

        $response->assertStatus(200)
                ->assertHeader('X-Cache-Status')
                ->assertHeader('X-Cache-Key')
                ->assertHeader('X-Locale', 'en');
    }
}
