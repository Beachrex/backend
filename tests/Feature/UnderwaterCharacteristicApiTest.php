<?php

namespace Tests\Feature;

use App\Models\UnderwaterCharacteristic;
use App\Models\UnderwaterCharacteristicTranslation;
use App\Models\Status;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UnderwaterCharacteristicApiTest extends TestCase
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

    public function test_can_get_all_underwater_characteristics()
    {
        $item = UnderwaterCharacteristic::create([
            'code' => 'ROCKY',
            'status_id' => 1,
        ]);

        UnderwaterCharacteristicTranslation::create([
            'underwater_characteristic_id' => $item->id,
            'locale' => 'en',
            'name' => 'Rocky Bottom',
        ]);

        $response = $this->getJson('/api/v1/underwater-characteristics');

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
                            'code' => 'ROCKY',
                            'name' => 'Rocky Bottom',
                        ]
                    ]
                ]);
    }

    public function test_can_get_specific_underwater_characteristic()
    {
        $item = UnderwaterCharacteristic::create(['code' => 'ROCKY', 'status_id' => 1]);
        UnderwaterCharacteristicTranslation::create(['underwater_characteristic_id' => $item->id, 'locale' => 'en', 'name' => 'Rocky Bottom']);

        $response = $this->getJson("/api/v1/underwater-characteristics/{$item->id}");

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'code' => 'ROCKY',
                        'name' => 'Rocky Bottom',
                    ]
                ]);
    }

    public function test_returns_404_for_nonexistent_underwater_characteristic()
    {
        $response = $this->getJson('/api/v1/underwater-characteristics/999');

        $response->assertStatus(404)
                ->assertJson([
                    'success' => false,
                ]);
    }

    public function test_only_returns_active_underwater_characteristics()
    {
        $item1 = UnderwaterCharacteristic::create(['code' => 'ROCKY1', 'status_id' => 1]);
        $item2 = UnderwaterCharacteristic::create(['code' => 'ROCKY2', 'status_id' => 2]);

        UnderwaterCharacteristicTranslation::create(['underwater_characteristic_id' => $item1->id, 'locale' => 'en', 'name' => 'Rocky Bottom 1']);
        UnderwaterCharacteristicTranslation::create(['underwater_characteristic_id' => $item2->id, 'locale' => 'en', 'name' => 'Rocky Bottom 2']);

        $response = $this->getJson('/api/v1/underwater-characteristics');

        $response->assertStatus(200)
                ->assertJsonCount(1, 'data')
                ->assertJson([
                    'data' => [
                        ['code' => 'ROCKY1']
                    ]
                ]);
    }

    public function test_respects_accept_language_header()
    {
        $item = UnderwaterCharacteristic::create(['code' => 'ROCKY', 'status_id' => 1]);
        UnderwaterCharacteristicTranslation::create(['underwater_characteristic_id' => $item->id, 'locale' => 'en', 'name' => 'Rocky Bottom']);
        UnderwaterCharacteristicTranslation::create(['underwater_characteristic_id' => $item->id, 'locale' => 'sl', 'name' => 'Kamnito dno']);

        $response = $this->getJson('/api/v1/underwater-characteristics', ['Accept-Language' => 'sl']);

        $response->assertStatus(200)
                ->assertJson([
                    'data' => [
                        ['name' => 'Kamnito dno']
                    ]
                ]);
    }

    public function test_cache_headers_are_present()
    {
        \Illuminate\Support\Facades\Cache::flush();

        $item = UnderwaterCharacteristic::create(['code' => 'ROCKY', 'status_id' => 1]);
        UnderwaterCharacteristicTranslation::create(['underwater_characteristic_id' => $item->id, 'locale' => 'en', 'name' => 'Rocky Bottom']);

        $response = $this->getJson('/api/v1/underwater-characteristics', ['Accept-Language' => 'en']);

        $response->assertStatus(200)
                ->assertHeader('X-Cache-Status')
                ->assertHeader('X-Cache-Key')
                ->assertHeader('X-Locale', 'en');
    }
}
