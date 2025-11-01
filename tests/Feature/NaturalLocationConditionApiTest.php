<?php

namespace Tests\Feature;

use App\Models\NaturalLocationCondition;
use App\Models\NaturalLocationConditionTranslation;
use App\Models\Status;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NaturalLocationConditionApiTest extends TestCase
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

    public function test_can_get_all_natural_location_conditions()
    {
        $item = NaturalLocationCondition::create([
            'code' => 'SUNNY',
            'status_id' => 1,
        ]);

        NaturalLocationConditionTranslation::create([
            'natural_location_condition_id' => $item->id,
            'locale' => 'en',
            'name' => 'Sunny',
        ]);

        $response = $this->getJson('/api/v1/natural-location-conditions');

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
                            'code' => 'SUNNY',
                            'name' => 'Sunny',
                        ]
                    ]
                ]);
    }

    public function test_can_get_specific_natural_location_condition()
    {
        $item = NaturalLocationCondition::create(['code' => 'SUNNY', 'status_id' => 1]);
        NaturalLocationConditionTranslation::create(['natural_location_condition_id' => $item->id, 'locale' => 'en', 'name' => 'Sunny']);

        $response = $this->getJson("/api/v1/natural-location-conditions/{$item->id}");

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'code' => 'SUNNY',
                        'name' => 'Sunny',
                    ]
                ]);
    }

    public function test_returns_404_for_nonexistent_natural_location_condition()
    {
        $response = $this->getJson('/api/v1/natural-location-conditions/999');

        $response->assertStatus(404)
                ->assertJson([
                    'success' => false,
                ]);
    }

    public function test_only_returns_active_natural_location_conditions()
    {
        $item1 = NaturalLocationCondition::create(['code' => 'SUNNY1', 'status_id' => 1]);
        $item2 = NaturalLocationCondition::create(['code' => 'SUNNY2', 'status_id' => 2]);

        NaturalLocationConditionTranslation::create(['natural_location_condition_id' => $item1->id, 'locale' => 'en', 'name' => 'Sunny 1']);
        NaturalLocationConditionTranslation::create(['natural_location_condition_id' => $item2->id, 'locale' => 'en', 'name' => 'Sunny 2']);

        $response = $this->getJson('/api/v1/natural-location-conditions');

        $response->assertStatus(200)
                ->assertJsonCount(1, 'data')
                ->assertJson([
                    'data' => [
                        ['code' => 'SUNNY1']
                    ]
                ]);
    }

    public function test_respects_accept_language_header()
    {
        $item = NaturalLocationCondition::create(['code' => 'SUNNY', 'status_id' => 1]);
        NaturalLocationConditionTranslation::create(['natural_location_condition_id' => $item->id, 'locale' => 'en', 'name' => 'Sunny']);
        NaturalLocationConditionTranslation::create(['natural_location_condition_id' => $item->id, 'locale' => 'sl', 'name' => 'Sončno']);

        $response = $this->getJson('/api/v1/natural-location-conditions', ['Accept-Language' => 'sl']);

        $response->assertStatus(200)
                ->assertJson([
                    'data' => [
                        ['name' => 'Sončno']
                    ]
                ]);
    }

    public function test_cache_headers_are_present()
    {
        \Illuminate\Support\Facades\Cache::flush();

        $item = NaturalLocationCondition::create(['code' => 'SUNNY', 'status_id' => 1]);
        NaturalLocationConditionTranslation::create(['natural_location_condition_id' => $item->id, 'locale' => 'en', 'name' => 'Sunny']);

        $response = $this->getJson('/api/v1/natural-location-conditions', ['Accept-Language' => 'en']);

        $response->assertStatus(200)
                ->assertHeader('X-Cache-Status')
                ->assertHeader('X-Cache-Key')
                ->assertHeader('X-Locale', 'en');
    }
}
