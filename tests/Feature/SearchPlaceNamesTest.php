<?php

namespace Tests\Feature;

use App\Models\Beach;
use App\Models\City;
use App\Models\Island;
use App\Models\Region;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Owner 2026-10-22: searching "grčija", "nemčija", "hrvaška", "francija" found only the country, no beaches.
 * The index held parent places in English only; `place_names` now carries them in every language.
 */
class SearchPlaceNamesTest extends TestCase
{
    use RefreshDatabase;

    private int $region;

    private int $city;

    private int $island;

    protected function setUp(): void
    {
        parent::setUp();
        DB::table('status')->insertOrIgnore([['id' => 1, 'name' => 'active']]);
        DB::table('countries')->insertOrIgnore(['code' => 'GR']);
        DB::table('country_translation')->insert([
            ['country_code' => 'GR', 'locale' => 'en', 'name' => 'Greece', 'slug' => 'greece'],
            ['country_code' => 'GR', 'locale' => 'sl', 'name' => 'Grčija', 'slug' => 'grcija'],
            ['country_code' => 'GR', 'locale' => 'de', 'name' => 'Griechenland', 'slug' => 'griechenland'],
            ['country_code' => 'GR', 'locale' => 'el', 'name' => 'Ελλάδα', 'slug' => 'ellada'],
        ]);
        $this->region = DB::table('region')->insertGetId(['code' => 'crete', 'country_code' => 'GR', 'status_id' => 1]);
        DB::table('region_translation')->insert([
            ['region_id' => $this->region, 'locale' => 'en', 'name' => 'Crete', 'slug' => 'crete'],
            ['region_id' => $this->region, 'locale' => 'sl', 'name' => 'Kreta', 'slug' => 'kreta'],
        ]);
        $this->island = DB::table('island')->insertGetId(['code' => 'crete-island', 'country_code' => 'GR', 'region_id' => $this->region, 'status_id' => 1]);
        DB::table('island_translation')->insert([
            ['island_id' => $this->island, 'locale' => 'en', 'name' => 'Crete', 'slug' => 'crete'],
            ['island_id' => $this->island, 'locale' => 'el', 'name' => 'Κρήτη', 'slug' => 'kriti'],
        ]);
        $this->city = DB::table('city')->insertGetId(['code' => 'chania', 'country_code' => 'GR', 'region_id' => $this->region, 'status_id' => 1]);
        DB::table('city_translation')->insert([
            ['city_id' => $this->city, 'locale' => 'en', 'name' => 'Chania', 'slug' => 'chania'],
            ['city_id' => $this->city, 'locale' => 'sl', 'name' => 'Hanija', 'slug' => 'hanija'],
        ]);
    }

    #[Test]
    public function a_beach_is_indexed_with_its_country_region_island_and_town_in_every_language(): void
    {
        $id = DB::table('beach')->insertGetId([
            'name' => 'Balos', 'country_code' => 'GR', 'status_id' => 1, 'latitude' => 35.58, 'longitude' => 23.59,
            'region_id' => $this->region, 'city_id' => $this->city, 'island_id' => $this->island,
        ]);
        DB::table('beach_translation')->insert(['beach_id' => $id, 'locale' => 'en', 'name' => 'Balos', 'slug' => 'balos']);

        $doc = Beach::find($id)->toSearchableArray();

        foreach (['Greece', 'Grčija', 'Griechenland', 'Ελλάδα', 'Kreta', 'Κρήτη', 'Hanija'] as $name) {
            $this->assertStringContainsString($name, $doc['place_names']);
        }
        $this->assertSame(1, substr_count($doc['place_names'], 'Crete'), 'the same name from region and island once');
    }

    #[Test]
    public function cities_islands_and_regions_carry_their_parent_places_too(): void
    {
        $this->assertStringContainsString('Grčija', City::find($this->city)->toSearchableArray()['place_names']);
        $this->assertStringContainsString('Kreta', City::find($this->city)->toSearchableArray()['place_names']);
        $this->assertStringContainsString('Grčija', Island::find($this->island)->toSearchableArray()['place_names']);
        $this->assertStringContainsString('Ελλάδα', Region::find($this->region)->toSearchableArray()['place_names']);
    }

    #[Test]
    public function place_names_are_searchable_in_every_index_that_has_parents(): void
    {
        foreach (['beaches', 'cities', 'islands', 'regions'] as $index) {
            $this->assertContains('place_names', config("scout.meilisearch.index-settings.{$index}.searchableAttributes"), $index);
        }
        $deploy = file_get_contents(base_path('.github/workflows/deploy.yml'));
        $this->assertLessThan(
            strpos($deploy, 'artisan search:index'),
            strpos($deploy, 'artisan search:update-settings'),
            'the deploy applies the index settings before indexing',
        );
    }
}
