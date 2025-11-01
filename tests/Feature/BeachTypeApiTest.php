<?php

namespace Tests\Feature;

use App\Models\BeachType;
use App\Models\BeachTypeTranslation;
use App\Models\Status;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BeachTypeApiTest extends TestCase
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

    public function test_can_get_all_beach_types()
    {
        $item = BeachType::create([
            'code' => 'SAND',
            'status_id' => 1, 'show_filter' => true,
        ]);

        BeachTypeTranslation::create([
            'beach_type_id' => $item->id,
            'locale' => 'en',
            'name' => 'Sandy Beach',
        ]);

        $response = $this->getJson('/api/v1/beach-types');

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
                            'code' => 'SAND',
                            'name' => 'Sandy Beach',
                        ]
                    ]
                ]);
    }

    public function test_can_get_specific_beach_type()
    {
        $item = BeachType::create(['code' => 'SAND', 'status_id' => 1, 'show_filter' => true]);
        BeachTypeTranslation::create(['beach_type_id' => $item->id, 'locale' => 'en', 'name' => 'Sandy Beach']);

        $response = $this->getJson("/api/v1/beach-types/{$item->id}");

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'code' => 'SAND',
                        'name' => 'Sandy Beach',
                    ]
                ]);
    }

    public function test_returns_404_for_nonexistent_beach_type()
    {
        $response = $this->getJson('/api/v1/beach-types/999');

        $response->assertStatus(404)
                ->assertJson([
                    'success' => false,
                ]);
    }

    public function test_only_returns_active_beach_types()
    {
        $item1 = BeachType::create(['code' => 'SAND1', 'status_id' => 1, 'show_filter' => true]);
        $item2 = BeachType::create(['code' => 'SAND2', 'status_id' => 2, 'show_filter' => true]);

        BeachTypeTranslation::create(['beach_type_id' => $item1->id, 'locale' => 'en', 'name' => 'Sandy Beach 1']);
        BeachTypeTranslation::create(['beach_type_id' => $item2->id, 'locale' => 'en', 'name' => 'Sandy Beach 2']);

        $response = $this->getJson('/api/v1/beach-types');

        $response->assertStatus(200)
                ->assertJsonCount(1, 'data')
                ->assertJson([
                    'data' => [
                        ['code' => 'SAND1']
                    ]
                ]);
    }

    public function test_respects_accept_language_header()
    {
        $item = BeachType::create(['code' => 'SAND', 'status_id' => 1, 'show_filter' => true]);
        BeachTypeTranslation::create(['beach_type_id' => $item->id, 'locale' => 'en', 'name' => 'Sandy Beach']);
        BeachTypeTranslation::create(['beach_type_id' => $item->id, 'locale' => 'sl', 'name' => 'Peščena plaža']);

        $response = $this->getJson('/api/v1/beach-types', ['Accept-Language' => 'sl']);

        $response->assertStatus(200)
                ->assertJson([
                    'data' => [
                        ['name' => 'Peščena plaža']
                    ]
                ]);
    }

    public function test_cache_headers_are_present()
    {
        \Illuminate\Support\Facades\Cache::flush();

        $item = BeachType::create(['code' => 'SAND', 'status_id' => 1, 'show_filter' => true]);
        BeachTypeTranslation::create(['beach_type_id' => $item->id, 'locale' => 'en', 'name' => 'Sandy Beach']);

        $response = $this->getJson('/api/v1/beach-types', ['Accept-Language' => 'en']);

        $response->assertStatus(200)
                ->assertHeader('X-Cache-Status')
                ->assertHeader('X-Cache-Key')
                ->assertHeader('X-Locale', 'en');
    }
}
