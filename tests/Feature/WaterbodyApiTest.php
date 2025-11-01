<?php

namespace Tests\Feature;

use App\Models\Waterbody;
use App\Models\WaterbodyTranslation;
use App\Models\Status;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WaterbodyApiTest extends TestCase
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

    public function test_can_get_all_waterbodies()
    {
        $waterbody = Waterbody::create([
            'code' => 'SEA',
            'status_id' => 1,
            'show_filter' => true,
        ]);

        WaterbodyTranslation::create([
            'waterbody_id' => $waterbody->id,
            'locale' => 'en',
            'name' => 'Sea',
        ]);

        $response = $this->getJson('/api/v1/waterbodies');

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
                            'code' => 'SEA',
                            'name' => 'Sea',
                        ]
                    ]
                ]);
    }

    public function test_can_filter_waterbodies_by_show_filter()
    {
        $waterbody1 = Waterbody::create(['code' => 'SEA', 'status_id' => 1, 'show_filter' => true]);
        $waterbody2 = Waterbody::create(['code' => 'LAKE', 'status_id' => 1, 'show_filter' => false]);

        WaterbodyTranslation::create(['waterbody_id' => $waterbody1->id, 'locale' => 'en', 'name' => 'Sea']);
        WaterbodyTranslation::create(['waterbody_id' => $waterbody2->id, 'locale' => 'en', 'name' => 'Lake']);

        $response = $this->getJson('/api/v1/waterbodies?filter=true');

        $response->assertStatus(200)
                ->assertJsonCount(1, 'data')
                ->assertJson([
                    'data' => [
                        ['code' => 'SEA']
                    ]
                ]);
    }

    public function test_can_get_specific_waterbody()
    {
        $waterbody = Waterbody::create(['code' => 'SEA', 'status_id' => 1, 'show_filter' => true]);
        WaterbodyTranslation::create(['waterbody_id' => $waterbody->id, 'locale' => 'en', 'name' => 'Sea']);

        $response = $this->getJson("/api/v1/waterbodies/{$waterbody->id}");

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'code' => 'SEA',
                        'name' => 'Sea',
                    ]
                ]);
    }

    public function test_returns_404_for_nonexistent_waterbody()
    {
        $response = $this->getJson('/api/v1/waterbodies/999');

        $response->assertStatus(404)
                ->assertJson([
                    'success' => false,
                    'message' => 'Waterbody not found'
                ]);
    }

    public function test_only_returns_active_waterbodies()
    {
        $waterbody1 = Waterbody::create(['code' => 'SEA', 'status_id' => 1, 'show_filter' => true]);
        $waterbody2 = Waterbody::create(['code' => 'LAKE', 'status_id' => 2, 'show_filter' => true]);

        WaterbodyTranslation::create(['waterbody_id' => $waterbody1->id, 'locale' => 'en', 'name' => 'Sea']);
        WaterbodyTranslation::create(['waterbody_id' => $waterbody2->id, 'locale' => 'en', 'name' => 'Lake']);

        $response = $this->getJson('/api/v1/waterbodies');

        $response->assertStatus(200)
                ->assertJsonCount(1, 'data')
                ->assertJson([
                    'data' => [
                        ['code' => 'SEA']
                    ]
                ]);
    }

    public function test_respects_accept_language_header()
    {
        $waterbody = Waterbody::create(['code' => 'SEA', 'status_id' => 1, 'show_filter' => true]);
        WaterbodyTranslation::create(['waterbody_id' => $waterbody->id, 'locale' => 'en', 'name' => 'Sea']);
        WaterbodyTranslation::create(['waterbody_id' => $waterbody->id, 'locale' => 'sl', 'name' => 'Morje']);

        $response = $this->getJson('/api/v1/waterbodies', ['Accept-Language' => 'sl']);

        $response->assertStatus(200)
                ->assertJson([
                    'data' => [
                        ['name' => 'Morje']
                    ]
                ]);
    }

    public function test_cache_headers_are_present_on_first_request()
    {
        // Clear cache to ensure MISS
        \Illuminate\Support\Facades\Cache::flush();

        $waterbody = Waterbody::create(['code' => 'SEA', 'status_id' => 1, 'show_filter' => true]);
        WaterbodyTranslation::create(['waterbody_id' => $waterbody->id, 'locale' => 'en', 'name' => 'Sea']);

        $response = $this->getJson('/api/v1/waterbodies', ['Accept-Language' => 'en']);

        $response->assertStatus(200)
                ->assertHeader('X-Cache-Status', 'MISS')
                ->assertHeader('X-Cache-Key', 'waterbodies:index:en:all')
                ->assertHeader('X-Locale', 'en');

        // Verify X-Response-Time header exists and is in correct format
        $this->assertTrue($response->headers->has('X-Response-Time'));
        $this->assertMatchesRegularExpression('/^\d+(\.\d+)?ms$/', $response->headers->get('X-Response-Time'));
    }

    public function test_cache_headers_show_hit_on_second_request()
    {
        // Clear cache first
        \Illuminate\Support\Facades\Cache::flush();

        $waterbody = Waterbody::create(['code' => 'SEA', 'status_id' => 1, 'show_filter' => true]);
        WaterbodyTranslation::create(['waterbody_id' => $waterbody->id, 'locale' => 'en', 'name' => 'Sea']);

        // First request - should be MISS
        $this->getJson('/api/v1/waterbodies', ['Accept-Language' => 'en']);

        // Second request - should be HIT
        $response = $this->getJson('/api/v1/waterbodies', ['Accept-Language' => 'en']);

        $response->assertStatus(200)
                ->assertHeader('X-Cache-Status', 'HIT')
                ->assertHeader('X-Cache-Key', 'waterbodies:index:en:all')
                ->assertHeader('X-Locale', 'en');
    }

    public function test_cache_keys_are_locale_aware()
    {
        // Clear cache first
        \Illuminate\Support\Facades\Cache::flush();

        $waterbody = Waterbody::create(['code' => 'SEA', 'status_id' => 1, 'show_filter' => true]);
        WaterbodyTranslation::create(['waterbody_id' => $waterbody->id, 'locale' => 'en', 'name' => 'Sea']);
        WaterbodyTranslation::create(['waterbody_id' => $waterbody->id, 'locale' => 'sl', 'name' => 'Morje']);

        // Request in English
        $responseEn = $this->getJson('/api/v1/waterbodies', ['Accept-Language' => 'en']);
        $responseEn->assertHeader('X-Cache-Status', 'MISS')
                   ->assertHeader('X-Cache-Key', 'waterbodies:index:en:all')
                   ->assertHeader('X-Locale', 'en');

        // Request in Slovenian - should also be MISS (different cache key)
        $responseSl = $this->getJson('/api/v1/waterbodies', ['Accept-Language' => 'sl']);
        $responseSl->assertHeader('X-Cache-Status', 'MISS')
                   ->assertHeader('X-Cache-Key', 'waterbodies:index:sl:all')
                   ->assertHeader('X-Locale', 'sl');

        // Second request in English - should be HIT
        $responseEn2 = $this->getJson('/api/v1/waterbodies', ['Accept-Language' => 'en']);
        $responseEn2->assertHeader('X-Cache-Status', 'HIT')
                    ->assertHeader('X-Cache-Key', 'waterbodies:index:en:all');
    }

    public function test_show_endpoint_has_cache_headers()
    {
        // Clear cache first
        \Illuminate\Support\Facades\Cache::flush();

        $waterbody = Waterbody::create(['code' => 'SEA', 'status_id' => 1, 'show_filter' => true]);
        WaterbodyTranslation::create(['waterbody_id' => $waterbody->id, 'locale' => 'en', 'name' => 'Sea']);

        // First request - MISS
        $response1 = $this->getJson("/api/v1/waterbodies/{$waterbody->id}", ['Accept-Language' => 'en']);
        $response1->assertStatus(200)
                  ->assertHeader('X-Cache-Status', 'MISS')
                  ->assertHeader('X-Cache-Key', "waterbody:show:en:{$waterbody->id}")
                  ->assertHeader('X-Locale', 'en');

        // Second request - HIT
        $response2 = $this->getJson("/api/v1/waterbodies/{$waterbody->id}", ['Accept-Language' => 'en']);
        $response2->assertStatus(200)
                  ->assertHeader('X-Cache-Status', 'HIT')
                  ->assertHeader('X-Cache-Key', "waterbody:show:en:{$waterbody->id}");
    }
}

