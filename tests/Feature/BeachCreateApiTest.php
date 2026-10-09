<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BeachCreateApiTest extends TestCase
{
    use RefreshDatabase;

    private array $payload = [
        'latitude' => 45.1234567,
        'longitude' => 13.9876543,
        'translations' => [
            ['locale' => 'en', 'name' => 'Test Beach'],
        ],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\StatusSeeder::class);
    }

    #[Test]
    public function guest_cannot_create_beach()
    {
        $this->postJson('/api/v1/beaches', $this->payload)
            ->assertStatus(401);

        $this->assertDatabaseCount('beach', 0);
    }

    #[Test]
    public function non_admin_cannot_create_beach()
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/beaches', $this->payload)
            ->assertStatus(403);

        $this->assertDatabaseCount('beach', 0);
    }

    #[Test]
    public function admin_can_create_beach()
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/beaches', $this->payload)
            ->assertStatus(201);

        $this->assertDatabaseCount('beach', 1);
    }
}
