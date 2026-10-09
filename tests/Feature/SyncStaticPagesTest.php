<?php

namespace Tests\Feature;

use App\Http\Middleware\SetLocaleFromHeader;
use App\Models\StaticPage;
use App\Models\StaticPageTranslation;
use App\Models\Status;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * `php artisan static-pages:sync` loads the footer pages from the content files (TASK-004 step 3).
 */
class SyncStaticPagesTest extends TestCase
{
    use RefreshDatabase;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        Status::insert([['id' => 1, 'name' => 'active'], ['id' => 2, 'name' => 'inactive']]);
        $this->dir = 'storage/framework/testing/static-pages-' . uniqid();
        File::ensureDirectoryExists(base_path($this->dir));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(base_path($this->dir));
        parent::tearDown();
    }

    private function page(string $slug, string $title = 'Title'): array
    {
        return ['title' => $title, 'slug' => $slug, 'meta_title' => "{$title} | Beachrex", 'meta_description' => 'Description', 'content' => '<p>Text</p>'];
    }

    private function write(string $locale, array $pages): void
    {
        File::put(base_path("{$this->dir}/{$locale}.json"), json_encode($pages));
    }

    private function sync(array $options = []): int
    {
        return $this->artisan('static-pages:sync', ['--path' => $this->dir] + $options)->run();
    }

    public function test_creates_missing_pages_and_translations(): void
    {
        $this->write('en', ['cookie-policy' => $this->page('cookie-policy')]);
        $this->write('sl', ['cookie-policy' => $this->page('politika-piskotkov', 'Politika piškotkov')]);

        $this->assertSame(0, $this->sync());

        $page = StaticPage::where('code', 'cookie-policy')->firstOrFail();
        $this->assertTrue($page->is_in_footer);
        $this->assertSame(1, (int) $page->status_id);
        $this->assertSame(6, $page->sort_order);
        $this->assertSame('politika-piskotkov', $page->translations()->where('locale', 'sl')->value('slug'));
        $this->assertSame(2, $page->translations()->count());
    }

    public function test_updates_existing_translation_keeps_page_settings_and_is_idempotent(): void
    {
        $page = StaticPage::create(['code' => 'about-us', 'sort_order' => 9, 'is_in_footer' => false, 'status_id' => 2]);
        StaticPageTranslation::create(['static_page_id' => $page->id, 'locale' => 'en', 'title' => 'Old', 'slug' => 'about-us', 'content' => '<p>Placeholder</p>']);
        $this->write('en', ['about-us' => $this->page('about-us', 'About us')]);

        $this->sync();
        $this->sync();

        $page->refresh();
        $this->assertSame(9, $page->sort_order);
        $this->assertFalse($page->is_in_footer);
        $this->assertSame(2, (int) $page->status_id);
        $this->assertSame(1, $page->translations()->count());
        $this->assertSame('About us', $page->translations()->value('title'));
        $this->assertSame('<p>Text</p>', $page->translations()->value('content'));
    }

    public function test_dry_run_changes_nothing(): void
    {
        $this->write('en', ['contact' => $this->page('contact')]);

        $this->assertSame(0, $this->sync(['--dry-run' => true]));

        $this->assertSame(0, StaticPage::count());
        $this->assertSame(0, StaticPageTranslation::count());
    }

    public function test_never_deletes_and_leaves_unknown_pages_alone(): void
    {
        $other = StaticPage::create(['code' => 'imprint', 'sort_order' => 7, 'is_in_footer' => true, 'status_id' => 1]);
        StaticPageTranslation::create(['static_page_id' => $other->id, 'locale' => 'en', 'title' => 'Imprint', 'slug' => 'imprint', 'content' => '<p>x</p>']);
        $deleted = StaticPage::create(['code' => 'contact', 'sort_order' => 5, 'is_in_footer' => true, 'status_id' => 1]);
        $deleted->delete();
        $this->write('en', ['contact' => $this->page('contact'), 'terms-of-use' => $this->page('terms-of-use')]);

        $this->sync();

        $this->assertSame('Imprint', $other->translations()->value('title'));
        $this->assertTrue(StaticPage::withTrashed()->where('code', 'contact')->first()->trashed());
        $this->assertSame(0, StaticPageTranslation::where('slug', 'contact')->count());
        $this->assertTrue(StaticPage::where('code', 'terms-of-use')->exists());
    }

    public function test_invalid_content_fails_without_writing(): void
    {
        $this->write('en', ['contact' => ['title' => 'Contact', 'slug' => 'Kontakt Ž'] + $this->page('x')]);
        $this->assertSame(1, $this->sync());

        $this->write('en', ['contact' => $this->page('same'), 'about-us' => $this->page('same')]);
        $this->assertSame(1, $this->sync());

        File::put(base_path("{$this->dir}/xx.json"), '{}');
        $this->write('en', ['contact' => $this->page('contact')]);
        $this->assertSame(1, $this->sync());

        $this->assertSame(0, StaticPage::count());
    }

    public function test_slug_owned_by_another_page_is_rejected(): void
    {
        $other = StaticPage::create(['code' => 'imprint', 'sort_order' => 7, 'is_in_footer' => true, 'status_id' => 1]);
        StaticPageTranslation::create(['static_page_id' => $other->id, 'locale' => 'en', 'title' => 'Imprint', 'slug' => 'contact', 'content' => '<p>x</p>']);
        $this->write('en', ['contact' => $this->page('contact')]);

        $this->assertSame(1, $this->sync());
        $this->assertFalse(StaticPage::where('code', 'contact')->exists());
    }

    public function test_synced_page_is_served_by_the_api_in_its_locale(): void
    {
        $this->write('en', ['privacy-policy' => $this->page('privacy-policy', 'Privacy policy')]);
        $this->write('de', ['privacy-policy' => $this->page('datenschutzerklaerung', 'Datenschutzerklärung')]);
        $this->sync();

        $this->getJson('/api/v1/static-pages/datenschutzerklaerung', ['Accept-Language' => 'de'])
            ->assertOk()
            ->assertJsonPath('data.title', 'Datenschutzerklärung');
        $this->getJson('/api/v1/static-pages', ['Accept-Language' => 'de'])
            ->assertOk()
            ->assertJsonPath('data.0.slug', 'datenschutzerklaerung');
    }

    /** The committed content: every backend locale, the same pages, valid fields. */
    public function test_committed_content_covers_every_locale(): void
    {
        $dir = base_path('database/content/static-pages');
        $en = json_decode(File::get("{$dir}/en.json"), true);
        $this->assertSame(['about-us', 'contact', 'privacy-policy', 'cookie-policy', 'terms-of-use', 'contribution-rules', 'ai-assistant-connector', 'media-kit'], array_keys($en));

        foreach (SetLocaleFromHeader::SUPPORTED_LOCALES as $locale) {
            $this->assertFileExists("{$dir}/{$locale}.json", "missing content for {$locale}");
            $pages = json_decode(File::get("{$dir}/{$locale}.json"), true);
            $this->assertSame(array_keys($en), array_keys($pages), "pages differ in {$locale}");
            $this->assertStringContainsString('<div class="cky-audit-table-element"></div>', $pages['cookie-policy']['content'], "CookieYes table missing in {$locale}");
            foreach ($pages as $code => $page) {
                $this->assertSame(
                    substr_count($en[$code]['content'], '<h2>'),
                    substr_count($page['content'], '<h2>'),
                    "{$locale} {$code}: section count differs from English",
                );
            }
        }

        $this->artisan('static-pages:sync', ['--dry-run' => true])->assertExitCode(0);
    }
}
