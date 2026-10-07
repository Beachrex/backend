<?php

namespace Tests\Feature;

use App\Models\StaticPage;
use App\Models\StaticPageTranslation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StaticPageApiTest extends TestCase
{
    use RefreshDatabase;

    private StaticPage $page;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\StatusSeeder::class);

        $this->page = StaticPage::create([
            'code' => 'privacy-policy',
            'sort_order' => 1,
            'is_in_footer' => true,
            'status_id' => 1,
        ]);

        foreach (['en' => 'Privacy Policy', 'sq' => 'Politika e Privatësisë'] as $locale => $title) {
            StaticPageTranslation::create([
                'static_page_id' => $this->page->id,
                'locale' => $locale,
                'title' => $title,
                'slug' => $locale === 'en' ? 'privacy-policy' : 'politika-e-privatesise',
                'content' => "<p>{$title}</p>",
            ]);
        }
    }

    #[Test]
    public function footer_lists_pages_in_requested_locale(): void
    {
        $this->getJson('/api/v1/static-pages', ['Accept-Language' => 'sq'])
            ->assertOk()
            ->assertJsonPath('data.0.code', 'privacy-policy')
            ->assertJsonPath('data.0.title', 'Politika e Privatësisë');
    }

    #[Test]
    public function show_returns_page_by_localized_slug(): void
    {
        $this->getJson('/api/v1/static-pages/politika-e-privatesise', ['Accept-Language' => 'sq'])
            ->assertOk()
            ->assertJsonPath('data.title', 'Politika e Privatësisë')
            ->assertJsonPath('data.locale', 'sq');
    }

    #[Test]
    public function show_returns_404_for_unknown_slug(): void
    {
        $this->getJson('/api/v1/static-pages/does-not-exist')
            ->assertStatus(404);
    }

    #[Test]
    public function admin_edit_is_visible_after_cache_warmup(): void
    {
        // Warm the cache, then edit the translation (as Filament would)
        $this->getJson('/api/v1/static-pages', ['Accept-Language' => 'sq'])->assertOk();

        $this->page->translations()->where('locale', 'sq')->first()
            ->update(['title' => 'Privatësia']);

        $this->getJson('/api/v1/static-pages', ['Accept-Language' => 'sq'])
            ->assertOk()
            ->assertJsonPath('data.0.title', 'Privatësia');
    }
}
