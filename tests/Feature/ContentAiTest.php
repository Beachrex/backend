<?php

namespace Tests\Feature;

use App\Http\Middleware\SetLocaleFromHeader;
use App\Services\AI\Content\BeachContentAi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Data quality pass (owner 2026-10-09): beach names without the import's English "Beach", region and
 * description translations, generated descriptions. Writes keep slugs and never overwrite human text.
 */
class ContentAiTest extends TestCase
{
    use RefreshDatabase;

    private BeachContentAi $ai;

    protected function setUp(): void
    {
        parent::setUp();
        DB::table('status')->insertOrIgnore([['id' => 1, 'name' => 'active'], ['id' => 2, 'name' => 'inactive']]);
        DB::table('countries')->insertOrIgnore(['code' => 'HR']);
        $this->ai = app(BeachContentAi::class);
    }

    /** @param array<string, array{0: string, 1?: ?string}> $rows locale => [name, description] */
    private function beach(array $rows, ?int $regionId = null, int $status = 1): int
    {
        $id = DB::table('beach')->insertGetId([
            'name' => 'x', 'country_code' => 'HR', 'status_id' => $status, 'latitude' => 45, 'longitude' => 14, 'region_id' => $regionId,
        ]);
        foreach ($rows as $locale => [$name, $description]) {
            DB::table('beach_translation')->insert([
                'beach_id' => $id, 'locale' => $locale, 'name' => $name, 'slug' => 'beach-slanica', 'description' => $description,
            ]);
        }

        return $id;
    }

    private function translation(int $beachId, string $locale): ?object
    {
        return DB::table('beach_translation')->where('beach_id', $beachId)->where('locale', $locale)->first();
    }

