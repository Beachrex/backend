<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Pre-launch 2026-10-23: the hero title carried a "DEV" marker; five languages had no title. */
class HeroBannerTitleFixTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function the_marker_goes_missing_languages_get_a_title_and_edited_rows_stay(): void
    {
        DB::table('status')->insertOrIgnore([['id' => 1, 'name' => 'active']]);
        $hero = DB::table('first_page_content')->insertGetId(['code' => 'hero_banner', 'section_type' => 'banner', 'order' => 1, 'status_id' => 1]);
        DB::table('first_page_content_translation')->insert([
            ['first_page_content_id' => $hero, 'locale' => 'en', 'title' => 'All worries are smaller on the beach! DEV', 'metadata' => json_encode(['button' => 'Explore'])],
            ['first_page_content_id' => $hero, 'locale' => 'sl', 'title' => 'Vse skrbi so manjše na plaži!', 'metadata' => null],
            ['first_page_content_id' => $hero, 'locale' => 'nl', 'title' => 'Door een mens geschreven', 'metadata' => null],
        ]);

        $migration = require database_path('migrations/2026_10_23_060000_fix_hero_banner_title.php');
        $migration->up();
        $migration->up(); // idempotent

        $title = fn ($l) => DB::table('first_page_content_translation')->where('first_page_content_id', $hero)->where('locale', $l)->value('title');
        $this->assertSame('All worries are smaller on the beach!', $title('en'));
        $this->assertSame('Vse skrbi so manjše na plaži!', $title('sl'));
        $this->assertSame('Door een mens geschreven', $title('nl'), 'an existing row is not overwritten');
        $this->assertSame('Όλες οι έγνοιες μικραίνουν στην παραλία!', $title('el'));
        $this->assertSame(['button' => 'Explore'], json_decode(DB::table('first_page_content_translation')->where('first_page_content_id', $hero)->where('locale', 'da')->value('metadata'), true));
        $this->assertSame(1, DB::table('first_page_content_translation')->where('first_page_content_id', $hero)->where('locale', 'pt')->count());
    }
}
