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
 * Owner 2026-10-29: Google ads (AdSense) off on prod right after deploy, with an admin on/off switch.
 */
class SiteSettingsAdsSwitchTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRATION = 'migrations/2026_10_29_200000_add_ads_enabled_site_setting.php';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\StatusSeeder::class);
    }

    #[Test]
    public function ads_are_off_by_default_and_the_api_says_so(): void
    {
        $this->assertSame('0', SiteSetting::query()->where('key', 'ads_enabled')->value('value'), 'migration seeds ads OFF');
        $this->assertFalse(SiteSetting::adsEnabled());

        $this->getJson('/api/v1/site-settings')
            ->assertOk()
            ->assertJsonPath('data.ads_enabled', false)
            ->assertJsonStructure(['success', 'data' => ['ai_assistant_enabled', 'ai_trip_planner_enabled', 'ads_enabled']]);
    }

    #[Test]
    public function a_missing_key_reads_as_off(): void
    {
        SiteSetting::query()->where('key', 'ads_enabled')->delete();

        $this->assertFalse(SiteSetting::adsEnabled());
        $this->getJson('/api/v1/site-settings')->assertJsonPath('data.ads_enabled', false);
    }

    #[Test]
    public function the_migration_is_idempotent_and_keeps_a_value_set_in_admin(): void
    {
        SiteSetting::set(SiteSetting::ADS_ENABLED, true);
        $migration = require database_path(self::MIGRATION);

        $migration->up();
        $migration->up();

        $this->assertSame(1, SiteSetting::query()->where('key', 'ads_enabled')->count());
        $this->assertTrue(SiteSetting::adsEnabled(), 'an existing ON value is kept');
    }

    #[Test]
    public function the_migration_inserts_off_when_the_key_is_missing(): void
    {
        SiteSetting::query()->where('key', 'ads_enabled')->delete();
        $migration = require database_path(self::MIGRATION);

        $migration->up();

        $this->assertSame('0', SiteSetting::query()->where('key', 'ads_enabled')->value('value'));
    }

    #[Test]
    public function switching_on_is_visible_in_the_api_at_once(): void
    {
        $this->getJson('/api/v1/site-settings')->assertJsonPath('data.ads_enabled', false); // warms the cache

        SiteSetting::set(SiteSetting::ADS_ENABLED, true);
        $this->getJson('/api/v1/site-settings')
            ->assertJsonPath('data.ads_enabled', true)
            ->assertJsonPath('data.ai_assistant_enabled', true)
            ->assertJsonPath('data.ai_trip_planner_enabled', true);
    }

    #[Test]
    public function admin_toggles_ads_without_touching_the_ai_switches(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(SiteSettings::class)
            ->assertOk()
            ->assertSee('Google ads (AdSense)')
            ->assertSet('data.ads_enabled', false)
            ->set('data.ads_enabled', true)
            ->call('save')
            ->assertHasNoErrors();
        $this->assertTrue(SiteSetting::adsEnabled());
        $this->assertTrue(SiteSetting::aiAssistantEnabled(), 'chat untouched');
        $this->assertTrue(SiteSetting::aiTripPlannerEnabled(), 'planner untouched');

        Livewire::test(SiteSettings::class)
            ->assertSet('data.ads_enabled', true)
            ->set('data.ads_enabled', false)
            ->call('save');
        $this->assertFalse(SiteSetting::adsEnabled());
        $this->getJson('/api/v1/site-settings')->assertJsonPath('data.ads_enabled', false);
    }

    #[Test]
    public function non_admin_cannot_open_the_page_and_guest_is_sent_to_login(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $url = SiteSettings::getUrl();

        $this->get($url)->assertRedirect();
        $this->actingAs(User::factory()->create(['is_admin' => false]))->get($url)->assertForbidden();
        $this->assertFalse(SiteSetting::adsEnabled(), 'unchanged');
    }
}
