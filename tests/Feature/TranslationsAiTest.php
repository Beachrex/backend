<?php

namespace Tests\Feature;

use App\Console\Commands\TranslationsAi;
use App\Models\BeachType;
use App\Models\BeachTypeTranslation;
use App\Support\TranslationTables;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Owner 2026-10-12: fill missing reference translations (Greek showed "small_stones", Albanian English names). */
class TranslationsAiTest extends TestCase
{
    use RefreshDatabase;

    private TranslationsAi $ai;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([\Database\Seeders\StatusSeeder::class]);
        $this->ai = app(TranslationsAi::class);
    }

    private function table(string $name): array
    {
        return collect(TranslationTables::all([$name]))->first();
    }

    private function smallStones(): BeachType
    {
        $type = BeachType::create(['code' => 'small_stones', 'status_id' => 1]);
        BeachTypeTranslation::create(['beach_type_id' => $type->id, 'locale' => 'en', 'name' => 'Small Stones', 'slug' => 'small-stones', 'filter_title' => 'Beach type']);
        BeachTypeTranslation::create(['beach_type_id' => $type->id, 'locale' => 'sl', 'name' => 'Drobni kamenčki', 'slug' => 'small-stones']);
        BeachTypeTranslation::create(['beach_type_id' => $type->id, 'locale' => 'sq', 'name' => 'Small Stones', 'slug' => 'small-stones']);

        return $type;
    }

    public function test_selects_missing_and_english_copies_but_not_human_translations(): void
    {
        $type = $this->smallStones();
        $work = $this->ai->work($this->table('beach_type_translation'))->first();

        $this->assertSame((string) $type->id, $work['key']);
        $this->assertSame(['name' => 'Small Stones', 'filter_title' => 'Beach type'], $work['en']);
        $this->assertContains('el', $work['locales']);
        $this->assertContains('sq', $work['locales']);
        $this->assertNotContains('sl', $work['locales']);
    }

    public function test_apply_writes_new_rows_with_the_english_slug_and_fixes_english_copies(): void
    {
        $type = $this->smallStones();
        $answer = ['key' => (string) $type->id, 'sl' => ['name' => 'X', 'filter_title' => 'X']];
        foreach (['de', 'hr', 'fr', 'es', 'it', 'ua', 'ru', 'pl', 'cs', 'hu', 'bg', 'sk', 'da', 'pt', 'nl'] as $l) {
            $answer[$l] = ['name' => "Small Stones {$l}", 'filter_title' => "Beach type {$l}"];
        }
        $answer['el'] = ['name' => 'Μικρά βότσαλα', 'filter_title' => 'Τύπος παραλίας'];
        $answer['sq'] = ['name' => 'Gurë të vegjël', 'filter_title' => 'Lloji i plazhit'];
        $json = json_encode(['items' => [$answer]]);

        $dry = $this->ai->apply('tr-beach_type_translation-0', $json, true);
        $this->assertSame('written', $dry['status']);
        $this->assertSame(0, DB::table('beach_type_translation')->where('locale', 'el')->count());

        $this->ai->apply('tr-beach_type_translation-0', $json, false);

        $el = DB::table('beach_type_translation')->where('beach_type_id', $type->id)->where('locale', 'el')->first();
        $this->assertSame('Μικρά βότσαλα', $el->name);
        $this->assertSame('small-stones', $el->slug);
        $this->assertSame('Τύπος παραλίας', $el->filter_title);
        $this->assertSame('Gurë të vegjël', DB::table('beach_type_translation')->where('beach_type_id', $type->id)->where('locale', 'sq')->value('name'));
        $this->assertSame('Drobni kamenčki', DB::table('beach_type_translation')->where('beach_type_id', $type->id)->where('locale', 'sl')->value('name'));
        $this->assertTrue($this->ai->work($this->table('beach_type_translation'))->isEmpty());
    }

    public function test_countries_are_keyed_by_code(): void
    {
        DB::table('continents')->insertOrIgnore(['code' => 'EU']);
        DB::table('countries')->insertOrIgnore(['code' => 'HR', 'continent_code' => 'EU']);
        DB::table('country_translation')->where('country_code', 'HR')->delete();
        DB::table('country_translation')->insert([
            ['country_code' => 'HR', 'locale' => 'en', 'name' => 'Croatia', 'full_name' => 'Croatia', 'slug' => 'croatia'],
            ['country_code' => 'HR', 'locale' => 'sq', 'name' => 'Croatia', 'full_name' => 'Croatia', 'slug' => 'croatia'],
        ]);

        $answer = ['key' => 'HR'];
        foreach (array_diff(\App\Http\Middleware\SetLocaleFromHeader::SUPPORTED_LOCALES, ['en']) as $l) {
            $answer[$l] = ['name' => $l === 'sq' ? 'Kroacia' : "Croatia {$l}", 'full_name' => $l === 'sq' ? 'Republika e Kroacisë' : "Croatia {$l}"];
        }
        $this->ai->apply('tr-country_translation-0', json_encode(['items' => [$answer]]), false);

        $sq = DB::table('country_translation')->where('country_code', 'HR')->where('locale', 'sq')->first();
        $this->assertSame('Kroacia', $sq->name);
        $this->assertSame('Republika e Kroacisë', $sq->full_name);
        $this->assertSame('croatia', $sq->slug);
    }

    public function test_requests_are_chunked_per_table_with_a_strict_schema(): void
    {
        $this->smallStones();
        $requests = $this->ai->requests($this->table('beach_type_translation'), 'claude-opus-5-5', 'low');

        $this->assertCount(1, $requests);
        $this->assertSame('tr-beach_type_translation-0', $requests[0]['customID']);
        $schema = $requests[0]['params']['outputConfig']['format']['schema'];
        $this->assertContains('el', $schema['properties']['items']['items']['required']);
        $this->assertNotContains('sl', $schema['properties']['items']['items']['required']);
    }

    public function test_unknown_request_ids_are_refused(): void
    {
        $this->assertSame('invalid', $this->ai->apply('tr-users-0', '{"items":[]}', true)['status']);
    }

    public function test_plan_lists_the_work_without_calling_claude(): void
    {
        $this->smallStones();
        $this->assertSame(0, \Illuminate\Support\Facades\Artisan::call('translations:ai', ['action' => 'plan', '--table' => ['beach_type_translation']]));
        $this->assertMatchesRegularExpression('/beach_type_translation\s*\|\s*1\s*\|\s*17/', \Illuminate\Support\Facades\Artisan::output());
    }

    public function test_beach_names_only_for_greek_and_cyrillic_and_without_copying_the_description(): void
    {
        DB::table('continents')->insertOrIgnore(['code' => 'EU']);
        DB::table('countries')->insertOrIgnore(['code' => 'HR', 'continent_code' => 'EU']);
        $beach = \App\Models\Beach::factory()->create(['status_id' => 1, 'country_code' => 'HR']);
        \App\Models\BeachTranslation::create(['beach_id' => $beach->id, 'locale' => 'en', 'name' => 'Zlatni rat', 'slug' => 'zlatni-rat', 'description' => 'A long English text.']);

        $work = $this->ai->work($this->table('beach_translation'))->firstWhere('key', (string) $beach->id);
        $this->assertSame(['ua', 'ru', 'bg', 'el'], $work['locales']);
        $this->assertSame('HR', $work['country']);
        $request = $this->ai->requests($this->table('beach_translation'), 'claude-opus-5-5', 'low')[0];
        $this->assertStringContainsString('"country":"HR"', $request['params']['messages'][0]['content']);

        $answer = ['key' => (string) $beach->id, 'ua' => ['name' => 'Златні рат'], 'ru' => ['name' => 'Златни-Рат'],
            'bg' => ['name' => 'Златни рат'], 'el' => ['name' => 'Ζλάτνι Ρατ']];
        $this->ai->apply('tr-beach_translation-0', json_encode(['items' => [$answer]]), false);

        $el = DB::table('beach_translation')->where('beach_id', $beach->id)->where('locale', 'el')->first();
        $this->assertSame('Ζλάτνι Ρατ', $el->name);
        $this->assertSame('zlatni-rat', $el->slug);
        $this->assertNull($el->description);
        $this->assertSame(0, DB::table('beach_translation')->where('beach_id', $beach->id)->where('locale', 'de')->count());
    }
}
