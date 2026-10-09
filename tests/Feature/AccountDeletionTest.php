<?php

namespace Tests\Feature;

use App\Models\Purchase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * B-M2 (owner 2026-10-09: the app signs in with e-mail + password only; Google/Apple later). Account deletion
 * (App Store 5.1.1(v), GDPR art. 17) removes personal data but keeps purchases for accounting; login and
 * register are throttled against password guessing.
 */
class AccountDeletionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        DB::table('status')->insertOrIgnore([['id' => 1, 'name' => 'active'], ['id' => 2, 'name' => 'inactive']]);
        RateLimiter::clear('');
    }

    private function user(array $attrs = []): User
    {
        return User::factory()->create(['email' => 'ana@example.com', 'password' => Hash::make('secret-pass-1'), 'name' => 'Ana Novak'] + $attrs);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function deleting_removes_personal_data_and_keeps_purchases(): void
    {
        $user = $this->user(['firstname' => 'Ana', 'mobile' => '+38640111222']);
        $token = $user->createToken('app')->plainTextToken;
        DB::table('countries')->insertOrIgnore(['code' => 'HR']);
        $beach = DB::table('beach')->insertGetId(['name' => 'B', 'country_code' => 'HR', 'status_id' => 1]);
        DB::table('beach_favorites')->insert(['user_id' => $user->id, 'beach_id' => $beach, 'created_at' => now()]);
        DB::table('user_entitlements')->insert(['user_id' => $user->id, 'itinerary_3day_credits' => 1, 'itinerary_7day_credits' => 0, 'itinerary_14day_credits' => 0]);
        DB::table('ai_sessions')->insert(['id' => '11111111-1111-4111-8111-111111111111', 'user_id' => $user->id, 'tier' => 'basic', 'locale' => 'sl', 'created_at' => now(), 'updated_at' => now()]);
        Purchase::create(['user_id' => $user->id, 'product_type' => 'itinerary_3day', 'amount' => 19, 'currency' => 'EUR', 'status' => 'completed', 'stripe_session_id' => 'cs_1', 'purchased_at' => now()]);

        $this->withToken($token)->deleteJson('/api/v1/me', ['password' => 'secret-pass-1'])->assertNoContent();

        $row = DB::table('users')->find($user->id);
        $this->assertSame("Deleted user #{$user->id}", $row->name);
        $this->assertStringEndsWith('@deleted.beachrex.invalid', $row->email);
        $this->assertNull($row->firstname);
        $this->assertNull($row->mobile);
        $this->assertSame(0, DB::table('personal_access_tokens')->where('tokenable_id', $user->id)->count());
        $this->assertSame(0, DB::table('beach_favorites')->where('user_id', $user->id)->count());
        $this->assertSame(0, DB::table('user_entitlements')->where('user_id', $user->id)->count());
        $this->assertSame(0, DB::table('ai_sessions')->where('user_id', $user->id)->count());
        $this->assertSame(1, Purchase::where('user_id', $user->id)->count()); // accounting record kept

        // The old e-mail and password no longer work, and the e-mail is free for a new account.
        $this->postJson('/api/v1/login', ['email' => 'ana@example.com', 'password' => 'secret-pass-1'])->assertStatus(422);
        $this->postJson('/api/v1/register', ['name' => 'Ana', 'email' => 'ana@example.com', 'password' => 'another-pass-2', 'password_confirmation' => 'another-pass-2'])->assertStatus(201);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function deleting_needs_the_password_or_for_google_accounts_an_explicit_confirm(): void
    {
        $user = $this->user();
        $this->actingAs($user, 'sanctum')->deleteJson('/api/v1/me', ['password' => 'wrong'])->assertStatus(422);
        $this->actingAs($user, 'sanctum')->deleteJson('/api/v1/me', ['confirm' => 'DELETE'])->assertStatus(422); // not a Google account
        $this->assertSame('Ana Novak', $user->fresh()->name);

        $google = User::factory()->create(['google_id' => 'g-123']);
        $this->actingAs($google, 'sanctum')->deleteJson('/api/v1/me', ['confirm' => 'yes'])->assertStatus(422);
        $this->actingAs($google, 'sanctum')->deleteJson('/api/v1/me', ['confirm' => 'DELETE'])->assertNoContent();
        $this->assertNull($google->fresh()->google_id);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function admins_and_guests_cannot_delete_through_the_api(): void
    {
        $this->deleteJson('/api/v1/me', ['password' => 'x'])->assertStatus(401);
        $admin = $this->user(['is_admin' => true]);
        $this->actingAs($admin, 'sanctum')->deleteJson('/api/v1/me', ['password' => 'secret-pass-1'])->assertStatus(403);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function login_is_throttled_against_password_guessing(): void
    {
        $this->user();
        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/v1/login', ['email' => 'ana@example.com', 'password' => "guess-{$i}"])->assertStatus(422);
        }
        $this->postJson('/api/v1/login', ['email' => 'ana@example.com', 'password' => 'secret-pass-1'])->assertStatus(429);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function login_returns_a_token_for_the_app(): void
    {
        $this->user();
        $this->postJson('/api/v1/login', ['email' => 'ana@example.com', 'password' => 'secret-pass-1'])
            ->assertOk()->assertJsonStructure(['token', 'user']);
        $this->postJson('/api/v1/login', [])->assertStatus(422);
    }
}
