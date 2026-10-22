<?php

namespace Tests\Feature;

use App\Models\CulinaryOffer;
use App\Models\LocationInfrastructure;
use App\Models\Status;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Owner report 2026-10-22: option names in Title Case ("Drobno Kamenje", "Plažni Bar") are wrong in Slovenian
 * and most other languages. Data fix: 2026_10_22_180000_option_names_sentence_case; seeders use sentence case.
 */
class OptionNamesSentenceCaseTest extends TestCase
{
    use RefreshDatabase;

    private object $migration;

    private int $offerId;

    private int $infraId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migration = require database_path('migrations/2026_10_22_180000_option_names_sentence_case.php');

        Status::insert([['id' => 1, 'name' => 'active'], ['id' => 2, 'name' => 'inactive']]);

        $this->offerId = CulinaryOffer::create(['code' => 'beach_bar', 'status_id' => 1, 'show_filter' => true, 'filter_order' => 1, 'input_type' => 'checkbox'])->id;
        DB::table('culinary_offer_translation')->insert([
            ['culinary_offer_id' => $this->offerId, 'locale' => 'sl', 'name' => 'Plažni Bar', 'slug' => 'plazni-bar'],
            ['culinary_offer_id' => $this->offerId, 'locale' => 'de', 'name' => 'Café Bar', 'slug' => 'cafe-bar'],
            // Already corrected in admin: must stay as it is.
            ['culinary_offer_id' => $this->offerId, 'locale' => 'en', 'name' => 'Beach bar (edited)', 'slug' => 'beach-bar'],
        ]);

        $this->infraId = LocationInfrastructure::create(['code' => 'wifi', 'status_id' => 1, 'show_filter' => true, 'filter_order' => 1, 'input_type' => 'checkbox'])->id;
        DB::table('location_infrastructure_translation')->insert([
            ['location_infrastructure_id' => $this->infraId, 'locale' => 'sl', 'name' => 'WiFi Dostopna Točka', 'slug' => 'wifi'],
            ['location_infrastructure_id' => $this->infraId, 'locale' => 'fr', 'name' => "Point d'Accès WiFi", 'slug' => 'wifi'],
            // Same text in another table: only the listed table changes.
            ['location_infrastructure_id' => $this->infraId, 'locale' => 'hr', 'name' => 'Plaža Bar', 'slug' => 'x'],
        ]);
    }

    private function name(string $table, string $fk, int $id, string $locale): ?string
    {
        return DB::table($table)->where($fk, $id)->where('locale', $locale)->value('name');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_writes_listed_names_in_sentence_case_and_keeps_slugs(): void
    {
        $this->migration->up();
        $this->migration->up(); // idempotent

        $this->assertSame('Plažni bar', $this->name('culinary_offer_translation', 'culinary_offer_id', $this->offerId, 'sl'));
        $this->assertSame('Café Bar', $this->name('culinary_offer_translation', 'culinary_offer_id', $this->offerId, 'de'));
        $this->assertSame('Beach bar (edited)', $this->name('culinary_offer_translation', 'culinary_offer_id', $this->offerId, 'en'));
        $this->assertSame('WiFi dostopna točka', $this->name('location_infrastructure_translation', 'location_infrastructure_id', $this->infraId, 'sl'));
        $this->assertSame("Point d'accès WiFi", $this->name('location_infrastructure_translation', 'location_infrastructure_id', $this->infraId, 'fr'));
        $this->assertSame('Plaža Bar', $this->name('location_infrastructure_translation', 'location_infrastructure_id', $this->infraId, 'hr'));
        $this->assertSame('plazni-bar', DB::table('culinary_offer_translation')->where('locale', 'sl')->value('slug'));

        $this->migration->down();
        $this->assertSame('Plažni Bar', $this->name('culinary_offer_translation', 'culinary_offer_id', $this->offerId, 'sl'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function the_fix_list_only_lowercases_letters_and_keeps_german_nouns(): void
    {
        foreach ($this->migration::FIXES as [$table, $locale, $old, $new]) {
            if ($locale === 'hr' && $old === 'Ski lift / Wakeboard') {
                continue; // the one translation fix, see the migration's docblock
            }
            $this->assertSame(mb_strtolower($old), mb_strtolower($new), "{$table} {$locale}: only letter case may change");
            if ($locale === 'de') {
                $this->assertContains($old, ['Ins Wasser Springen', 'Unterschiedlich/Gemischt']);
            }
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function option_seeders_have_no_title_case_names_outside_german(): void
    {
        $seeders = ['BeachType', 'AccommodationAround', 'CulinaryOffer', 'LocationInfrastructure', 'NaturalLocationCondition',
            'SportingActivities', 'UnderwaterCharacteristic', 'RecommendedTransportType', 'AreaDescription', 'BeachInclination', 'UserType'];

        foreach ($seeders as $seeder) {
            $source = file_get_contents(database_path("seeders/{$seeder}Seeder.php"));
            preg_match_all("/'([a-z]{2})'\\s*=>\\s*\\[\\s*'name'\\s*=>\\s*'((?:[^'\\\\]|\\\\.)*)'/u", $source, $matches, PREG_SET_ORDER);
            $this->assertNotEmpty($matches, $seeder);

            foreach ($matches as [, $locale, $name]) {
                if ($locale === 'de') {
                    continue;
                }
                preg_match_all('/\\p{L}+/u', $name, $words);
                foreach (array_slice($words[0], 1) as $word) {
                    $rest = mb_substr($word, 1);
                    $isAcronymOrMixed = mb_strlen($word) > 1 && ($word === mb_strtoupper($word) || $rest !== mb_strtolower($rest));
                    $startsUpper = mb_substr($word, 0, 1) !== mb_strtolower(mb_substr($word, 0, 1));
                    $this->assertFalse($startsUpper && ! $isAcronymOrMixed, "{$seeder} {$locale}: \"{$name}\" ({$word})");
                }
            }
        }
    }
}
