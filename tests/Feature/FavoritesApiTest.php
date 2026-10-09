<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** B-M3 (TASK-005, web H6): saved beaches per user, idempotent add/remove, owner only, beach cards in the list. */
class FavoritesApiTest extends TestCase
{
    use RefreshDatabase;

    private function beach(string $name, int $status = 1): int
    {
        $id = DB::table('beach')->insertGetId(['name' => $name, 'country_code' => 'HR', 'status_id' => $status, 'latitude' => 45, 'longitude' => 14]);
        DB::table('beach_translation')->insert([
            ['beach_id' => $id, 'locale' => 'en', 'name' => "Beach {$name}", 'slug' => 'beach-'.strtolower($name)],
            ['beach_id' => $id, 'locale' => 'sl', 'name' => "Plaža {$name}", 'slug' => 'plaza-'.strtolower($name)],
        ]);

        return $id;
    }

    protected function setUp(): void
    {
        parent::setUp();
        DB::table('status')->insertOrIgnore([['id' => 1, 'name' => 'active'], ['id' => 2, 'name' => 'inactive']]);
        DB::table('countries')->insertOrIgnore(['code' => 'HR']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function every_endpoint_needs_sign_in(): void
    {
        $this->getJson('/api/v1/me/favorites')->assertStatus(401);
        $this->getJson('/api/v1/me/favorites/ids')->assertStatus(401);
        $this->postJson('/api/v1/me/favorites', ['beach_id' => 1])->assertStatus(401);
        $this->deleteJson('/api/v1/me/favorites/1')->assertStatus(401);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function save_list_and_remove_beaches_idempotently(): void
    {
        $user = User::factory()->create();
        $a = $this->beach('Alpha');
        $b = $this->beach('Bravo');
        $this->actingAs($user, 'sanctum');

        $this->postJson('/api/v1/me/favorites', ['beach_id' => $a])->assertStatus(201);
        $this->postJson('/api/v1/me/favorites', ['beach_id' => $a])->assertStatus(200); // retry: no duplicate
        $this->travel(1)->seconds();
        $this->postJson('/api/v1/me/favorites', ['beach_id' => $b])->assertStatus(201);

        $this->getJson('/api/v1/me/favorites/ids')->assertOk()->assertExactJson(['data' => [$b, $a]]);
        $this->withHeader('Accept-Language', 'sl')->getJson('/api/v1/me/favorites')->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('data.0.id', $b)
            ->assertJsonPath('data.0.name', 'Plaža Bravo')
            ->assertJsonPath('data.1.slug', 'plaza-alpha');

        $this->deleteJson("/api/v1/me/favorites/{$a}")->assertNoContent();
        $this->deleteJson("/api/v1/me/favorites/{$a}")->assertNoContent(); // retry
        $this->getJson('/api/v1/me/favorites/ids')->assertExactJson(['data' => [$b]]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function users_only_see_and_change_their_own_list(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $a = $this->beach('Alpha');
        $this->actingAs($owner, 'sanctum')->postJson('/api/v1/me/favorites', ['beach_id' => $a])->assertStatus(201);

        $this->actingAs($other, 'sanctum')->getJson('/api/v1/me/favorites/ids')->assertExactJson(['data' => []]);
        $this->actingAs($other, 'sanctum')->deleteJson("/api/v1/me/favorites/{$a}")->assertNoContent();
        $this->assertDatabaseHas('beach_favorites', ['user_id' => $owner->id, 'beach_id' => $a]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function unknown_or_unpublished_beaches_cannot_be_saved(): void
    {
        $this->actingAs(User::factory()->create(), 'sanctum');
        $hidden = $this->beach('Hidden', 2);

        $this->postJson('/api/v1/me/favorites', ['beach_id' => 999999])->assertStatus(422);
        $this->postJson('/api/v1/me/favorites', ['beach_id' => $hidden])->assertStatus(422);
        $this->postJson('/api/v1/me/favorites', [])->assertStatus(422);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function a_beach_unpublished_later_drops_out_of_the_list(): void
    {
        $user = User::factory()->create();
        $a = $this->beach('Alpha');
        $this->actingAs($user, 'sanctum')->postJson('/api/v1/me/favorites', ['beach_id' => $a]);
        DB::table('beach')->where('id', $a)->update(['status_id' => 2]);

        $this->getJson('/api/v1/me/favorites')->assertOk()->assertJsonPath('meta.total', 0);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function the_list_is_capped(): void
    {
        config(['mobile.favorites_limit' => 1]);
        $user = User::factory()->create();
        $a = $this->beach('Alpha');
        $b = $this->beach('Bravo');
        $this->actingAs($user, 'sanctum');

        $this->postJson('/api/v1/me/favorites', ['beach_id' => $a])->assertStatus(201);
        $this->postJson('/api/v1/me/favorites', ['beach_id' => $b])->assertStatus(422)->assertJsonPath('error', 'favorites_limit');
        $this->postJson('/api/v1/me/favorites', ['beach_id' => $a])->assertStatus(200); // re-saving a saved beach still works
        $this->getJson('/api/v1/me/favorites')->assertJsonPath('meta.limit', 1);
    }
}
