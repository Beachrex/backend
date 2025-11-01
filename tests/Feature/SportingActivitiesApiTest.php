<?php

namespace Tests\Feature;

use App\Models\SportingActivities;
use App\Models\SportingActivitiesTranslation;
use App\Models\Status;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SportingActivitiesApiTest extends TestCase
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

    public function test_can_get_all_sporting_activities()
    {
        $item = SportingActivities::create([
            'code' => 'SWIM',
            'status_id' => 1,
        ]);

        SportingActivitiesTranslation::create([
            'sporting_activities_id' => $item->id,
            'locale' => 'en',
            'name' => 'Swimming',
        ]);

        $response = $this->getJson('/api/v1/sporting-activities');

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
                            'code' => 'SWIM',
                            'name' => 'Swimming',
                        ]
                    ]
                ]);
    }

    public function test_can_get_specific_sporting_activitie()
    {
        $item = SportingActivities::create(['code' => 'SWIM', 'status_id' => 1]);
        SportingActivitiesTranslation::create(['sporting_activities_id' => $item->id, 'locale' => 'en', 'name' => 'Swimming']);

        $response = $this->getJson("/api/v1/sporting-activities/{$item->id}");

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'code' => 'SWIM',
                        'name' => 'Swimming',
                    ]
                ]);
    }

    public function test_returns_404_for_nonexistent_sporting_activitie()
    {
        $response = $this->getJson('/api/v1/sporting-activities/999');

        $response->assertStatus(404)
                ->assertJson([
                    'success' => false,
                ]);
    }

    public function test_only_returns_active_sporting_activities()
    {
        $item1 = SportingActivities::create(['code' => 'SWIM1', 'status_id' => 1]);
        $item2 = SportingActivities::create(['code' => 'SWIM2', 'status_id' => 2]);

        SportingActivitiesTranslation::create(['sporting_activities_id' => $item1->id, 'locale' => 'en', 'name' => 'Swimming 1']);
        SportingActivitiesTranslation::create(['sporting_activities_id' => $item2->id, 'locale' => 'en', 'name' => 'Swimming 2']);

        $response = $this->getJson('/api/v1/sporting-activities');

        $response->assertStatus(200)
                ->assertJsonCount(1, 'data')
                ->assertJson([
                    'data' => [
                        ['code' => 'SWIM1']
                    ]
                ]);
    }

    public function test_respects_accept_language_header()
    {
        $item = SportingActivities::create(['code' => 'SWIM', 'status_id' => 1]);
        SportingActivitiesTranslation::create(['sporting_activities_id' => $item->id, 'locale' => 'en', 'name' => 'Swimming']);
        SportingActivitiesTranslation::create(['sporting_activities_id' => $item->id, 'locale' => 'sl', 'name' => 'Plavanje']);

        $response = $this->getJson('/api/v1/sporting-activities', ['Accept-Language' => 'sl']);

        $response->assertStatus(200)
                ->assertJson([
                    'data' => [
                        ['name' => 'Plavanje']
                    ]
                ]);
    }

    public function test_cache_headers_are_present()
    {
        \Illuminate\Support\Facades\Cache::flush();

        $item = SportingActivities::create(['code' => 'SWIM', 'status_id' => 1]);
        SportingActivitiesTranslation::create(['sporting_activities_id' => $item->id, 'locale' => 'en', 'name' => 'Swimming']);

        $response = $this->getJson('/api/v1/sporting-activities', ['Accept-Language' => 'en']);

        $response->assertStatus(200)
                ->assertHeader('X-Cache-Status')
                ->assertHeader('X-Cache-Key')
                ->assertHeader('X-Locale', 'en');
    }
}
