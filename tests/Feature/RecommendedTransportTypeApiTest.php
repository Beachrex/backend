<?php

namespace Tests\Feature;

use App\Models\RecommendedTransportType;
use App\Models\RecommendedTransportTypeTranslation;
use App\Models\Status;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecommendedTransportTypeApiTest extends TestCase
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

    public function test_can_get_all_recommended_transport_types()
    {
        $item = RecommendedTransportType::create([
            'code' => 'CAR',
            'status_id' => 1,
        ]);

        RecommendedTransportTypeTranslation::create([
            'recommended_transport_type_id' => $item->id,
            'locale' => 'en',
            'name' => 'Car',
        ]);

        $response = $this->getJson('/api/v1/recommended-transport-types');

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
                            'code' => 'CAR',
                            'name' => 'Car',
                        ]
                    ]
                ]);
    }

    public function test_can_get_specific_recommended_transport_type()
    {
        $item = RecommendedTransportType::create(['code' => 'CAR', 'status_id' => 1]);
        RecommendedTransportTypeTranslation::create(['recommended_transport_type_id' => $item->id, 'locale' => 'en', 'name' => 'Car']);

        $response = $this->getJson("/api/v1/recommended-transport-types/{$item->id}");

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'code' => 'CAR',
                        'name' => 'Car',
                    ]
                ]);
    }

    public function test_returns_404_for_nonexistent_recommended_transport_type()
    {
        $response = $this->getJson('/api/v1/recommended-transport-types/999');

        $response->assertStatus(404)
                ->assertJson([
                    'success' => false,
                ]);
    }

    public function test_only_returns_active_recommended_transport_types()
    {
        $item1 = RecommendedTransportType::create(['code' => 'CAR1', 'status_id' => 1]);
        $item2 = RecommendedTransportType::create(['code' => 'CAR2', 'status_id' => 2]);

        RecommendedTransportTypeTranslation::create(['recommended_transport_type_id' => $item1->id, 'locale' => 'en', 'name' => 'Car 1']);
        RecommendedTransportTypeTranslation::create(['recommended_transport_type_id' => $item2->id, 'locale' => 'en', 'name' => 'Car 2']);

        $response = $this->getJson('/api/v1/recommended-transport-types');

        $response->assertStatus(200)
                ->assertJsonCount(1, 'data')
                ->assertJson([
                    'data' => [
                        ['code' => 'CAR1']
                    ]
                ]);
    }

    public function test_respects_accept_language_header()
    {
        $item = RecommendedTransportType::create(['code' => 'CAR', 'status_id' => 1]);
        RecommendedTransportTypeTranslation::create(['recommended_transport_type_id' => $item->id, 'locale' => 'en', 'name' => 'Car']);
        RecommendedTransportTypeTranslation::create(['recommended_transport_type_id' => $item->id, 'locale' => 'sl', 'name' => 'Avto']);

        $response = $this->getJson('/api/v1/recommended-transport-types', ['Accept-Language' => 'sl']);

        $response->assertStatus(200)
                ->assertJson([
                    'data' => [
                        ['name' => 'Avto']
                    ]
                ]);
    }

    public function test_cache_headers_are_present()
    {
        \Illuminate\Support\Facades\Cache::flush();

        $item = RecommendedTransportType::create(['code' => 'CAR', 'status_id' => 1]);
        RecommendedTransportTypeTranslation::create(['recommended_transport_type_id' => $item->id, 'locale' => 'en', 'name' => 'Car']);

        $response = $this->getJson('/api/v1/recommended-transport-types', ['Accept-Language' => 'en']);

        $response->assertStatus(200)
                ->assertHeader('X-Cache-Status')
                ->assertHeader('X-Cache-Key')
                ->assertHeader('X-Locale', 'en');
    }
}
