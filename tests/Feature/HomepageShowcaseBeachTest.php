<?php

namespace Tests\Feature;

use App\Http\Middleware\SetLocaleFromHeader;
use App\Models\Beach;
use App\Models\FirstPageContent;
use App\Models\FirstPageContentTranslation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * TASK-008: `beach_views.config.showcase_beach_id` picks the example beach on the homepage.
 */
class HomepageShowcaseBeachTest extends TestCase
{
    use RefreshDatabase;

    private object $migration;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\StatusSeeder::class);
        $this->migration = require database_path('migrations/2026_10_28_120000_set_homepage_showcase_beach.php');
    }

    private function section(array $config = null): FirstPageContent
    {
        $content = FirstPageContent::create([
            'code' => 'beach_views', 'section_type' => 'repeater', 'status_id' => 1, 'config' => $config,
        ]);
        FirstPageContentTranslation::create([
            'first_page_content_id' => $content->id, 'locale' => 'en', 'title' => 'View beaches from all angles',
        ]);

        return $content;
    }

    private function beach989(): void
    {
        Beach::factory()->create(['id' => 989]);
    }

    private function configOf(FirstPageContent $content): ?array
    {
        $raw = DB::table('first_page_content')->where('id', $content->id)->value('config');

        return $raw === null ? null : json_decode($raw, true);
    }

    #[Test]
    public function migration_sets_the_default_beach_when_none_is_set(): void
    {
        $this->beach989();
        $content = $this->section();

        $this->migration->up();

        $this->assertSame(['showcase_beach_id' => '989'], $this->configOf($content));
    }

    #[Test]
    public function migration_keeps_a_beach_chosen_in_admin_and_other_keys(): void
    {
        $this->beach989();
        $content = $this->section(['showcase_beach_id' => '614', 'layout' => 'wide']);

        $this->migration->up();
        $this->migration->up();

        $this->assertSame(['showcase_beach_id' => '614', 'layout' => 'wide'], $this->configOf($content));
    }

    #[Test]
    public function migration_does_nothing_without_the_beach_or_the_section(): void
    {
        $content = $this->section();
        $this->migration->up();
        $this->assertNull($this->configOf($content));

        $content->forceDelete();
        $this->beach989();
        $this->migration->up();
        $this->assertSame(0, DB::table('first_page_content')->count());
    }

    #[Test]
    public function the_api_returns_the_configured_beach(): void
    {
        $this->beach989();
        $this->section();
        $this->migration->up();

        $sections = $this->getJson('/api/v1/first-page-content', ['Accept-Language' => 'en'])->assertOk()->json('data');
        $beachViews = collect($sections)->firstWhere('code', 'beach_views');

        $this->assertSame('989', $beachViews['config']['showcase_beach_id']);
    }

    #[Test]
    public function saving_a_section_in_admin_clears_the_homepage_cache_in_every_language(): void
    {
        // Servers use Redis, where `Cacheable` deletes only `reference:FirstPageContent:*` keys. The test cache
        // (array) would flush everything instead and hide the bug, so make it behave like Redis here.
        $this->partialMock(\App\Services\CacheService::class, function ($mock) {
            $mock->shouldReceive('clearReferenceCache')->andReturnNull();
        });

        $content = $this->section();
        $this->getJson('/api/v1/first-page-content', ['Accept-Language' => 'en'])->assertOk();

        foreach (SetLocaleFromHeader::SUPPORTED_LOCALES as $locale) {
            Cache::put("first_page_content:index:{$locale}", 'stale');
            Cache::put("first_page_content:show:beach_views:{$locale}", 'stale');
        }

        $content->update(['config' => ['showcase_beach_id' => '614']]);

        foreach (SetLocaleFromHeader::SUPPORTED_LOCALES as $locale) {
            $this->assertFalse(Cache::has("first_page_content:index:{$locale}"), "index cache kept for {$locale}");
            $this->assertFalse(Cache::has("first_page_content:show:beach_views:{$locale}"), "show cache kept for {$locale}");
        }

        $sections = $this->getJson('/api/v1/first-page-content', ['Accept-Language' => 'en'])->assertOk()->json('data');
        $this->assertSame('614', collect($sections)->firstWhere('code', 'beach_views')['config']['showcase_beach_id']);
    }
}
