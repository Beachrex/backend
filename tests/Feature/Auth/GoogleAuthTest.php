<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Tests\TestCase;

class GoogleAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed status table for foreign key constraints
        $this->seed(\Database\Seeders\StatusSeeder::class);
    }

    public function test_redirect_returns_google_oauth_url_and_stores_state(): void
    {
        $response = $this->getJson('/api/v1/auth/google/redirect');

        $response->assertStatus(200)
            ->assertJsonStructure(['url']);

        $url = $response->json('url');
        $this->assertStringContainsString('accounts.google.com', $url);
        $this->assertStringContainsString('state=', $url);

        // Extract state from URL
        parse_str(parse_url($url, PHP_URL_QUERY), $query);
        $state = $query['state'] ?? null;

        $this->assertNotNull($state);
        $this->assertTrue(Cache::has("google_oauth_state:{$state}"));
    }

    public function test_callback_with_invalid_state_returns_401(): void
    {
        $response = $this->getJson('/api/v1/auth/google/callback?code=test_code&state=invalid_state');

        $response->assertStatus(401)
            ->assertJson([
                'error' => 'invalid_state',
                'message' => 'Invalid or expired state parameter. Please try again.',
            ]);
    }

    public function test_callback_with_missing_code_returns_400(): void
    {
        $state = 'test_state';
        Cache::put("google_oauth_state:{$state}", ['created_at' => now()->toIso8601String()], 600);

        $response = $this->getJson("/api/v1/auth/google/callback?state={$state}");

        $response->assertStatus(400)
            ->assertJson([
                'error' => 'invalid_code',
                'message' => 'Authorization code is required',
            ]);
    }

    public function test_callback_with_missing_state_returns_400(): void
    {
        $response = $this->getJson('/api/v1/auth/google/callback?code=test_code');

        $response->assertStatus(400)
            ->assertJson([
                'error' => 'invalid_state',
                'message' => 'State parameter is required',
            ]);
    }

    public function test_callback_with_google_error_returns_400(): void
    {
        $response = $this->getJson('/api/v1/auth/google/callback?error=access_denied');

        $response->assertStatus(400)
            ->assertJson([
                'error' => 'google_api_error',
                'message' => 'User denied access to Google account',
            ]);
    }

    public function test_callback_creates_new_user_and_returns_token(): void
    {
        $state = 'test_state';
        Cache::put("google_oauth_state:{$state}", ['created_at' => now()->toIso8601String()], 600);

        // Mock Socialite
        $socialiteUser = Mockery::mock(SocialiteUser::class);
        $socialiteUser->shouldReceive('getId')->andReturn('google_123');
        $socialiteUser->shouldReceive('getEmail')->andReturn('test@example.com');
        $socialiteUser->shouldReceive('getName')->andReturn('Test User');
        $socialiteUser->shouldReceive('getAvatar')->andReturn('https://example.com/avatar.jpg');

        Socialite::shouldReceive('driver->stateless->user')->andReturn($socialiteUser);

        $response = $this->getJson("/api/v1/auth/google/callback?code=test_code&state={$state}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'token',
                'token_type',
                'expires_in',
                'user' => ['id', 'name', 'email', 'avatar_url', 'locale', 'created_at'],
            ])
            ->assertJson([
                'token_type' => 'Bearer',
                'expires_in' => 0,
                'user' => [
                    'name' => 'Test User',
                    'email' => 'test@example.com',
                    'avatar_url' => 'https://example.com/avatar.jpg',
                ],
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'test@example.com',
            'google_id' => 'google_123',
        ]);

        // Verify state was deleted
        $this->assertFalse(Cache::has("google_oauth_state:{$state}"));
    }

    public function test_callback_links_google_to_existing_user_with_same_email(): void
    {
        $existingUser = User::factory()->create([
            'email' => 'test@example.com',
            'google_id' => null,
        ]);

        $state = 'test_state';
        Cache::put("google_oauth_state:{$state}", ['created_at' => now()->toIso8601String()], 600);

        // Mock Socialite
        $socialiteUser = Mockery::mock(SocialiteUser::class);
        $socialiteUser->shouldReceive('getId')->andReturn('google_123');
        $socialiteUser->shouldReceive('getEmail')->andReturn('test@example.com');
        $socialiteUser->shouldReceive('getName')->andReturn('Test User');
        $socialiteUser->shouldReceive('getAvatar')->andReturn('https://example.com/avatar.jpg');

        Socialite::shouldReceive('driver->stateless->user')->andReturn($socialiteUser);

        $response = $this->getJson("/api/v1/auth/google/callback?code=test_code&state={$state}");

        $response->assertStatus(200);

        $this->assertDatabaseHas('users', [
            'id' => $existingUser->id,
            'email' => 'test@example.com',
            'google_id' => 'google_123',
        ]);
    }

    public function test_me_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/me');

        $response->assertStatus(401);
    }

    public function test_me_returns_authenticated_user_profile(): void
    {
        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'avatar_url' => 'https://example.com/avatar.jpg',
            'locale' => 'en',
        ]);

        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/me');

        $response->assertStatus(200)
            ->assertJson([
                'id' => $user->id,
                'name' => 'Test User',
                'email' => 'test@example.com',
                'avatar_url' => 'https://example.com/avatar.jpg',
                'locale' => 'en',
            ]);
    }

    public function test_logout_revokes_current_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        // Verify token works before logout
        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/me');
        $response->assertStatus(200);

        // Logout
        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/auth/logout');

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        // Verify token count decreased
        $this->assertEquals(0, $user->fresh()->tokens()->count());
    }

    public function test_logout_requires_authentication(): void
    {
        $response = $this->postJson('/api/v1/auth/logout');

        $response->assertStatus(401);
    }
}
