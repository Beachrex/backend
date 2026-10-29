<?php

namespace Tests\Feature;

use App\Filament\BrAdmin\Pages\SiteSettings;
use App\Models\SiteSetting;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * TASK-009 (owner 2026-10-29): two independent admin switches per environment, AI chat and the paid AI trip planner
 * (the owner switches only the planner off on prod).
 */
class SiteSettingsAiSwitchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\StatusSeeder::class);
    }

    private function chatOff(): void
    {
        SiteSetting::set(SiteSetting::AI_ASSISTANT_ENABLED, false);
    }

    private function plannerOff(): void
    {
        SiteSetting::set(SiteSetting::AI_TRIP_PLANNER_ENABLED, false);
    }

    /** Chat routes: [method, uri, body]. */
    private function chatRoutes(): array
    {
        return [
            ['POST', '/api/v1/ai/chat', ['message' => 'Best beach in Istria?']],
            ['POST', '/api/v1/ai/test', ['message' => 'x']],
        ];
    }

    /** Trip planner routes (signed in): [method, uri, body]. */
    private function plannerRoutes(): array
    {
        return [
            ['POST', '/api/v1/ai/itineraries', ['destination' => 'Croatia', 'days' => 3]],
            ['POST', '/api/v1/ai/itinerary-plans', ['destination' => 'Croatia', 'days' => 7]],
            ['GET', '/api/v1/payments/products', []],
            ['POST', '/api/v1/payments/stripe/checkout', ['product_type' => 'itinerary_7day']],
        ];
    }

    private function hit(array $route): \Illuminate\Testing\TestResponse
    {
        [$method, $uri, $body] = $route;

        return $this->json($method, $uri, $body);
    }

    #[Test]
    public function both_switches_are_on_by_default_and_the_api_says_so(): void
    {
        $this->assertSame('1', SiteSetting::query()->where('key', 'ai_assistant_enabled')->value('value'), 'migration seeds chat ON');
        $this->assertSame('1', SiteSetting::query()->where('key', 'ai_trip_planner_enabled')->value('value'), 'migration seeds planner ON');

        $this->getJson('/api/v1/site-settings')
            ->assertOk()
            ->assertExactJson(['success' => true, 'data' => [
                'ai_assistant_enabled' => true,
                'ai_trip_planner_enabled' => true,
                'ads_enabled' => false,
            ]]);
    }

    #[Test]
    public function the_planner_migration_is_idempotent_and_keeps_an_existing_value(): void
    {
        $this->plannerOff();
        $migration = require database_path('migrations/2026_10_29_150000_add_ai_trip_planner_enabled_site_setting.php');

        $migration->up();
        $migration->up();

        $this->assertSame(1, SiteSetting::query()->where('key', 'ai_trip_planner_enabled')->count());
        $this->assertSame('0', SiteSetting::query()->where('key', 'ai_trip_planner_enabled')->value('value'));
    }

    #[Test]
    public function a_missing_planner_key_reads_as_on(): void
    {
        SiteSetting::query()->where('key', 'ai_trip_planner_enabled')->delete();

        $this->assertTrue(SiteSetting::aiTripPlannerEnabled());
        $this->getJson('/api/v1/site-settings')->assertJsonPath('data.ai_trip_planner_enabled', true);
    }

    #[Test]
    public function switching_off_is_visible_in_the_api_at_once(): void
    {
        $this->getJson('/api/v1/site-settings')->assertJsonPath('data.ai_assistant_enabled', true); // warms the cache

        $this->plannerOff();
        $this->getJson('/api/v1/site-settings')
            ->assertJsonPath('data.ai_assistant_enabled', true)
            ->assertJsonPath('data.ai_trip_planner_enabled', false);

        $this->chatOff();
        $this->getJson('/api/v1/site-settings')
            ->assertJsonPath('data.ai_assistant_enabled', false)
            ->assertJsonPath('data.ai_trip_planner_enabled', false);
    }

    #[Test]
    public function chat_off_closes_only_the_chat_routes(): void
    {
        $this->chatOff();
        $this->actingAs(User::factory()->create());

        foreach ($this->chatRoutes() as $route) {
            $this->hit($route)->assertStatus(503)->assertJsonPath('error', 'ai_disabled');
        }
        foreach ($this->plannerRoutes() as $route) {
            $error = $this->hit($route)->json('error');
            $this->assertNotContains($error, ['ai_disabled', 'ai_trip_planner_disabled'], $route[1]);
        }
    }

    #[Test]
    public function planner_off_closes_only_the_planner_routes(): void
    {
        $this->plannerOff();
        $this->actingAs(User::factory()->create());

        foreach ($this->plannerRoutes() as $route) {
            $this->hit($route)->assertStatus(503)->assertJsonPath('error', 'ai_trip_planner_disabled');
        }
        foreach ($this->chatRoutes() as $route) {
            $error = $this->hit($route)->json('error');
            $this->assertNotContains($error, ['ai_disabled', 'ai_trip_planner_disabled'], $route[1]);
        }
    }

    #[Test]
    public function both_off_close_every_ai_route_with_its_own_error(): void
    {
        $this->chatOff();
        $this->plannerOff();
        $this->actingAs(User::factory()->create());

        foreach ($this->chatRoutes() as $route) {
            $this->hit($route)->assertStatus(503)->assertJsonPath('error', 'ai_disabled');
        }
        foreach ($this->plannerRoutes() as $route) {
            $this->hit($route)->assertStatus(503)->assertJsonPath('error', 'ai_trip_planner_disabled');
        }
    }

    #[Test]
    public function the_webhook_and_bought_plans_stay_open_when_both_are_off(): void
    {
        $this->chatOff();
        $this->plannerOff();

        $webhook = $this->postJson('/api/v1/payments/stripe/webhook', []);
        $this->assertNotSame(503, $webhook->status());

        $this->actingAs(User::factory()->create());
        $this->getJson('/api/v1/me/itinerary-plans')->assertOk();
        $this->getJson('/api/v1/me/entitlements')->assertOk();
    }

    #[Test]
    public function when_on_the_switches_do_not_block(): void
    {
        $this->actingAs(User::factory()->create());

        foreach ([...$this->chatRoutes(), ...$this->plannerRoutes()] as $route) {
            $error = $this->hit($route)->json('error');
            $this->assertNotContains($error, ['ai_disabled', 'ai_trip_planner_disabled'], $route[1]);
        }
    }

    #[Test]
    public function guests_get_401_on_checkout_and_the_switch_answer_on_the_ai_group_when_off(): void
    {
        $this->plannerOff();

        // The switch runs before sign-in on the /ai group (as before), after sign-in on checkout (as before).
        $this->postJson('/api/v1/ai/itinerary-plans', ['destination' => 'Croatia', 'days' => 7])
            ->assertStatus(503)->assertJsonPath('error', 'ai_trip_planner_disabled');
        $this->postJson('/api/v1/payments/stripe/checkout', ['product_type' => 'itinerary_7day'])->assertUnauthorized();
    }

    #[Test]
    public function admin_switches_each_feature_independently(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(SiteSettings::class)
            ->assertOk()
            ->assertSee('AI chat')
            ->assertSee('AI trip planner (paid)')
            ->assertSet('data.ai_assistant_enabled', true)
            ->assertSet('data.ai_trip_planner_enabled', true)
            ->set('data.ai_trip_planner_enabled', false)
            ->call('save')
            ->assertHasNoErrors();
        $this->assertTrue(SiteSetting::aiAssistantEnabled(), 'chat untouched');
        $this->assertFalse(SiteSetting::aiTripPlannerEnabled());
        $this->postJson('/api/v1/ai/itineraries', ['destination' => 'Croatia', 'days' => 3])->assertStatus(503);

        Livewire::test(SiteSettings::class)
            ->assertSet('data.ai_trip_planner_enabled', false)
            ->set('data.ai_assistant_enabled', false)
            ->set('data.ai_trip_planner_enabled', true)
            ->call('save');
        $this->assertFalse(SiteSetting::aiAssistantEnabled());
        $this->assertTrue(SiteSetting::aiTripPlannerEnabled());
        $this->postJson('/api/v1/ai/chat', ['message' => 'x'])->assertStatus(503)->assertJsonPath('error', 'ai_disabled');
    }

    #[Test]
    public function non_admin_cannot_open_the_page_and_guest_is_sent_to_login(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $url = SiteSettings::getUrl();

        $this->get($url)->assertRedirect();
        $this->actingAs(User::factory()->create(['is_admin' => false]))->get($url)->assertForbidden();
        $this->assertTrue(SiteSetting::aiAssistantEnabled(), 'unchanged');
        $this->assertTrue(SiteSetting::aiTripPlannerEnabled(), 'unchanged');
    }
}
