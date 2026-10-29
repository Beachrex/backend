<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Owner 2026-10-23: old beach descriptions (8 languages) were mostly not imported. */
class ImportOldBeachDescriptionsTest extends TestCase
{
    use RefreshDatabase;

    private function beach(int $id, array $rows): void
    {
        DB::table('status')->insertOrIgnore([['id' => 1, 'name' => 'active']]);
        DB::table('countries')->insertOrIgnore(['code' => 'GR']);
        DB::table('beach')->insert(['id' => $id, 'name' => 'x', 'country_code' => 'GR', 'status_id' => 1, 'latitude' => 40, 'longitude' => 25]);
        foreach ($rows as $locale => $description) {
            DB::table('beach_translation')->insert(['beach_id' => $id, 'locale' => $locale, 'name' => 'Paralia Astir', 'slug' => 'paralia-astir', 'description' => $description]);
        }
    }

    private function description(int $id, string $locale): ?string
    {
        return DB::table('beach_translation')->where('beach_id', $id)->where('locale', $locale)->value('description');
    }

    #[Test]
    public function the_json_is_clean_plain_text(): void
    {
        $texts = json_decode((string) file_get_contents(base_path('database/legacy/old-beach-descriptions.json')), true);

        $this->assertCount(793, $texts);
        $this->assertStringStartsWith('Dobrodošli na plaži Paralia Astir.', $texts['3388']['sl']);
        foreach ($texts as $byLocale) {
            foreach ($byLocale as $locale => $text) {
                $this->assertContains($locale, ['en', 'de', 'it', 'fr', 'es', 'sl', 'hr', 'ru']);
                $this->assertDoesNotMatchRegularExpression('/<[a-z\/]|&[a-z#0-9]+;/i', $text);
            }
        }
    }

    #[Test]
    public function empty_and_english_copies_are_filled_written_texts_stay(): void
    {
        // Beach 3388 has old texts in en, it, fr, es, de, sl, hr, ru.
        $this->beach(3388, [
            'en' => 'Our own English text.',
            'de' => 'Our own English text.',   // copy of English → replaced
            'sl' => null,                      // empty → filled
            'fr' => 'Texte écrit à la main.',  // written → stays
        ]);

        $migration = require database_path('migrations/2026_10_23_160000_import_old_beach_descriptions.php');
        $migration->up();
        $migration->up(); // idempotent

        $this->assertSame('Our own English text.', $this->description(3388, 'en'));
        $this->assertStringStartsWith('Willkommen am Strand Paralia Astir.', $this->description(3388, 'de'));
        $this->assertStringStartsWith('Dobrodošli na plaži Paralia Astir.', $this->description(3388, 'sl'));
        $this->assertSame('Texte écrit à la main.', $this->description(3388, 'fr'));
        $this->assertStringStartsWith('Добро пожаловать', $this->description(3388, 'ru'), 'a missing language row is created');
        $this->assertSame('paralia-astir', DB::table('beach_translation')->where('beach_id', 3388)->where('locale', 'ru')->value('slug'));
        $this->assertSame(1, DB::table('beach_translation')->where('beach_id', 3388)->where('locale', 'ru')->count());
    }

    #[Test]
    public function an_empty_english_description_is_filled(): void
    {
        $this->beach(3798, ['en' => null, 'de' => null]);

        (require database_path('migrations/2026_10_23_160000_import_old_beach_descriptions.php'))->up();

        $this->assertStringStartsWith('One of the very first beaches', $this->description(3798, 'en'));
        $this->assertStringStartsWith('Einer der ersten Strände', $this->description(3798, 'de'));
    }
}
