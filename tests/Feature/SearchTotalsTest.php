<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\V1\SearchController;
use App\Models\Beach;
use App\Models\City;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Owner 2026-10-28: searching "france" showed "All 28 = Beaches 10 + Cities 10 + Regions 7 + Country 1" although
 * France has ~400 beaches and 87 towns: the tab counts were the number of items returned (`limit` 10 per type).
 * Italian beaches (Principina a Mare: "Church of San Francesco" in the description) ranked above French ones.
 * ADR 2026-10-28 "Search counts are real totals; a place name scopes the result".
 *
 * Uses Scout's collection engine (a real search over the DB) instead of the test default null engine.
 */
class SearchTotalsTest extends TestCase
{
    use RefreshDatabase;

    private int $hauts;

    private int $toscana;

    protected function setUp(): void
    {
        parent::setUp();
        config(['scout.driver' => 'collection']);

        DB::table('status')->insertOrIgnore([['id' => 1, 'name' => 'active']]);
        DB::table('countries')->insertOrIgnore([['code' => 'FR'], ['code' => 'IT']]);
        DB::table('country_translation')->insert([
            ['country_code' => 'FR', 'locale' => 'en', 'name' => 'France', 'slug' => 'france'],
            ['country_code' => 'FR', 'locale' => 'sl', 'name' => 'Francija', 'slug' => 'francija'],
            ['country_code' => 'IT', 'locale' => 'en', 'name' => 'Italy', 'slug' => 'italy'],
        ]);

        $this->hauts = DB::table('region')->insertGetId(['code' => 'hauts-de-france', 'country_code' => 'FR', 'status_id' => 1]);
        $this->toscana = DB::table('region')->insertGetId(['code' => 'toscana', 'country_code' => 'IT', 'status_id' => 1]);
        DB::table('region_translation')->insert([
            ['region_id' => $this->hauts, 'locale' => 'en', 'name' => 'Hauts-de-France', 'slug' => 'hauts-de-france'],
            ['region_id' => $this->toscana, 'locale' => 'en', 'name' => 'Toscana', 'slug' => 'toscana'],
        ]);

        // 7 French towns, 1 Italian
        $frCities = [];
        for ($i = 1; $i <= 7; $i++) {
            $frCities[] = $this->city("ville-{$i}", "Ville {$i}", 'FR', $this->hauts);
        }
        $grosseto = $this->city('grosseto', 'Grosseto', 'IT', $this->toscana);

        // 12 French beaches, 2 Italian ones whose description contains "France" as a word prefix
        for ($i = 1; $i <= 12; $i++) {
            $this->beach("Plage {$i}", 'FR', $this->hauts, $frCities[$i % 7], 'Sand and dunes.');
        }
        $this->beach('Principina a Mare', 'IT', $this->toscana, $grosseto, 'Visit the Church of San Francesco in town.');
        $this->beach('Giannella', 'IT', $this->toscana, $grosseto, 'Ferries to Isola del Giglio; Franceschini bar.');
    }

    private function city(string $code, string $name, string $country, int $region): int
    {
        $id = DB::table('city')->insertGetId(['code' => $code, 'country_code' => $country, 'region_id' => $region, 'status_id' => 1]);
        DB::table('city_translation')->insert(['city_id' => $id, 'locale' => 'en', 'name' => $name, 'slug' => $code]);

        return $id;
    }

    private function beach(string $name, string $country, int $region, int $city, string $description): int
    {
        $id = DB::table('beach')->insertGetId([
            'name' => $name, 'country_code' => $country, 'status_id' => 1, 'latitude' => 43.0, 'longitude' => 7.0,
            'region_id' => $region, 'city_id' => $city,
        ]);
        DB::table('beach_translation')->insert([
            'beach_id' => $id, 'locale' => 'en', 'name' => $name, 'slug' => str($name)->slug(), 'description' => $description,
        ]);

        return $id;
    }

    private function search(string $q, int $limit = 5, string $locale = 'en'): \Illuminate\Testing\TestResponse
    {
        return $this->withHeaders(['Accept-Language' => $locale])
            ->getJson('/api/v1/search?_nocache=1&limit=' . $limit . '&q=' . urlencode($q))
            ->assertOk();
    }

    #[Test]
    public function a_country_name_counts_all_beaches_and_towns_in_that_country_not_the_returned_items(): void
    {
        $response = $this->search('france', 5);

        $response->assertJsonCount(5, 'data.beaches')
            ->assertJsonCount(5, 'data.cities')
            ->assertJsonPath('meta.results_by_type.beaches', 5)
            ->assertJsonPath('meta.totals_by_type.beaches', 12)
            ->assertJsonPath('meta.totals_by_type.cities', 7)
            ->assertJsonPath('meta.totals_by_type.regions', 1)
            ->assertJsonPath('meta.totals_by_type.countries', 1)
            ->assertJsonPath('meta.totals_by_type.islands', 0)
            ->assertJsonPath('meta.total_matches', 21)
            ->assertJsonPath('meta.place.type', 'country')
            ->assertJsonPath('meta.place.code', 'FR')
            ->assertJsonPath('meta.place.name', 'France');
    }

    #[Test]
    public function a_country_name_never_lists_beaches_from_another_country(): void
    {
        $beaches = $this->search('france', 20)->json('data.beaches');

        $this->assertCount(12, $beaches);
        $this->assertSame(['FR'], array_values(array_unique(array_column($beaches, 'country_code'))));
        $this->assertNotContains('Principina a Mare', array_column($beaches, 'name'));
    }

