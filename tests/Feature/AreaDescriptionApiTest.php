<?php

namespace Tests\Feature;

use App\Models\AreaDescription;
use App\Models\AreaDescriptionTranslation;
use App\Models\Status;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AreaDescriptionApiTest extends TestCase
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

    public function test_can_get_all_area_descriptions()
    {
        $item = AreaDescription::create([
            'code' => 'URBAN',
            'status_id' => 1,
        ]);

        AreaDescriptionTranslation::create([
            'area_description_id' => $item->id,
            'locale' => 'en',
            'name' => 'Urban Area',
        ]);

        $response = $this->getJson('/api/v1/area-descriptions');

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
                            'code' => 'URBAN',
                            'name' => 'Urban Area',
                        ]
                    ]
                ]);
    }

    public function test_can_get_specific_area_description()
    {
        $item = AreaDescription::create(['code' => 'URBAN', 'status_id' => 1]);
        AreaDescriptionTranslation::create(['area_description_id' => $item->id, 'locale' => 'en', 'name' => 'Urban Area']);

        $response = $this->getJson("/api/v1/area-descriptions/{$item->id}");

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'code' => 'URBAN',
                        'name' => 'Urban Area',
                    ]
                ]);
    }

    public function test_returns_404_for_nonexistent_area_description()
    {
        $response = $this->getJson('/api/v1/area-descriptions/999');

        $response->assertStatus(404)
                ->assertJson([
                    'success' => false,
                ]);
    }

    public function test_only_returns_active_area_descriptions()
    {
        $item1 = AreaDescription::create(['code' => 'URBAN1', 'status_id' => 1]);
        $item2 = AreaDescription::create(['code' => 'URBAN2', 'status_id' => 2]);

        AreaDescriptionTranslation::create(['area_description_id' => $item1->id, 'locale' => 'en', 'name' => 'Urban Area 1']);
        AreaDescriptionTranslation::create(['area_description_id' => $item2->id, 'locale' => 'en', 'name' => 'Urban Area 2']);

        $response = $this->getJson('/api/v1/area-descriptions');

        $response->assertStatus(200)
                ->assertJsonCount(1, 'data')
                ->assertJson([
                    'data' => [
                        ['code' => 'URBAN1']
                    ]
                ]);
    }

    public function test_respects_accept_language_header()
    {
        $item = AreaDescription::create(['code' => 'URBAN', 'status_id' => 1]);
        AreaDescriptionTranslation::create(['area_description_id' => $item->id, 'locale' => 'en', 'name' => 'Urban Area']);
        AreaDescriptionTranslation::create(['area_description_id' => $item->id, 'locale' => 'sl', 'name' => 'Urbano območje']);

        $response = $this->getJson('/api/v1/area-descriptions', ['Accept-Language' => 'sl']);

        $response->assertStatus(200)
                ->assertJson([
                    'data' => [
                        ['name' => 'Urbano območje']
                    ]
                ]);
    }

    public function test_cache_headers_are_present()
    {
        \Illuminate\Support\Facades\Cache::flush();

        $item = AreaDescription::create(['code' => 'URBAN', 'status_id' => 1]);
        AreaDescriptionTranslation::create(['area_description_id' => $item->id, 'locale' => 'en', 'name' => 'Urban Area']);

        $response = $this->getJson('/api/v1/area-descriptions', ['Accept-Language' => 'en']);

        $response->assertStatus(200)
                ->assertHeader('X-Cache-Status')
                ->assertHeader('X-Cache-Key')
                ->assertHeader('X-Locale', 'en');
    }
}
