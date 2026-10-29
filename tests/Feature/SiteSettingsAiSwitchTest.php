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
 * TASK-009 (owner 2026-10-29): switch the AI guide off per environment in admin, e.g. on prod before Stripe is set up.
 */
class SiteSettingsAiSwitchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\StatusSeeder::class);
    }

    private function off(): void
    {
        SiteSetting::set(SiteSetting::AI_ASSISTANT_ENABLED, false);
    }

    #[Test]
    public function the_guide_is_on_by_default_and_the_api_says_so(): void
    {
        $this->assertSame('1', SiteSetting::query()->where('key', 'ai_assistant_enabled')->value('value'), 'migration seeds ON');

        $this->getJson('/api/v1/site-settings')
            ->assertOk()
            ->assertExactJson(['success' => true, 'data' => ['ai_assistant_enabled' => true]]);
    }

    #[Test]
    public function switching_off_is_visible_in_the_api_at_once(): void
    {
        $this->getJson('/api/v1/site-settings')->assertJsonPath('data.ai_assistant_enabled', true); // warms the cache

        $this->off();

        $this->getJson('/api/v1/site-settings')->assertJsonPath('data.ai_assistant_enabled', false);
    }

    #[Test]
    public function when_off_every_ai_route_answers_503_ai_disabled(): void
    {
        $this->off();
        $user = User::factory()->create();

        $this->postJson('/api/v1/ai/chat', ['message' => 'Best beach in Istria?'])
            ->assertStatus(503)->assertJsonPath('error', 'ai_disabled');
        $this->postJson('/api/v1/ai/itineraries', ['destination' => 'Croatia', 'days' => 3])
            ->assertStatus(503)->assertJsonPath('error', 'ai_disabled');
        $this->actingAs($user)->postJson('/api/v1/ai/itinerary-plans', ['destination' => 'Croatia', 'days' => 7])
            ->assertStatus(503)->assertJsonPath('error', 'ai_disabled');
        $this->actingAs($user)->postJson('/api/v1/payments/stripe/checkout', ['product_type' => 'itinerary_7day'])
            ->assertStatus(503)->assertJsonPath('error', 'ai_disabled');
    }

    #[Test]
    public function when_on_the_switch_does_not_block(): void
    {
        $user = User::factory()->create();

        $this->assertNotSame('ai_disabled', $this->postJson('/api/v1/ai/chat', [])->json('error'));
        $this->assertNotSame(
            'ai_disabled',
            $this->actingAs($user)->postJson('/api/v1/payments/stripe/checkout', [])->json('error')
        );
    }

    #[Test]
    public function checkout_still_requires_sign_in_when_off(): void
    {
        $this->off();

        $this->postJson('/api/v1/payments/stripe/checkout', ['product_type' => 'itinerary_7day'])->assertUnauthorized();
    }

    #[Test]
    public function admin_switches_the_guide_off_and_on_in_admin(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(SiteSettings::class)
            ->assertOk()
            ->assertSee('AI guide on')
            ->assertSet('data.ai_assistant_enabled', true)
            ->set('data.ai_assistant_enabled', false)
            ->call('save')
            ->assertHasNoErrors();
        $this->assertFalse(SiteSetting::aiAssistantEnabled());
        $this->postJson('/api/v1/ai/chat', ['message' => 'x'])->assertStatus(503);

        Livewire::test(SiteSettings::class)
            ->assertSet('data.ai_assistant_enabled', false)
            ->set('data.ai_assistant_enabled', true)
            ->call('save');
        $this->assertTrue(SiteSetting::aiAssistantEnabled());
    }

    #[Test]
    public function non_admin_cannot_open_the_page_and_guest_is_sent_to_login(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $url = SiteSettings::getUrl();

        $this->get($url)->assertRedirect();
        $this->actingAs(User::factory()->create(['is_admin' => false]))->get($url)->assertForbidden();
        $this->assertTrue(SiteSetting::aiAssistantEnabled(), 'unchanged');
    }
}