    #[Test]
    public function the_place_is_recognised_in_the_visitors_language(): void
    {
        $this->search('Francija', 5, 'si')
            ->assertJsonPath('meta.place.code', 'FR')
            ->assertJsonPath('meta.place.name', 'Francija')
            ->assertJsonPath('meta.totals_by_type.beaches', 12);
    }

    #[Test]
    public function a_region_name_scopes_beaches_and_towns_to_that_region(): void
    {
        $response = $this->search('toscana', 10);

        $response->assertJsonPath('meta.place.type', 'region')
            ->assertJsonPath('meta.place.id', $this->toscana)
            ->assertJsonPath('meta.totals_by_type.beaches', 2)
            ->assertJsonPath('meta.totals_by_type.cities', 1);
        $this->assertEqualsCanonicalizing(['Principina a Mare', 'Giannella'], array_column($response->json('data.beaches'), 'name'));
    }

    #[Test]
    public function a_query_that_names_no_place_is_a_plain_search_with_its_real_total(): void
    {
        // "plage 1" matches Plage 1, 10, 11, 12
        $this->search('plage 1', 2)
            ->assertJsonPath('meta.place', null)
            ->assertJsonCount(2, 'data.beaches')
            ->assertJsonPath('meta.totals_by_type.beaches', 4)
            ->assertJsonPath('meta.total_matches', 4);
    }

    #[Test]
    public function the_limit_boundary_returns_every_item_when_the_total_equals_the_limit(): void
    {
        $this->search('france', 12)
            ->assertJsonCount(12, 'data.beaches')
            ->assertJsonPath('meta.totals_by_type.beaches', 12);
        $this->search('france', 11)
            ->assertJsonCount(11, 'data.beaches')
            ->assertJsonPath('meta.totals_by_type.beaches', 12);
    }

    #[Test]
    public function two_places_with_the_same_name_do_not_scope_the_search(): void
    {
        $a = (object) ['name' => 'San Marco', 'translations' => collect()];
        $b = (object) ['name' => 'San Marco', 'translations' => collect()];
        $c = (object) ['name' => 'Sveti Marko', 'translations' => collect([(object) ['name' => 'San Marco ']])];

        $this->assertNull(SearchController::namedPlace([$a, $b], 'san marco'));
        $this->assertSame($a, SearchController::namedPlace([$a], 'SAN MARCO'));
        $this->assertSame($c, SearchController::namedPlace([$c], 'san  marco'), 'any language, spaces and case ignored');
        $this->assertNull(SearchController::namedPlace([$a], 'san'), 'a prefix is not the place name');
        $this->assertSame('cote d\'azur', SearchController::normalizeName(' Côte  d\'Azur '));
    }

    #[Test]
    public function scope_filters_only_apply_to_types_below_the_place(): void
    {
        $country = ['type' => 'country', 'id' => 1, 'code' => 'FR'];
        $this->assertSame([], SearchController::scopeFilters($country, 'country'));
        $this->assertSame(['country_code' => 'FR'], SearchController::scopeFilters($country, 'region'));
        $this->assertSame(['country_code' => 'FR'], SearchController::scopeFilters($country, 'beach'));

        $island = ['type' => 'island', 'id' => 9, 'code' => 'hvar'];
        $this->assertSame([], SearchController::scopeFilters($island, 'region'));
        $this->assertSame(['island_id' => 9], SearchController::scopeFilters($island, 'city'));
        $this->assertSame(['city_id' => 4], SearchController::scopeFilters(['type' => 'city', 'id' => 4, 'code' => 'nice'], 'beach'));
    }

    #[Test]
    public function the_index_ranks_names_and_places_above_descriptions_and_can_filter_by_place_id(): void
    {
        $beaches = config('scout.meilisearch.index-settings.beaches');
        $attrs = $beaches['searchableAttributes'];
        foreach (['country_name', 'region_name', 'city_name', 'island_name', 'place_names'] as $place) {
            $this->assertLessThan(array_search('description', $attrs, true), array_search($place, $attrs, true), $place);
        }
        $this->assertSame('translated_descriptions', end($attrs));
        foreach (['region_id', 'island_id', 'city_id', 'country_code'] as $field) {
            $this->assertContains($field, $beaches['filterableAttributes']);
        }
        $this->assertContains('region_id', config('scout.meilisearch.index-settings.cities.filterableAttributes'));
        $this->assertContains('island_id', config('scout.meilisearch.index-settings.cities.filterableAttributes'));
        $this->assertContains('region_id', config('scout.meilisearch.index-settings.islands.filterableAttributes'));
        $this->assertGreaterThan(1000, $beaches['pagination']['maxTotalHits'], 'Meilisearch default caps totals at 1000');

        $beach = Beach::first()->toSearchableArray();
        $this->assertSame($this->hauts, $beach['region_id']);
        $this->assertArrayHasKey('city_id', $beach);
        $this->assertArrayHasKey('island_id', $beach);
        $this->assertArrayHasKey('region_id', City::first()->toSearchableArray());
        $this->assertArrayHasKey('island_id', City::first()->toSearchableArray());
    }

    #[Test]
    public function the_openapi_spec_documents_the_totals_and_the_place(): void
    {
        $docs = json_decode(file_get_contents(storage_path('api-docs/api-docs.json')), true);
        $meta = $docs['paths']['/api/v1/search']['get']['responses']['200']['content']['application/json']['schema']['properties']['meta']['properties'];

        $this->assertArrayHasKey('total_matches', $meta);
        $this->assertArrayHasKey('place', $meta);
        $this->assertEqualsCanonicalizing(
            ['beaches', 'cities', 'islands', 'regions', 'countries'],
            array_keys($meta['totals_by_type']['properties']),
        );
        $this->assertArrayHasKey('results_by_type', $meta, 'existing field kept (additive change)');
    }
}
