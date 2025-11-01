<?php

namespace Tests\Feature;

use App\Models\SwimmingDepthOfOneM;
use App\Models\SwimmingDepthOfOneMTranslation;
use App\Models\Status;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SwimmingDepthOfOneMApiTest extends TestCase
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

    public function test_can_get_all_swimming_depths()
    {
        $item = SwimmingDepthOfOneM::create([
            'code' => 'SHALLOW',
            'status_id' => 1,
        ]);

        SwimmingDepthOfOneMTranslation::create([
            'swimming_depth_of_one_m_id' => $item->id,
            'locale' => 'en',
            'name' => 'Shallow',
        ]);

        $response = $this->getJson('/api/v1/swimming-depths');

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
                            'code' => 'SHALLOW',
                            'name' => 'Shallow',
                        ]
                    ]
                ]);
    }

    public function test_can_get_specific_swimming_depth()
    {
        $item = SwimmingDepthOfOneM::create(['code' => 'SHALLOW', 'status_id' => 1]);
        SwimmingDepthOfOneMTranslation::create(['swimming_depth_of_one_m_id' => $item->id, 'locale' => 'en', 'name' => 'Shallow']);

        $response = $this->getJson("/api/v1/swimming-depths/{$item->id}");

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'code' => 'SHALLOW',
                        'name' => 'Shallow',
                    ]
                ]);
    }

    public function test_returns_404_for_nonexistent_swimming_depth()
    {
        $response = $this->getJson('/api/v1/swimming-depths/999');

        $response->assertStatus(404)
                ->assertJson([
                    'success' => false,
                ]);
    }

    public function test_only_returns_active_swimming_depths()
    {
        $item1 = SwimmingDepthOfOneM::create(['code' => 'SHALLOW1', 'status_id' => 1]);
        $item2 = SwimmingDepthOfOneM::create(['code' => 'SHALLOW2', 'status_id' => 2]);

        SwimmingDepthOfOneMTranslation::create(['swimming_depth_of_one_m_id' => $item1->id, 'locale' => 'en', 'name' => 'Shallow 1']);
        SwimmingDepthOfOneMTranslation::create(['swimming_depth_of_one_m_id' => $item2->id, 'locale' => 'en', 'name' => 'Shallow 2']);

        $response = $this->getJson('/api/v1/swimming-depths');

        $response->assertStatus(200)
                ->assertJsonCount(1, 'data')
                ->assertJson([
                    'data' => [
                        ['code' => 'SHALLOW1']
                    ]
                ]);
    }

    public function test_respects_accept_language_header()
    {
        $item = SwimmingDepthOfOneM::create(['code' => 'SHALLOW', 'status_id' => 1]);
        SwimmingDepthOfOneMTranslation::create(['swimming_depth_of_one_m_id' => $item->id, 'locale' => 'en', 'name' => 'Shallow']);
        SwimmingDepthOfOneMTranslation::create(['swimming_depth_of_one_m_id' => $item->id, 'locale' => 'sl', 'name' => 'Plitvo']);

        $response = $this->getJson('/api/v1/swimming-depths', ['Accept-Language' => 'sl']);

        $response->assertStatus(200)
                ->assertJson([
                    'data' => [
                        ['name' => 'Plitvo']
                    ]
                ]);
    }

    public function test_cache_headers_are_present()
    {
        \Illuminate\Support\Facades\Cache::flush();

        $item = SwimmingDepthOfOneM::create(['code' => 'SHALLOW', 'status_id' => 1]);
        SwimmingDepthOfOneMTranslation::create(['swimming_depth_of_one_m_id' => $item->id, 'locale' => 'en', 'name' => 'Shallow']);

        $response = $this->getJson('/api/v1/swimming-depths', ['Accept-Language' => 'en']);

        $response->assertStatus(200)
                ->assertHeader('X-Cache-Status')
                ->assertHeader('X-Cache-Key')
                ->assertHeader('X-Locale', 'en');
    }
}
