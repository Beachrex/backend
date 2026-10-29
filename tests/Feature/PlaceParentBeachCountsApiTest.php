<?php

namespace Tests\Feature;

use App\Models\Beach;
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
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Place pages show the location pills of their parent levels with each parent's published beach count
 * (owner, 2026-10-29). The show endpoints load those counts on the nested parents.
 */
class PlaceParentBeachCountsApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();

        Status::insert([['id' => 1, 'name' => 'active'], ['id' => 2, 'name' => 'inactive']]);

        Continent::create(['code' => 'EU']);
        ContinentTranslation::insert([['continent_code' => 'EU', 'locale' => 'en', 'name' => 'Europe', 'slug' => 'europe']]);
        foreach (['HR' => 'croatia', 'IT' => 'italy'] as $code => $slug) {
            Country::create(['code' => $code, 'continent_code' => 'EU']);
            CountryTranslation::insert([['country_code' => $code, 'locale' => 'en', 'name' => ucfirst($slug), 'slug' => $slug]]);
        }
        Region::insert([['id' => 1, 'code' => 'KV', 'country_code' => 'HR', 'status_id' => 1, 'created_at' => now(), 'updated_at' => now()]]);
        RegionTranslation::insert([['region_id' => 1, 'locale' => 'en', 'name' => 'Kvarner', 'slug' => 'kvarner']]);
        Island::insert([['id' => 1, 'code' => 'RAB', 'country_code' => 'HR', 'region_id' => 1, 'status_id' => 1, 'created_at' => now(), 'updated_at' => now()]]);
        IslandTranslation::insert([['island_id' => 1, 'locale' => 'en', 'name' => 'Rab', 'slug' => 'rab']]);
        City::insert([['id' => 1, 'code' => 'LOP', 'country_code' => 'HR', 'region_id' => 1, 'island_id' => 1, 'status_id' => 1, 'created_at' => now(), 'updated_at' => now()]]);
        CityTranslation::insert([['city_id' => 1, 'locale' => 'en', 'name' => 'Lopar', 'slug' => 'lopar']]);

        $full = ['country_code' => 'HR', 'region_id' => 1, 'island_id' => 1, 'city_id' => 1];
        $this->beach('in-city-1', $full);
        $this->beach('in-city-2', $full);
        $this->beach('region-mainland', ['country_code' => 'HR', 'region_id' => 1]);
        $this->beach('italy', ['country_code' => 'IT']);
        $this->beach('unpublished', $full, 2); // never counted
    }

    private function beach(string $folder, array $place, int $status = 1): void
    {
        Beach::create(array_merge([
            'latitude' => '44.9', 'longitude' => '14.4', 'continent_code' => 'EU',
            'status_id' => $status, 'folder_name' => $folder, 'mobile_upload' => false,
        ], $place));
    }

    public function test_city_page_has_counts_for_every_parent_level()
    {
        $this->getJson('/api/v1/cities/lopar', ['Accept-Language' => 'en'])
            ->assertOk()
            ->assertJsonPath('data.beaches_count', 2)
            ->assertJsonPath('data.country.beaches_count', 3)
            ->assertJsonPath('data.country.continent.beaches_count', 4)
            ->assertJsonPath('data.region.beaches_count', 3)
            ->assertJsonPath('data.island.beaches_count', 2);
    }

    public function test_island_page_has_counts_for_continent_country_and_region()
    {
        $this->getJson('/api/v1/islands/rab', ['Accept-Language' => 'en'])
            ->assertOk()
            ->assertJsonPath('data.beaches_count', 2)
            ->assertJsonPath('data.country.beaches_count', 3)
            ->assertJsonPath('data.country.continent.beaches_count', 4)
            ->assertJsonPath('data.region.beaches_count', 3);
    }

    public function test_region_page_has_counts_for_continent_and_country()
    {
        $this->getJson('/api/v1/regions/kvarner', ['Accept-Language' => 'en'])
            ->assertOk()
            ->assertJsonPath('data.beaches_count', 3)
            ->assertJsonPath('data.country.beaches_count', 3)
            ->assertJsonPath('data.country.continent.beaches_count', 4);
    }

    public function test_country_page_has_the_continent_count()
    {
        $this->getJson('/api/v1/countries/croatia', ['Accept-Language' => 'en'])
            ->assertOk()
            ->assertJsonPath('data.beaches_count', 3)
            ->assertJsonPath('data.continent.beaches_count', 4);
    }
}
