<?php

namespace Tests\Feature;

use App\Models\Beach;
use App\Services\AI\Content\BeachContentAi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * TASK-007 (owner 2026-10-21): generated texts were one template with swapped values. Now: search snippet,
 * an intro without logistics, FAQs, computed context from our own data, varied openings, two tiers.
 * Every field is written only where the language has none.
 */
class ContentAiSeoTest extends TestCase
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
    private function beach(array $rows, float $lat = 44.35, float $lng = 15.10, ?int $cityId = null, int $views = 0, int $status = 1): int
    {
        $id = DB::table('beach')->insertGetId([
            'name' => 'x', 'country_code' => 'HR', 'status_id' => $status, 'latitude' => $lat, 'longitude' => $lng,
            'city_id' => $cityId, 'beach_views' => $views,
        ]);
        foreach ($rows as $locale => [$name, $description]) {
            DB::table('beach_translation')->insert([
                'beach_id' => $id, 'locale' => $locale, 'name' => $name, 'slug' => 'beach-'.$id, 'description' => $description,
            ]);
        }

        return $id;
    }

    private function city(string $name, float $lat, float $lng): int
    {
        $id = DB::table('city')->insertGetId(['code' => strtolower($name), 'country_code' => 'HR', 'status_id' => 1, 'latitude' => $lat, 'longitude' => $lng]);
        DB::table('city_translation')->insert(['city_id' => $id, 'locale' => 'en', 'name' => $name, 'slug' => strtolower($name)]);

        return $id;
    }

    private function row(int $beachId, string $locale): ?object
    {
        return DB::table('beach_translation')->where('beach_id', $beachId)->where('locale', $locale)->first();
    }

    /** @param array<string, mixed> $overrides locale => answer */
    private function answer(bool $withDescription = true, array $overrides = []): string
    {
        $answer = [];
        foreach (BeachContentAi::locales() as $l) {
            $answer[$l] = array_filter([
                'meta' => "Sandy beach in Povljana ({$l}).",
                'description' => $withDescription ? "Intro {$l}." : null,
                'faq' => [['q' => "Is Slanica good for children ({$l})?", 'a' => 'Yes, Slanica is shallow.'], ['q' => 'Is there shade at Slanica?', 'a' => 'Yes, pines give shade at Slanica.']],
            ], fn ($v) => $v !== null);
        }

        return json_encode(array_replace($answer, $overrides));
    }

    #[Test]
    public function generate_asks_for_snippet_intro_and_faq_per_language(): void
    {
        $id = $this->beach(['en' => ['Beach Slanica', null]]);
        $request = $this->ai->request('generate', Beach::find($id), 'claude-opus-5-5', 'low');
        $schema = $request['params']['outputConfig']['format']['schema'];

        $this->assertSame(BeachContentAi::locales(), $schema['required']);
        $this->assertSame(['meta', 'description', 'faq'], $schema['properties']['sl']['required']);
        $this->assertSame(['q', 'a'], $schema['properties']['sl']['properties']['faq']['items']['required']);
        $system = $request['params']['system'];
        $this->assertStringContainsString('Leave out logistics', $system, 'parking, road and transport go to the FAQ, not the intro');
        $this->assertStringContainsString('starts with the direct answer', $system);
        $this->assertStringContainsString('Ask only questions the facts answer', $system);
        $this->assertStringContainsString('50-80 words', $system);
        $this->assertSame(32000, $request['params']['maxTokens']);
    }

    #[Test]
    public function openings_vary_by_beach(): void
    {
        $angles = [];
        foreach (range(1, 4) as $i) {
            $id = $this->beach(['en' => ["Beach {$i}", null]]);
            $facts = json_decode($this->ai->request('generate', Beach::find($id), 'm', 'low')['params']['messages'][0]['content'], true);
            $angles[] = $facts['opening_angle'];
        }

        $this->assertCount(4, array_unique($angles), 'four neighbouring beaches get four different opening angles');
        $this->assertSame($angles[0], json_decode($this->ai->request('generate', Beach::find($id - 3), 'm', 'low')['params']['messages'][0]['content'], true)['opening_angle'], 'stable per beach');
    }

    #[Test]
    public function the_most_viewed_beaches_get_the_longer_featured_text(): void
    {
        config(['ai.content.featured' => 1]);
        $top = $this->beach(['en' => ['Top', null]], views: 900);
        $other = $this->beach(['en' => ['Other', null]], views: 10);

        $this->assertStringContainsString('90-150 words', $this->ai->request('generate', Beach::find($top), 'm', 'low')['params']['system']);
        $this->assertStringContainsString('50-80 words', $this->ai->request('generate', Beach::find($other), 'm', 'low')['params']['system']);
    }

    #[Test]
    public function facts_carry_computed_town_and_nearby_context(): void
    {
        $city = $this->city('Povljana', 44.3500, 15.1000);
        $id = $this->beach(['en' => ['Beach Livade', null]], 44.3500, 15.1150, $city); // ~1.2 km east of the centre
        $near = $this->beach(['en' => ['Beach Dubrava', null], 'ru' => ['Дубрава', null]], 44.3520, 15.1200, $city);
        $this->beach(['en' => ['Far', null]], 45.3500, 15.1000); // ~111 km
        $this->beach(['en' => ['Hidden', null]], 44.3501, 15.1151, status: 2);

        $facts = $this->ai->facts(Beach::find($id));

        $this->assertSame('about 1.2 km from the centre of Povljana', $facts['town_centre']);
        $this->assertSame('one of 2 beaches in Povljana listed on Beachrex', $facts['beaches_in_town']);
        $this->assertCount(1, $facts['nearest_beaches'], 'only active beaches within 10 km');
        $this->assertSame('Dubrava', $facts['nearest_beaches'][0]['name']);
        $this->assertSame(['ru' => 'Дубрава'], $facts['nearest_beaches'][0]['name_in_script']);
        $this->assertSame('about 500 m', $facts['nearest_beaches'][0]['distance']);
        $this->assertNotContains($near, [$id]);
    }

    #[Test]
    public function generate_writes_snippet_intro_and_faq_only_where_empty(): void
    {
        $id = $this->beach(['en' => ['Beach Slanica', null], 'sl' => ['Slanica', null]]);
        DB::table('beach_translation')->where('beach_id', $id)->where('locale', 'sl')->update([
            'meta_description' => 'Ročno napisan opis.', 'faq' => json_encode([['q' => 'Ročno?', 'a' => 'Da.']]),
        ]);

        $dry = $this->ai->apply("generate-{$id}", $this->answer(), dryRun: true);
        $this->assertSame('written', $dry['status']);
        $this->assertContains('en: [meta] Sandy beach in Povljana (en).', $dry['changes']);
        $this->assertNull($this->row($id, 'en')->meta_description, 'dry run writes nothing');

        $this->ai->apply("generate-{$id}", $this->answer(), dryRun: false);
        $en = $this->row($id, 'en');
        $this->assertSame('Intro en.', $en->description);
        $this->assertSame('Sandy beach in Povljana (en).', $en->meta_description);
        $this->assertSame('Yes, Slanica is shallow.', json_decode($en->faq, true)[0]['a']);
        $this->assertSame('Ročno napisan opis.', $this->row($id, 'sl')->meta_description, 'a person\'s snippet stays');
        $this->assertSame('Ročno?', json_decode($this->row($id, 'sl')->faq, true)[0]['q'], 'a person\'s FAQ stays');
        $this->assertSame('Intro sl.', $this->row($id, 'sl')->description);
        $this->assertSame('beach-'.$id, $this->row($id, 'fr')->slug, 'new rows keep the slug');
        $this->assertSame([], $this->ai->beachesForGeneration()->pluck('id')->all());
        $this->assertSame([], $this->ai->beachesForSeo()->pluck('id')->all(), 'nothing left for the seo part');
    }

    #[Test]
    public function a_far_too_long_snippet_or_a_thin_faq_is_left_out_and_retried_by_the_seo_part(): void
    {
        $id = $this->beach(['en' => ['Beach Slanica', null]]);
        $answer = $this->answer(overrides: ['de' => [
            'meta' => str_repeat('Lang. ', 40), 'description' => 'Einleitung.', 'faq' => [['q' => 'Nur eine?', 'a' => 'Ja.'], ['q' => '', 'a' => 'leer']],
        ]]);

        $result = $this->ai->apply("generate-{$id}", $answer, dryRun: false);

        $this->assertContains('de: [meta left out: 239 characters]', $result['changes']);
        $this->assertContains('de: [faq left out: fewer than two valid questions]', $result['changes']);
        $this->assertSame('Einleitung.', $this->row($id, 'de')->description);
        $this->assertNull($this->row($id, 'de')->meta_description);
        $this->assertNull($this->row($id, 'de')->faq);
        $this->assertSame([$id], $this->ai->beachesForSeo()->pluck('id')->all());
    }

    #[Test]
    public function seo_part_uses_the_existing_description_and_never_changes_it(): void
    {
        $id = $this->beach(['en' => ['Slanica', 'A sandy bay with pines, written by hand.'], 'de' => ['Slanica', 'Von Hand.']]);
        $this->beach(['en' => ['No text', null]]);
        $this->assertSame([$id], $this->ai->beachesForSeo()->pluck('id')->all(), 'only beaches with a description');

        $request = $this->ai->request('seo', Beach::find($id), 'claude-opus-5-5', 'low');
        $facts = json_decode($request['params']['messages'][0]['content'], true);
        $this->assertSame('A sandy bay with pines, written by hand.', $facts['description']);
        $this->assertSame(['meta', 'faq'], $request['params']['outputConfig']['format']['schema']['properties']['en']['required']);

        $answer = $this->answer(withDescription: false, overrides: ['en' => ['meta' => 'Snippet.', 'description' => 'NEW', 'faq' => [['q' => 'A?', 'a' => 'B.'], ['q' => 'C?', 'a' => 'D.']]]]);
        $this->ai->apply("seo-{$id}", $answer, dryRun: false);

        $this->assertSame('A sandy bay with pines, written by hand.', $this->row($id, 'en')->description);
        $this->assertSame('Snippet.', $this->row($id, 'en')->meta_description);
        $this->assertSame('Von Hand.', $this->row($id, 'de')->description);
        $this->assertNull($this->row($id, 'it')->description, 'seo never writes a description');
        $this->assertSame([], $this->ai->beachesForSeo()->pluck('id')->all());
    }

    #[Test]
    public function the_api_returns_snippet_and_faq_of_the_requested_language_only(): void
    {
        $id = $this->beach(['en' => ['Slanica', 'Text.'], 'sl' => ['Slanica', 'Besedilo.']]);
        DB::table('beach_translation')->where('beach_id', $id)->where('locale', 'en')->update([
            'meta_description' => 'English snippet.', 'faq' => json_encode([['q' => 'Q?', 'a' => 'A.'], ['q' => '', 'a' => 'broken']]),
        ]);

        $this->getJson("/api/v1/beach-details/{$id}", ['Accept-Language' => 'en'])
            ->assertOk()
            ->assertJsonPath('data.basic_info.meta_description', 'English snippet.')
            ->assertJsonPath('data.basic_info.faq', [['q' => 'Q?', 'a' => 'A.']]);

        $this->getJson("/api/v1/beach-details/{$id}", ['Accept-Language' => 'sl'])
            ->assertOk()
            ->assertJsonPath('data.basic_info.meta_description', null)
            ->assertJsonPath('data.basic_info.faq', []);
    }

    #[Test]
    public function plan_includes_the_seo_part(): void
    {
        $this->beach(['en' => ['Slanica', 'Text.']]);

        $this->artisan('content:ai', ['action' => 'plan', '--part' => ['seo']])
            ->expectsOutputToContain('seo')
            ->assertSuccessful();
    }
}
