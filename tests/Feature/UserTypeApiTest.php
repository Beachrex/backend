<?php

namespace Tests\Feature;

use App\Models\UserType;
use App\Models\UserTypeTranslation;
use App\Models\Status;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserTypeApiTest extends TestCase
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

    public function test_can_get_all_user_types()
    {
        $item = UserType::create([
            'code' => 'FAMILY',
            'status_id' => 1,
        ]);

        UserTypeTranslation::create([
            'user_type_id' => $item->id,
            'locale' => 'en',
            'name' => 'Family',
        ]);

        $response = $this->getJson('/api/v1/user-types');

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
                            'code' => 'FAMILY',
                            'name' => 'Family',
                        ]
                    ]
                ]);
    }

    public function test_can_get_specific_user_type()
    {
        $item = UserType::create(['code' => 'FAMILY', 'status_id' => 1]);
        UserTypeTranslation::create(['user_type_id' => $item->id, 'locale' => 'en', 'name' => 'Family']);

        $response = $this->getJson("/api/v1/user-types/{$item->id}");

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'code' => 'FAMILY',
                        'name' => 'Family',
                    ]
                ]);
    }

    public function test_returns_404_for_nonexistent_user_type()
    {
        $response = $this->getJson('/api/v1/user-types/999');

        $response->assertStatus(404)
                ->assertJson([
                    'success' => false,
                ]);
    }

    public function test_only_returns_active_user_types()
    {
        $item1 = UserType::create(['code' => 'FAMILY1', 'status_id' => 1]);
        $item2 = UserType::create(['code' => 'FAMILY2', 'status_id' => 2]);

        UserTypeTranslation::create(['user_type_id' => $item1->id, 'locale' => 'en', 'name' => 'Family 1']);
        UserTypeTranslation::create(['user_type_id' => $item2->id, 'locale' => 'en', 'name' => 'Family 2']);

        $response = $this->getJson('/api/v1/user-types');

        $response->assertStatus(200)
                ->assertJsonCount(1, 'data')
                ->assertJson([
                    'data' => [
                        ['code' => 'FAMILY1']
                    ]
                ]);
    }

    public function test_respects_accept_language_header()
    {
        $item = UserType::create(['code' => 'FAMILY', 'status_id' => 1]);
        UserTypeTranslation::create(['user_type_id' => $item->id, 'locale' => 'en', 'name' => 'Family']);
        UserTypeTranslation::create(['user_type_id' => $item->id, 'locale' => 'sl', 'name' => 'Družina']);

        $response = $this->getJson('/api/v1/user-types', ['Accept-Language' => 'sl']);

        $response->assertStatus(200)
                ->assertJson([
                    'data' => [
                        ['name' => 'Družina']
                    ]
                ]);
    }

    public function test_cache_headers_are_present()
    {
        \Illuminate\Support\Facades\Cache::flush();

        $item = UserType::create(['code' => 'FAMILY', 'status_id' => 1]);
        UserTypeTranslation::create(['user_type_id' => $item->id, 'locale' => 'en', 'name' => 'Family']);

        $response = $this->getJson('/api/v1/user-types', ['Accept-Language' => 'en']);

        $response->assertStatus(200)
                ->assertHeader('X-Cache-Status')
                ->assertHeader('X-Cache-Key')
                ->assertHeader('X-Locale', 'en');
    }
}
