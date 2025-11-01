<?php

namespace Tests\Feature;

use App\Models\LocationLength;
use App\Models\LocationLengthTranslation;
use App\Models\Status;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocationLengthApiTest extends TestCase
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

    public function test_can_get_all_location_lengths()
    {
        $item = LocationLength::create([
            'code' => '5m',
            'status_id' => 1,
        ]);

        LocationLengthTranslation::create([
            'location_length_id' => $item->id,
            'locale' => 'en',
            'name' => '5m',
            'slug' => '5m',
        ]);

        $response = $this->getJson('/api/v1/location-lengths');

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
                            'code' => '5m',
                            'name' => '5m',
                        ]
                    ]
                ]);
    }

    public function test_can_get_specific_location_length()
    {
        $item = LocationLength::create(['code' => '10m', 'status_id' => 1]);
        LocationLengthTranslation::create([
            'location_length_id' => $item->id,
            'locale' => 'en',
            'name' => '10m',
            'slug' => '10m',
        ]);

        $response = $this->getJson("/api/v1/location-lengths/{$item->id}");

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'code' => '10m',
                        'name' => '10m',
                    ]
                ]);
    }

    public function test_returns_404_for_nonexistent_location_length()
    {
        $response = $this->getJson('/api/v1/location-lengths/999');

        $response->assertStatus(404)
                ->assertJson([
                    'success' => false,
                ]);
    }

    public function test_only_returns_active_location_lengths()
    {
        $item1 = LocationLength::create(['code' => '20m', 'status_id' => 1]);
        $item2 = LocationLength::create(['code' => '30m', 'status_id' => 2]);

        LocationLengthTranslation::create([
            'location_length_id' => $item1->id,
            'locale' => 'en',
            'name' => '20m',
            'slug' => '20m',
        ]);
        LocationLengthTranslation::create([
            'location_length_id' => $item2->id,
            'locale' => 'en',
            'name' => '30m',
            'slug' => '30m',
        ]);

        $response = $this->getJson('/api/v1/location-lengths');

        $response->assertStatus(200)
                ->assertJsonCount(1, 'data')
                ->assertJson([
                    'data' => [
                        ['code' => '20m']
                    ]
                ]);
    }

    public function test_returns_localized_translation()
    {
        $item = LocationLength::create(['code' => 'more_km', 'status_id' => 1]);

        LocationLengthTranslation::create([
            'location_length_id' => $item->id,
            'locale' => 'en',
            'name' => '>1km',
            'slug' => 'more-km',
        ]);

        LocationLengthTranslation::create([
            'location_length_id' => $item->id,
            'locale' => 'sl',
            'name' => '>1km',
            'slug' => 'vec-km',
        ]);

        // Test English
        $response = $this->getJson('/api/v1/location-lengths', [
            'Accept-Language' => 'en'
        ]);

        $response->assertStatus(200)
                ->assertJson([
                    'data' => [
                        [
                            'code' => 'more_km',
                            'name' => '>1km',
                            'slug' => 'more-km',
                        ]
                    ]
                ]);

        // Test Slovenian
        $response = $this->getJson('/api/v1/location-lengths', [
            'Accept-Language' => 'sl'
        ]);

        $response->assertStatus(200)
                ->assertJson([
                    'data' => [
                        [
                            'code' => 'more_km',
                            'name' => '>1km',
                            'slug' => 'vec-km',
                        ]
                    ]
                ]);
    }

    public function test_includes_translations_when_requested()
    {
        $item = LocationLength::create(['code' => '50m', 'status_id' => 1]);

        LocationLengthTranslation::create([
            'location_length_id' => $item->id,
            'locale' => 'en',
            'name' => '50m',
            'slug' => '50m',
        ]);

        LocationLengthTranslation::create([
            'location_length_id' => $item->id,
            'locale' => 'sl',
            'name' => '50m',
            'slug' => '50m',
        ]);

        $response = $this->getJson("/api/v1/location-lengths/{$item->id}");

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'data' => [
                        'id',
                        'code',
                        'name',
                        'slug',
                        'beaches_count',
                        'translations' => [
                            '*' => [
                                'locale',
                                'name',
                                'slug',
                            ]
                        ]
                    ]
                ])
                ->assertJsonCount(2, 'data.translations');
    }

    public function test_fallback_to_english_when_translation_not_found()
    {
        $item = LocationLength::create(['code' => '100m', 'status_id' => 1]);

        LocationLengthTranslation::create([
            'location_length_id' => $item->id,
            'locale' => 'en',
            'name' => '100m',
            'slug' => '100m',
        ]);

        // Request with a locale that doesn't exist
        $response = $this->getJson('/api/v1/location-lengths', [
            'Accept-Language' => 'fr'
        ]);

        $response->assertStatus(200)
                ->assertJson([
                    'data' => [
                        [
                            'code' => '100m',
                            'name' => '100m', // Should fallback to English
                        ]
                    ]
                ]);
    }
}

