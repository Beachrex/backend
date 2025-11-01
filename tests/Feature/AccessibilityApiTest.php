<?php

namespace Tests\Feature;

use App\Models\Accessibility;
use App\Models\AccessibilityTranslation;
use App\Models\Status;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccessibilityApiTest extends TestCase
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

    public function test_can_get_all_accessibilities()
    {
        $item = Accessibility::create([
            'code' => 'EASY',
            'status_id' => 1,
        ]);

        AccessibilityTranslation::create([
            'accessibility_id' => $item->id,
            'locale' => 'en',
            'name' => 'Easy Access',
        ]);

        $response = $this->getJson('/api/v1/accessibilities');

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
                            'code' => 'EASY',
                            'name' => 'Easy Access',
                        ]
                    ]
                ]);
    }

    public function test_can_get_specific_accessibilitie()
    {
        $item = Accessibility::create(['code' => 'EASY', 'status_id' => 1]);
        AccessibilityTranslation::create(['accessibility_id' => $item->id, 'locale' => 'en', 'name' => 'Easy Access']);

        $response = $this->getJson("/api/v1/accessibilities/{$item->id}");

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'code' => 'EASY',
                        'name' => 'Easy Access',
                    ]
                ]);
    }

    public function test_returns_404_for_nonexistent_accessibilitie()
    {
        $response = $this->getJson('/api/v1/accessibilities/999');

        $response->assertStatus(404)
                ->assertJson([
                    'success' => false,
                ]);
    }

    public function test_only_returns_active_accessibilities()
    {
        $item1 = Accessibility::create(['code' => 'EASY1', 'status_id' => 1]);
        $item2 = Accessibility::create(['code' => 'EASY2', 'status_id' => 2]);

        AccessibilityTranslation::create(['accessibility_id' => $item1->id, 'locale' => 'en', 'name' => 'Easy Access 1']);
        AccessibilityTranslation::create(['accessibility_id' => $item2->id, 'locale' => 'en', 'name' => 'Easy Access 2']);

        $response = $this->getJson('/api/v1/accessibilities');

        $response->assertStatus(200)
                ->assertJsonCount(1, 'data')
                ->assertJson([
                    'data' => [
                        ['code' => 'EASY1']
                    ]
                ]);
    }

    public function test_respects_accept_language_header()
    {
        $item = Accessibility::create(['code' => 'EASY', 'status_id' => 1]);
        AccessibilityTranslation::create(['accessibility_id' => $item->id, 'locale' => 'en', 'name' => 'Easy Access']);
        AccessibilityTranslation::create(['accessibility_id' => $item->id, 'locale' => 'sl', 'name' => 'Lahek dostop']);

        $response = $this->getJson('/api/v1/accessibilities', ['Accept-Language' => 'sl']);

        $response->assertStatus(200)
                ->assertJson([
                    'data' => [
                        ['name' => 'Lahek dostop']
                    ]
                ]);
    }

    public function test_cache_headers_are_present()
    {
        \Illuminate\Support\Facades\Cache::flush();

        $item = Accessibility::create(['code' => 'EASY', 'status_id' => 1]);
        AccessibilityTranslation::create(['accessibility_id' => $item->id, 'locale' => 'en', 'name' => 'Easy Access']);

        $response = $this->getJson('/api/v1/accessibilities', ['Accept-Language' => 'en']);

        $response->assertStatus(200)
                ->assertHeader('X-Cache-Status')
                ->assertHeader('X-Cache-Key')
                ->assertHeader('X-Locale', 'en');
    }
}