    private function namesAnswer(string $local = 'Slanica'): string
    {
        return json_encode(['local_name' => $local, 'ua' => 'Сланиця', 'ru' => 'Сланица', 'bg' => 'Сланица', 'el' => 'Σλάνιτσα']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function every_supported_locale_has_a_language_name_for_the_prompts(): void
    {
        $this->assertEqualsCanonicalizing(SetLocaleFromHeader::SUPPORTED_LOCALES, array_keys(BeachContentAi::LANGUAGE_NAMES));
        $this->assertSame([], array_diff(BeachContentAi::NON_LATIN, SetLocaleFromHeader::SUPPORTED_LOCALES));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function names_drop_beach_keep_slugs_and_human_names(): void
    {
        $id = $this->beach(['en' => ['Beach Slanica', 'Sandy bay.'], 'sl' => ['Beach Slanica', null], 'de' => ['Strand Slanica', null]]);

        $dry = $this->ai->apply("names-{$id}", $this->namesAnswer(), dryRun: true);
        $this->assertSame('written', $dry['status']);
        $this->assertContains('en: "Beach Slanica" -> "Slanica"', $dry['changes']);
        $this->assertSame('Beach Slanica', $this->translation($id, 'en')->name, 'dry run writes nothing');

        $this->assertSame('written', $this->ai->apply("names-{$id}", $this->namesAnswer(), dryRun: false)['status']);
        $this->assertSame('Slanica', $this->translation($id, 'en')->name);
        $this->assertSame('Sandy bay.', $this->translation($id, 'en')->description);
        $this->assertSame('Slanica', $this->translation($id, 'sl')->name);
        $this->assertSame('Strand Slanica', $this->translation($id, 'de')->name, 'a name a person wrote stays');
        $this->assertSame('Сланица', $this->translation($id, 'ru')->name);
        $this->assertSame('Slanica', $this->translation($id, 'fr')->name, 'missing languages get a row');
        $this->assertSame('beach-slanica', $this->translation($id, 'fr')->slug, 'new rows reuse the slug: URLs do not change');
        $this->assertNull($this->translation($id, 'fr')->description, 'null falls back to the English description');
        $this->assertSame(19, DB::table('beach_translation')->where('beach_id', $id)->count());

        $this->assertSame('skipped', $this->ai->apply("names-{$id}", $this->namesAnswer(), dryRun: false)['status'], 'second run changes nothing');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function a_wrong_or_still_english_name_is_refused(): void
    {
        $id = $this->beach(['en' => ['Beach Slanica', null]]);

        $this->assertSame('invalid', $this->ai->apply("names-{$id}", $this->namesAnswer('Zlatni Rat'), dryRun: false)['status']);
        $this->assertSame('invalid', $this->ai->apply("names-{$id}", $this->namesAnswer('Slanica Beach'), dryRun: false)['status']);
        $this->assertSame('invalid', $this->ai->apply("names-{$id}", 'not json', dryRun: false)['status']);
        $this->assertSame('invalid', $this->ai->apply('other-1', $this->namesAnswer(), dryRun: false)['status']);
        $this->assertSame('Beach Slanica', $this->translation($id, 'en')->name);
        $this->assertSame(1, DB::table('beach_translation')->where('beach_id', $id)->count());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function regions_fill_missing_and_untranslated_languages_only(): void
    {
        $region = DB::table('region')->insertGetId(['code' => 'dalmatia-sibenik', 'country_code' => 'HR', 'status_id' => 1]);
        DB::table('region_translation')->insert([
            ['region_id' => $region, 'locale' => 'en', 'name' => 'Dalmatia - Šibenik', 'slug' => 'dalmatia-sibenik'],
            ['region_id' => $region, 'locale' => 'sl', 'name' => 'Dalmatia - Šibenik', 'slug' => 'dalmatia-sibenik'],
            ['region_id' => $region, 'locale' => 'hr', 'name' => 'Šibenska Dalmacija', 'slug' => 'dalmatia-sibenik'],
        ]);
        $this->beach(['en' => ['Beach Slanica', null]], $region);
        $this->assertSame([$region], $this->ai->regionsForTranslation()->pluck('id')->all());

        $answer = [];
        foreach (BeachContentAi::LANGUAGE_NAMES as $locale => $_) {
            $answer[$locale] = $locale === 'de' ? 'Dalmatien - Šibenik' : 'Dalmacija - Šibenik';
        }
        $this->ai->apply("regions-{$region}", json_encode($answer), dryRun: false);

        $name = fn ($l) => DB::table('region_translation')->where('region_id', $region)->where('locale', $l)->first();
        $this->assertSame('Dalmatia - Šibenik', $name('en')->name, 'English stays');
        $this->assertSame('Dalmacija - Šibenik', $name('sl')->name);
        $this->assertSame('Šibenska Dalmacija', $name('hr')->name, 'a name a person wrote stays');
        $this->assertSame('Dalmatien - Šibenik', $name('de')->name);
        $this->assertSame('dalmatia-sibenik', $name('de')->slug, 'URLs do not change');
        $this->assertSame([], $this->ai->regionsForTranslation()->pluck('id')->all(), 'done regions are not selected again');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function descriptions_are_added_only_where_a_language_has_none(): void
    {
        $id = $this->beach(['en' => ['Beach Slanica', 'Sandy bay.'], 'hr' => ['Slanica', ' '], 'de' => ['Slanica', 'Von Hand.']]);
        $this->assertSame([$id], $this->ai->beachesForDescriptionTranslation()->pluck('id')->all());

        $request = $this->ai->request('descriptions', \App\Models\Beach::find($id), 'claude-opus-5-5', 'low');
        $schema = $request['params']['outputConfig']['format']['schema'];
        $this->assertNotContains('en', $schema['required']);
        $this->assertNotContains('de', $schema['required']);
        $this->assertContains('hr', $schema['required']);

        $answer = array_map(fn ($l) => "Text {$l}.", array_combine(BeachContentAi::locales(), BeachContentAi::locales()));
        $this->ai->apply("descriptions-{$id}", json_encode($answer), dryRun: false);

        $this->assertSame('Sandy bay.', $this->translation($id, 'en')->description);
        $this->assertSame('Von Hand.', $this->translation($id, 'de')->description);
        $this->assertSame('Text hr.', $this->translation($id, 'hr')->description);
        $this->assertSame('Text it.', $this->translation($id, 'it')->description);
        $this->assertSame('Beach Slanica', $this->translation($id, 'it')->name, 'new rows copy the source name');
        $this->assertSame([], $this->ai->beachesForDescriptionTranslation()->pluck('id')->all());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function selection_per_part(): void
    {
        $prefixed = $this->beach(['en' => ['Beach Slanica', 'Text.']]);
        $clean = $this->beach(['en' => ['Slanica', null]]);
        $this->beach(['en' => ['Beach Hidden', null]], status: 2);

        $this->assertSame([$prefixed], $this->ai->beachesForNames()->pluck('id')->all());
        $this->assertSame([$clean], $this->ai->beachesForGeneration()->pluck('id')->all());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function requests_use_structured_output_and_one_id_per_item(): void
    {
        $id = $this->beach(['en' => ['Beach Slanica', null]]);
        $beach = \App\Models\Beach::find($id);

        $names = $this->ai->request('names', $beach, 'claude-opus-5-5', 'low');
        $this->assertSame("names-{$id}", $names['customID']);
        $this->assertSame('claude-opus-5-5', $names['params']['model']);
        $this->assertSame('low', $names['params']['outputConfig']['effort']);
        $this->assertSame(['local_name', ...BeachContentAi::NON_LATIN], $names['params']['outputConfig']['format']['schema']['required']);
        $this->assertStringContainsString('Beach Slanica', $names['params']['messages'][0]['content']);

        $generate = $this->ai->request('generate', $beach, 'claude-opus-5-5', 'low');
        $this->assertSame(BeachContentAi::locales(), $generate['params']['outputConfig']['format']['schema']['properties']['texts']['items']['properties']['locale']['enum']);
        $this->assertStringContainsString('"name":"Slanica"', $generate['params']['messages'][0]['content'], 'facts use the name without "Beach"');

        $this->assertNull($this->ai->request('descriptions', $beach, 'claude-opus-5-5', 'low'), 'no text to translate');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function plan_counts_without_calling_the_api(): void
    {
        $this->beach(['en' => ['Beach Slanica', null]]);

        $this->artisan('content:ai', ['action' => 'plan'])
            ->expectsOutputToContain('Nothing is sent and nothing is written')
            ->assertSuccessful();
        $this->artisan('content:ai', ['action' => 'submit'])->assertExitCode(2);
        $this->artisan('content:ai', ['action' => 'apply'])->assertExitCode(2);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function generated_descriptions_get_per_language_names_and_varied_seo_instructions(): void
    {
        $id = $this->beach(['en' => ['Beach Slanica', null], 'ru' => ['Сланица', null]]);
        $request = $this->ai->request('generate', \App\Models\Beach::find($id), 'claude-opus-5-5', 'low');
        $facts = json_decode($request['params']['messages'][0]['content'], true);

        $this->assertSame('Сланица', $facts['name_by_language']['ru']);
        $this->assertSame('Slanica', $facts['name_by_language']['de'], 'languages without a row use the source name without "Beach"');
        $this->assertStringContainsString('at most 155 characters', $request['params']['system']);
        $this->assertStringContainsString('only when you are certain', $request['params']['system']);
        $this->assertStringContainsString('never open with a generic', $request['params']['system']);
        $this->assertStringContainsString('never turn the distance into a depth', $request['params']['system']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function full_preview_shows_whole_descriptions(): void
    {
        $id = $this->beach(['en' => ['Beach Slanica', null]]);
        $long = str_repeat('Sand and pines. ', 20);
        $answer = json_encode(array_fill_keys(BeachContentAi::locales(), ['meta' => 'Short.', 'description' => $long, 'faq' => []]));

        $short = $this->ai->apply("generate-{$id}", $answer, dryRun: true);
        $full = $this->ai->apply("generate-{$id}", $answer, dryRun: true, previewChars: PHP_INT_MAX);
        $this->assertLessThan(100, mb_strlen($short['changes'][0]));
        $this->assertSame('en: '.trim($long), $full['changes'][0]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function submit_refuses_a_part_whose_last_batch_was_not_applied(): void
    {
        \Illuminate\Support\Facades\Cache::forever(\App\Console\Commands\ContentAi::pendingKey('names'), ['msgbatch_1']);

        $this->artisan('content:ai', ['action' => 'submit', '--part' => ['names']])
            ->expectsOutputToContain('not applied yet: msgbatch_1')
            ->assertFailed();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function the_1m_depth_code_becomes_a_sentence_the_model_cannot_read_as_a_depth(): void
    {
        // Owner preview 2026-10-09: "5m" was written as "5 m deep a metre from the shore".
        $this->assertSame('The water becomes 1 m deep about 5 m from the shore.', $this->ai->depthSentence('5m'));
        $this->assertStringContainsString('good for small children', $this->ai->depthSentence('50m'));
        $this->assertStringContainsString('right at the shore', $this->ai->depthSentence('0m'));
        $this->assertSame('The water becomes 1 m deep somewhere within the first 10 m from the shore.', $this->ai->depthSentence('0m_10m'));
        $this->assertStringContainsString('more than 100 m', $this->ai->depthSentence('more_100m'));
        $this->assertNull($this->ai->depthSentence(null));
        $this->assertNull($this->ai->depthSentence('unknown'));
    }
}
