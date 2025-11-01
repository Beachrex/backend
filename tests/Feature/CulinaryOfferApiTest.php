<?php

namespace Tests\Feature;

use App\Models\CulinaryOffer;
use App\Models\CulinaryOfferTranslation;
use App\Models\Status;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CulinaryOfferApiTest extends TestCase
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

    public function test_can_get_all_culinary_offers()
    {
        $item = CulinaryOffer::create([
            'code' => 'RESTAURANT',
            'status_id' => 1,
        ]);

        CulinaryOfferTranslation::create([
            'culinary_offer_id' => $item->id,
            'locale' => 'en',
            'name' => 'Restaurant',
        ]);

        $response = $this->getJson('/api/v1/culinary-offers');

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
                            'code' => 'RESTAURANT',
                            'name' => 'Restaurant',
                        ]
                    ]
                ]);
    }

    public function test_can_get_specific_culinary_offer()
    {
        $item = CulinaryOffer::create(['code' => 'RESTAURANT', 'status_id' => 1]);
        CulinaryOfferTranslation::create(['culinary_offer_id' => $item->id, 'locale' => 'en', 'name' => 'Restaurant']);

        $response = $this->getJson("/api/v1/culinary-offers/{$item->id}");

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'code' => 'RESTAURANT',
                        'name' => 'Restaurant',
                    ]
                ]);
    }

    public function test_returns_404_for_nonexistent_culinary_offer()
    {
        $response = $this->getJson('/api/v1/culinary-offers/999');

        $response->assertStatus(404)
                ->assertJson([
                    'success' => false,
                ]);
    }

    public function test_only_returns_active_culinary_offers()
    {
        $item1 = CulinaryOffer::create(['code' => 'RESTAURANT1', 'status_id' => 1]);
        $item2 = CulinaryOffer::create(['code' => 'RESTAURANT2', 'status_id' => 2]);

        CulinaryOfferTranslation::create(['culinary_offer_id' => $item1->id, 'locale' => 'en', 'name' => 'Restaurant 1']);
        CulinaryOfferTranslation::create(['culinary_offer_id' => $item2->id, 'locale' => 'en', 'name' => 'Restaurant 2']);

        $response = $this->getJson('/api/v1/culinary-offers');

        $response->assertStatus(200)
                ->assertJsonCount(1, 'data')
                ->assertJson([
                    'data' => [
                        ['code' => 'RESTAURANT1']
                    ]
                ]);
    }

    public function test_respects_accept_language_header()
    {
        $item = CulinaryOffer::create(['code' => 'RESTAURANT', 'status_id' => 1]);
        CulinaryOfferTranslation::create(['culinary_offer_id' => $item->id, 'locale' => 'en', 'name' => 'Restaurant']);
        CulinaryOfferTranslation::create(['culinary_offer_id' => $item->id, 'locale' => 'sl', 'name' => 'Restavracija']);

        $response = $this->getJson('/api/v1/culinary-offers', ['Accept-Language' => 'sl']);

        $response->assertStatus(200)
                ->assertJson([
                    'data' => [
                        ['name' => 'Restavracija']
                    ]
                ]);
    }

    public function test_cache_headers_are_present()
    {
        \Illuminate\Support\Facades\Cache::flush();

        $item = CulinaryOffer::create(['code' => 'RESTAURANT', 'status_id' => 1]);
        CulinaryOfferTranslation::create(['culinary_offer_id' => $item->id, 'locale' => 'en', 'name' => 'Restaurant']);

        $response = $this->getJson('/api/v1/culinary-offers', ['Accept-Language' => 'en']);

        $response->assertStatus(200)
                ->assertHeader('X-Cache-Status')
                ->assertHeader('X-Cache-Key')
                ->assertHeader('X-Locale', 'en');
    }
}
