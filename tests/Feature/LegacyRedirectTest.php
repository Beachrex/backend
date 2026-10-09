<?php

namespace Tests\Feature;

use App\Console\Commands\BuildLegacyUrlMap;
use App\Models\Beach;
use App\Models\BeachTranslation;
use App\Models\City;
use App\Models\CityTranslation;
use App\Models\Continent;
use App\Models\ContinentTranslation;
use App\Models\Country;
use App\Models\CountryTranslation;
use App\Models\Island;
use App\Models\IslandTranslation;
use App\Models\Region;
use App\Models\RegionTranslation;
use App\Models\Status;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Old beachrex.com URLs resolve to the new pages (TASK-004 step 5b).
 * Old URLs are real ones from search engine results; beach ids come from the committed map
 * (`database/legacy/old-beach-urls.json`, built from the old dump), so the map is tested too.
 */
class LegacyRedirectTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Status::insert([['id' => 1, 'name' => 'active'], ['id' => 2, 'name' => 'inactive']]);

        Continent::create(['code' => 'EU']);
        ContinentTranslation::insert([
            ['continent_code' => 'EU', 'locale' => 'en', 'name' => 'Europe', 'slug' => 'europe'],
            ['continent_code' => 'EU', 'locale' => 'hr', 'name' => 'Europa', 'slug' => 'europa'],
            ['continent_code' => 'EU', 'locale' => 'sl', 'name' => 'Evropa', 'slug' => 'evropa'],
        ]);

        Country::create(['code' => 'HR', 'continent_code' => 'EU']);
        CountryTranslation::insert([
            ['country_code' => 'HR', 'locale' => 'en', 'name' => 'Croatia', 'slug' => 'croatia'],
            ['country_code' => 'HR', 'locale' => 'hr', 'name' => 'Hrvatska', 'slug' => 'hrvatska'],
            ['country_code' => 'HR', 'locale' => 'de', 'name' => 'Kroatien', 'slug' => 'kroatien'],
            ['country_code' => 'HR', 'locale' => 'sl', 'name' => 'Hrvaška', 'slug' => 'hrvaska-si'],
        ]);

        Region::insert([
            ['id' => 1, 'code' => 'dalmatia-sibenik', 'country_code' => 'HR', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'code' => 'kvarner', 'country_code' => 'HR', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 3, 'code' => 'istra', 'country_code' => 'HR', 'created_at' => now(), 'updated_at' => now()],
        ]);
        RegionTranslation::insert([
            ['region_id' => 1, 'locale' => 'en', 'name' => 'Dalmatia - Šibenik', 'slug' => 'dalmatia-sibenik'],
            ['region_id' => 1, 'locale' => 'hr', 'name' => 'Dalmacija - Šibenik', 'slug' => 'dalmacija-sibenik'],
            ['region_id' => 2, 'locale' => 'en', 'name' => 'Kvarner', 'slug' => 'kvarner'],
            ['region_id' => 3, 'locale' => 'en', 'name' => 'Istria', 'slug' => 'istria'],
            ['region_id' => 3, 'locale' => 'hr', 'name' => 'Istra', 'slug' => 'istra'],
        ]);

        Island::insert([['id' => 1, 'code' => 'island-rab', 'country_code' => 'HR', 'region_id' => 2, 'created_at' => now(), 'updated_at' => now()]]);
        IslandTranslation::insert([
            ['island_id' => 1, 'locale' => 'en', 'name' => 'Island Rab', 'slug' => 'island-rab'],
            ['island_id' => 1, 'locale' => 'hr', 'name' => 'Otok Rab', 'slug' => 'otok-rab'],
        ]);

        City::insert([
            ['id' => 1, 'code' => 'tisno', 'country_code' => 'HR', 'region_id' => 1, 'status_id' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'code' => 'labin', 'country_code' => 'HR', 'region_id' => 3, 'status_id' => 1, 'created_at' => now(), 'updated_at' => now()],
        ]);
        CityTranslation::insert([
            ['city_id' => 1, 'locale' => 'en', 'name' => 'Tisno', 'slug' => 'tisno'],
            ['city_id' => 1, 'locale' => 'hr', 'name' => 'Tisno', 'slug' => 'tisno'],
            ['city_id' => 2, 'locale' => 'en', 'name' => 'Labin', 'slug' => 'labin'],
        ]);

        // Beach ids from the old site (map: beach-tisno => 136, beach-sveta-marina => 17).
        $this->beach(136, 1, 1, ['en' => 'beach-tisno', 'hr' => 'plaza-tisno']);
        $this->beach(17, 3, 2, ['en' => 'beach-sveta-marina']);
    }

    private function beach(int $id, int $regionId, int $cityId, array $slugs, int $status = 1): void
    {
        $beach = new Beach([
            'latitude' => '43.8', 'longitude' => '15.6', 'continent_code' => 'EU', 'country_code' => 'HR',
            'region_id' => $regionId, 'city_id' => $cityId, 'status_id' => $status,
            'folder_name' => "beach-{$id}", 'mobile_upload' => false,
        ]);
        $beach->id = $id;
        $beach->save();
        foreach ($slugs as $locale => $slug) {
            BeachTranslation::create(['beach_id' => $id, 'locale' => $locale, 'name' => $slug, 'slug' => $slug]);
        }
    }

    private function resolve(string $path, string $locale = 'en')
    {
        return $this->getJson('/api/v1/legacy-redirect?path=' . urlencode($path), ['Accept-Language' => $locale]);
    }

    public function test_old_croatian_beach_url_resolves_to_the_beach_in_the_requested_locale(): void
    {
        $this->resolve('/hr/hrvatska/dalmacija/tisno-plaze/plaza-tisno?c=lun', 'hr')
            ->assertOk()
            ->assertJsonPath('data.type', 'beach')
            ->assertJsonPath('data.language', 'hr')
            ->assertJsonPath('data.segments', ['europa', 'hrvatska', 'dalmacija-sibenik', 'tisno', 'plaza-tisno']);
    }

    public function test_beach_word_of_any_old_language_maps_to_the_english_nice_url(): void
    {
        foreach (['/de/kroatien/dalmatien/tisno-strande/strand-tisno', '/it/croazia/dalmazia/tisno-spiagge/spiaggia-tisno', '/en/beach/beach-tisno', '/sl/hrvaska/dalmacija/tisno-plaze/plaza-tisno'] as $old) {
            $this->resolve($old)
                ->assertOk()
                ->assertJsonPath('data.type', 'beach')
                ->assertJsonPath('data.segments', ['europe', 'croatia', 'dalmatia-sibenik', 'tisno', 'beach-tisno']);
        }
    }

    public function test_slovenian_old_prefix_is_reported_and_slugs_fall_back_to_english(): void
    {
        $this->resolve('/sl/hrvaska/istra/labin-plaze/plaza-sveta-marina', 'sl')
            ->assertOk()
            ->assertJsonPath('data.language', 'sl')
            ->assertJsonPath('data.segments', ['evropa', 'hrvaska-si', 'istria', 'labin', 'beach-sveta-marina']);
    }

    public function test_path_with_the_new_locale_in_front_is_resolved(): void
    {
        // The frontend catch-all sends /{newLocale}/{old path...}, e.g. /en/sl/... after the middleware.
        $this->resolve('/en/sl/hrvaska/istra/labin-plaze/plaza-sveta-marina')
            ->assertOk()
            ->assertJsonPath('data.language', 'sl')
            ->assertJsonPath('data.type', 'beach');
    }

    public function test_unknown_or_inactive_beach_falls_back_to_its_city(): void
    {
        // German page translated the beach name ("strand-kroatien"); no map entry → city page.
        $this->resolve('/de/kroatien/dalmatien/tisno-strande/strand-kroatien')
            ->assertOk()
            ->assertJsonPath('data.type', 'city')
            ->assertJsonPath('data.segments', ['europe', 'croatia', 'dalmatia-sibenik', 'tisno']);

        Beach::where('id', 136)->update(['status_id' => 2]);
        $this->resolve('/hr/hrvatska/dalmacija/tisno-plaze/plaza-tisno?x=1')
            ->assertOk()
            ->assertJsonPath('data.type', 'city');
    }

    public function test_city_island_region_and_country_pages(): void
    {
        $this->resolve('/hr/hrvatska/dalmacija/tisno-plaze', 'hr')
            ->assertJsonPath('data.type', 'city')
            ->assertJsonPath('data.segments', ['europa', 'hrvatska', 'dalmacija-sibenik', 'tisno']);

        $this->resolve('/en/croatia/kvarner/island-rab', 'hr')
            ->assertJsonPath('data.type', 'island')
            ->assertJsonPath('data.segments', ['europa', 'hrvatska', 'kvarner', 'otok-rab']);

        $this->resolve('/de/kroatien/istra')
            ->assertJsonPath('data.type', 'region')
            ->assertJsonPath('data.segments', ['europe', 'croatia', 'istria']);

        // "dalmacija" matches no region code and is ambiguous as a prefix → country.
        $this->resolve('/hr/hrvatska/dalmacija', 'hr')
            ->assertJsonPath('data.type', 'country')
            ->assertJsonPath('data.segments', ['europa', 'hrvatska']);

        $this->resolve('/en/croatia/gravel-beaches')
            ->assertJsonPath('data.type', 'country');

        // Old typo in the country slug.
        $this->resolve('/hr/hrvaska/istra/labin-plaze/plaza-unknown')
            ->assertJsonPath('data.type', 'city')
            ->assertJsonPath('data.segments', ['europe', 'croatia', 'istria', 'labin']);
    }

    public function test_old_beach_filter_is_the_map(): void
    {
        $this->resolve('/en/beach-filter')->assertOk()->assertJsonPath('data.type', 'map')->assertJsonPath('data.segments', []);
    }

    public function test_nothing_matches_returns_404(): void
    {
        $this->resolve('/en/no-such-country/foo')->assertNotFound();
        $this->resolve('/en')->assertNotFound();
        $this->resolve('/')->assertNotFound();
    }

    public function test_path_is_required_and_bounded(): void
    {
        $this->getJson('/api/v1/legacy-redirect')->assertStatus(422);
        $this->resolve('/' . str_repeat('a', 501))->assertStatus(422);
    }

    public function test_map_parser_reads_only_english_nice_urls(): void
    {
        $sql = "INSERT INTO `beach_nice_url` (`id`, `nice_url`, `language_short_name`, `beach_id`) VALUES\n"
            . "(1, 'beach-livade', 'en', 1),\n(2, 'Beach-Pineta', 'en', 6),\n(3, 'x', 'hrvatski', 9);\n";
        $this->assertSame(['beach-livade' => 1, 'beach-pineta' => 6], BuildLegacyUrlMap::parseNiceUrls($sql));
    }

    public function test_committed_map_holds_the_old_beach_urls(): void
    {
        $map = json_decode((string) file_get_contents(base_path('database/legacy/old-beach-urls.json')), true);
        $this->assertGreaterThan(4000, count($map));
        $this->assertSame(136, $map['beach-tisno']);
        $this->assertSame(743, $map['beach-trstenik-1']);
        $this->assertSame(617, $map['beach-trstenik']);
    }
}
