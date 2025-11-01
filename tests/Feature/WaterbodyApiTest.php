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
}

