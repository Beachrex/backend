<?php

namespace Tests\Feature;

use App\Http\Middleware\SetLocaleFromHeader;
use App\Models\FirstPageContent;
use App\Models\FirstPageContentTranslation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FirstPageContentCacheTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function saving_a_translation_clears_cache_for_every_supported_locale(): void
    {
        $this->seed(\Database\Seeders\StatusSeeder::class);

        $content = FirstPageContent::create([
            'code' => 'hero',
            'section_type' => 'banner',
            'status_id' => 1,
        ]);

        foreach (SetLocaleFromHeader::SUPPORTED_LOCALES as $locale) {
            Cache::put("first_page_content:index:{$locale}", 'stale');
            Cache::put("first_page_content:show:hero:{$locale}", 'stale');
        }

        FirstPageContentTranslation::create([
            'first_page_content_id' => $content->id,
            'locale' => 'sq',
            'title' => 'Plazhet',
        ]);

        foreach (SetLocaleFromHeader::SUPPORTED_LOCALES as $locale) {
            $this->assertFalse(Cache::has("first_page_content:index:{$locale}"), "index cache not cleared for {$locale}");
            $this->assertFalse(Cache::has("first_page_content:show:hero:{$locale}"), "show cache not cleared for {$locale}");
        }
    }
}
