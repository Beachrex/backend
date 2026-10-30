<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\CityTranslation;
use App\Models\Continent;
use App\Models\ContinentTranslation;
use App\Models\Country;
use App\Models\CountryTranslation;
use App\Models\Region;
use App\Models\RegionTranslation;
use App\Models\Status;
use App\Services\PostGISService;
use App\Support\FixedJoinOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Hot SQL paths of the incident 2026-10-30 (ADR "API performance"). */
class SqlHotPathsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Status::insert([['id' => 1, 'name' => 'active'], ['id' => 2, 'name' => 'inactive']]);
        Continent::insert([['code' => 'EU']]);
        ContinentTranslation::insert([['continent_code' => 'EU', 'locale' => 'en', 'name' => 'Europe', 'slug' => 'europe']]);
        Country::insert([['code' => 'HR', 'iso3' => 'HRV', 'continent_code' => 'EU']]);
        CountryTranslation::insert([['country_code' => 'HR', 'locale' => 'en', 'name' => 'Croatia', 'slug' => 'croatia']]);
        Region::insert([['id' => 1, 'code' => 'DAL', 'country_code' => 'HR', 'status_id' => 1]]);
        RegionTranslation::insert([['region_id' => 1, 'locale' => 'en', 'name' => 'Dalmatia', 'slug' => 'dalmatia']]);
        City::insert([
            ['id' => 1, 'code' => 'SPL', 'country_code' => 'HR', 'region_id' => 1, 'latitude' => 43.5081, 'longitude' => 16.4402, 'status_id' => 1],
            ['id' => 2, 'code' => 'TRG', 'country_code' => 'HR', 'region_id' => 1, 'latitude' => 43.5167, 'longitude' => 16.2500, 'status_id' => 1],
            ['id' => 3, 'code' => 'DBK', 'country_code' => 'HR', 'region_id' => 1, 'latitude' => 42.6507, 'longitude' => 18.0944, 'status_id' => 1],
            // Closer to some points than Split, but inactive / without beaches: never the answer.
            ['id' => 4, 'code' => 'INA', 'country_code' => 'HR', 'region_id' => 1, 'latitude' => 43.6000, 'longitude' => 16.6000, 'status_id' => 2],
            ['id' => 5, 'code' => 'NOB', 'country_code' => 'HR', 'region_id' => 1, 'latitude' => 43.6000, 'longitude' => 16.6000, 'status_id' => 1],
        ]);
        CityTranslation::insert([
            ['city_id' => 1, 'locale' => 'en', 'name' => 'Split', 'slug' => 'split'],
            ['city_id' => 2, 'locale' => 'en', 'name' => 'Trogir', 'slug' => 'trogir'],
            ['city_id' => 3, 'locale' => 'en', 'name' => 'Dubrovnik', 'slug' => 'dubrovnik'],
            ['city_id' => 4, 'locale' => 'en', 'name' => 'Inactive', 'slug' => 'inactive'],
            ['city_id' => 5, 'locale' => 'en', 'name' => 'No beaches', 'slug' => 'no-beaches'],
        ]);
        foreach ([[1, 43.5081, 16.4402, 1], [2, 43.5167, 16.25, 1], [3, 42.6507, 18.0944, 1], [4, 43.6, 16.6, 1]] as [$city, $lat, $lng, $status]) {
            DB::table('beach')->insert([
                'latitude' => $lat, 'longitude' => $lng, 'country_code' => 'HR', 'continent_code' => 'EU',
                'region_id' => 1, 'city_id' => $city, 'status_id' => $status, 'folder_name' => "b{$city}", 'mobile_upload' => false,
            ]);
        }
        DB::statement('UPDATE beach SET location = ST_SetSRID(ST_MakePoint(longitude::float, latitude::float), 4326)');
    }

    #[Test]
    public function the_nearest_city_with_beaches_within_100_km_is_found_through_the_index(): void
    {
        $service = app(PostGISService::class);

        $this->assertSame('trogir', $service->reverseGeocodePoint(43.52, 16.26)?->city_slug);
        $this->assertSame('split', $service->reverseGeocodePoint(43.55, 16.50)?->city_slug, 'inactive / beach-less cities are skipped');
        $this->assertSame('dubrovnik', $service->reverseGeocodePoint(42.70, 18.00)?->city_slug);
        $this->assertNull($service->reverseGeocodePoint(45.80, 15.98), 'Zagreb is > 100 km from every city with beaches');

        $index = DB::selectOne("select indexdef from pg_indexes where indexname = 'city_point_geography_idx'");
        $this->assertNotNull($index, 'migration add_city_point_geography_index');
        $this->assertStringContainsString('USING gist', $index->indexdef);
    }

    #[Test]
    public function a_city_list_does_not_load_every_beach_of_the_continent(): void
    {
        $queries = [];
        DB::listen(function ($q) use (&$queries) {
            $queries[] = $q->sql;
        });

        $response = $this->getJson('/api/v1/cities?country_code=HR')->assertOk();

        $this->assertSame([], array_values(array_filter(
            $queries,
            fn ($sql) => str_starts_with($sql, 'select * from "beach" where "beach"."continent_code"'),
        )), 'the nested continent lazy-loaded all its beach rows');
        // The nested continent always carries the same count the relation gave (all beaches of the continent).
        $response->assertJsonPath('data.0.country.continent.beaches_count', 4);
        $response->assertJsonPath('data.1.country.continent.beaches_count', 4);
        $this->getJson('/api/v1/continents')->assertJsonPath('data.0.beaches_count', 4);
    }

    #[Test]
    public function fixed_join_order_settings_end_with_the_query(): void
    {
        // RefreshDatabase keeps every test in a transaction, so check on a second connection without one.
        config(['database.connections.pgsql_plain' => config('database.connections.pgsql')]);
        $db = DB::connection('pgsql_plain');
        DB::setDefaultConnection('pgsql_plain');
        try {
            $this->assertSame('8', $db->selectOne('show join_collapse_limit')->join_collapse_limit);
            $rows = FixedJoinOrder::select("select current_setting('join_collapse_limit') as j, current_setting('from_collapse_limit') as f");
            $this->assertSame(['1', '1'], [$rows[0]->j, $rows[0]->f]);
            $this->assertSame('8', $db->selectOne('show join_collapse_limit')->join_collapse_limit);
            $this->assertSame('8', $db->selectOne('show from_collapse_limit')->from_collapse_limit);
        } finally {
            DB::setDefaultConnection('pgsql');
            DB::purge('pgsql_plain');
        }
    }
}
