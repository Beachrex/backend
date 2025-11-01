<?php

namespace Tests\Feature;

use App\Models\BayLength;
use App\Models\BayLengthTranslation;
use App\Models\Status;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BayLengthApiTest extends TestCase
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

    public function test_can_get_all_bay_lengths()
    {
        $item = BayLength::create([
            'code' => 'WIDE',
            'status_id' => 1,
        ]);

        BayLengthTranslation::create([
            'bay_length_id' => $item->id,
            'locale' => 'en',
            'name' => 'Wide',
        ]);

        $response = $this->getJson('/api/v1/bay-lengths');

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
                            'code' => 'WIDE',
                            'name' => 'Wide',
                        ]
                    ]
                ]);
    }

    public function test_can_get_specific_bay_length()
    {
        $item = BayLength::create(['code' => 'WIDE', 'status_id' => 1]);
        BayLengthTranslation::create(['bay_length_id' => $item->id, 'locale' => 'en', 'name' => 'Wide']);

        $response = $this->getJson("/api/v1/bay-lengths/{$item->id}");

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'code' => 'WIDE',
                        'name' => 'Wide',
                    ]
                ]);
    }

    public function test_returns_404_for_nonexistent_bay_length()
    {
        $response = $this->getJson('/api/v1/bay-lengths/999');

        $response->assertStatus(404)
                ->assertJson([
                    'success' => false,
                ]);
    }

    public function test_only_returns_active_bay_lengths()
    {
        $item1 = BayLength::create(['code' => 'WIDE1', 'status_id' => 1]);
        $item2 = BayLength::create(['code' => 'WIDE2', 'status_id' => 2]);

        BayLengthTranslation::create(['bay_length_id' => $item1->id, 'locale' => 'en', 'name' => 'Wide 1']);
        BayLengthTranslation::create(['bay_length_id' => $item2->id, 'locale' => 'en', 'name' => 'Wide 2']);

        $response = $this->getJson('/api/v1/bay-lengths');

        $response->assertStatus(200)
                ->assertJsonCount(1, 'data')
                ->assertJson([
                    'data' => [
                        ['code' => 'WIDE1']
                    ]
                ]);
    }

    public function test_respects_accept_language_header()
    {
        $item = BayLength::create(['code' => 'WIDE', 'status_id' => 1]);
        BayLengthTranslation::create(['bay_length_id' => $item->id, 'locale' => 'en', 'name' => 'Wide']);
        BayLengthTranslation::create(['bay_length_id' => $item->id, 'locale' => 'sl', 'name' => 'Široko']);

        $response = $this->getJson('/api/v1/bay-lengths', ['Accept-Language' => 'sl']);

        $response->assertStatus(200)
                ->assertJson([
                    'data' => [
                        ['name' => 'Široko']
                    ]
                ]);
    }

    public function test_cache_headers_are_present()
    {
        \Illuminate\Support\Facades\Cache::flush();

        $item = BayLength::create(['code' => 'WIDE', 'status_id' => 1]);
        BayLengthTranslation::create(['bay_length_id' => $item->id, 'locale' => 'en', 'name' => 'Wide']);

        $response = $this->getJson('/api/v1/bay-lengths', ['Accept-Language' => 'en']);

        $response->assertStatus(200)
                ->assertHeader('X-Cache-Status')
                ->assertHeader('X-Cache-Key')
                ->assertHeader('X-Locale', 'en');
    }
}
