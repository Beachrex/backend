<?php

namespace Tests\Feature;

use App\Models\Beach;
use App\Models\Continent;
use App\Models\Country;
use App\Models\CulinaryOffer;
use App\Models\Status;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Owner report 2026-10-21: English option names in Greek, Cyrillic city names with Latin letters,
 * `L\'Escala`-style names and "Ražanac → Ражанац". Data fix:
 * 2026_10_21_120000_fix_untranslated_and_broken_translation_names.
 */
class TranslationNameCleanupMigrationTest extends TestCase
{
    use RefreshDatabase;

    private object $migration;

    private int $beachId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migration = require database_path('migrations/2026_10_21_120000_fix_untranslated_and_broken_translation_names.php');

        Status::insert([['id' => 1, 'name' => 'active'], ['id' => 2, 'name' => 'inactive']]);
        Continent::create(['code' => 'EU']);
        Country::create(['code' => 'HR', 'continent_code' => 'EU']);

        foreach ([78, 1514, 449] as $id) {
            DB::table('city')->insert(['id' => $id, 'code' => "c{$id}", 'country_code' => 'HR', 'status_id' => 1, 'created_at' => now(), 'updated_at' => now()]);
        }
        DB::table('city_translation')->insert([
            ['city_id' => 78, 'locale' => 'ua', 'name' => 'Стариград Пакleniця', 'slug' => 'starigrad'],
            ['city_id' => 1514, 'locale' => 'ua', 'name' => 'Міддelкерке', 'slug' => 'middelkerke'],
            ['city_id' => 449, 'locale' => 'en', 'name' => "L\\'Escala", 'slug' => 'l-escala'],
            ['city_id' => 449, 'locale' => 'sl', 'name' => "L\\'Escala", 'slug' => 'l-escala'],
            // Same broken text on a different city: not in the fix list, must stay as it is.
            ['city_id' => 449, 'locale' => 'ua', 'name' => 'Стариград Пакleniця', 'slug' => 'x'],
        ]);

        $offer = CulinaryOffer::create(['code' => 'beach_bar', 'status_id' => 1, 'show_filter' => true, 'filter_order' => 1, 'input_type' => 'checkbox']);
        DB::table('culinary_offer_translation')->insert([
            ['culinary_offer_id' => $offer->id, 'locale' => 'el', 'name' => 'Beach bar', 'slug' => 'beach-bar'],
            ['culinary_offer_id' => $offer->id, 'locale' => 'en', 'name' => 'Beach bar', 'slug' => 'beach-bar'],
        ]);

        $this->beachId = Beach::factory()->create(['country_code' => 'HR'])->id;
        DB::table('beach_translation')->insert([
            ['beach_id' => $this->beachId, 'locale' => 'ua', 'name' => 'Ražanac → Ражанац', 'slug' => 'beach-razanac'],
            ['beach_id' => $this->beachId, 'locale' => 'en', 'name' => "Cap-d\\'Ail beach", 'slug' => 'cap'],
        ]);
    }

    private function nameOf(string $table, string $fk, int $id, string $locale): ?string
    {
        return DB::table($table)->where($fk, $id)->where('locale', $locale)->value('name');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_fixes_the_listed_names_and_nothing_else(): void
    {
        $this->migration->up();

        $this->assertSame('Стариград Пакленіця', $this->nameOf('city_translation', 'city_id', 78, 'ua'));
        $this->assertSame('Мідделкерке', $this->nameOf('city_translation', 'city_id', 1514, 'ua'));
        $this->assertSame("L'Escala", $this->nameOf('city_translation', 'city_id', 449, 'en'));
        $this->assertSame("L'Escala", $this->nameOf('city_translation', 'city_id', 449, 'sl'));
        $this->assertSame('Стариград Пакleniця', $this->nameOf('city_translation', 'city_id', 449, 'ua'), 'other city untouched');

        $offerId = CulinaryOffer::first()->id;
        $this->assertSame('Μπαρ παραλίας', $this->nameOf('culinary_offer_translation', 'culinary_offer_id', $offerId, 'el'));
        $this->assertSame('Beach bar', $this->nameOf('culinary_offer_translation', 'culinary_offer_id', $offerId, 'en'), 'English untouched');

        $this->assertSame('Ражанац', $this->nameOf('beach_translation', 'beach_id', $this->beachId, 'ua'));
        $this->assertSame("Cap-d'Ail beach", $this->nameOf('beach_translation', 'beach_id', $this->beachId, 'en'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function running_it_twice_changes_nothing_more(): void
    {
        $this->migration->up();
        $before = DB::table('city_translation')->orderBy('id')->pluck('name')->all();

        $this->migration->up();

        $this->assertSame($before, DB::table('city_translation')->orderBy('id')->pluck('name')->all());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function every_fixed_cyrillic_or_greek_name_has_no_latin_letters(): void
    {
        $nonLatin = ['ua', 'ru', 'bg', 'el'];
        $fixes = array_merge(
            array_map(fn ($f) => [$f[1], $f[3]], $this->migration::OPTION_FIXES),
            array_map(fn ($f) => [$f[1], $f[3]], $this->migration::CITY_FIXES),
            array_map(fn ($f) => [$f[0], $f[2]], $this->migration::BEACH_FIXES),
        );

        foreach ($fixes as [$locale, $new]) {
            if (in_array($locale, $nonLatin, true)) {
                // "WiFi" is a brand and stays Latin.
                $this->assertDoesNotMatchRegularExpression('/[A-Za-z]/', str_replace('WiFi', '', $new), "{$locale}: {$new}");
            }
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function down_restores_the_listed_names(): void
    {
        $this->migration->up();
        $this->migration->down();

        $this->assertSame('Стариград Пакleniця', $this->nameOf('city_translation', 'city_id', 78, 'ua'));
        $this->assertSame('Ražanac → Ражанац', $this->nameOf('beach_translation', 'beach_id', $this->beachId, 'ua'));
    }
}
