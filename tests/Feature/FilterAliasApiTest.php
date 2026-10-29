<?php

namespace Tests\Feature;

use App\Models\Beach;
use App\Models\Continent;
use App\Models\Country;
use App\Models\Region;
use App\Models\RegionTranslation;
use App\Models\Status;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Regression: /beaches/filters read only `country`, while /beaches and the MCP use `country_code`. */
class FilterAliasApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Status::insert([['id' => 1, 'name' => 'active'], ['id' => 2, 'name' => 'inactive']]);
        Continent::create(['code' => 'EU']);
        foreach (['HR' => [1, 'dalmatia'], 'SI' => [2, 'primorska']] as $country => [$id, $code]) {
            Country::create(['code' => $country, 'continent_code' => 'EU']);
            Region::insert([['id' => $id, 'code' => $code, 'country_code' => $country, 'status_id' => 1, 'created_at' => now(), 'updated_at' => now()]]);
            RegionTranslation::insert([['region_id' => $id, 'locale' => 'en', 'name' => ucfirst($code), 'slug' => $code]]);
            // Regions are listed only when they have more than one active beach.
            Beach::factory()->count(2)->create(['country_code' => $country, 'region_id' => $id, 'status_id' => 1]);
        }
    }

    private function regionCodes(array $query): array
    {
        $response = $this->getJson('/api/v1/beaches/filters?'.http_build_query($query), ['Accept-Language' => 'en'])->assertOk();
        $json = $response->json();
        $options = data_get($json, 'data.region.options') ?? data_get($json, 'region.options') ?? [];

        return collect($options)->pluck('code')->sort()->values()->all();
    }

    public function test_country_code_is_an_alias_of_country(): void
    {
        $this->assertSame(['dalmatia', 'primorska'], $this->regionCodes([]));
        $this->assertSame(['primorska'], $this->regionCodes(['country' => 'SI']));
        $this->assertSame(['primorska'], $this->regionCodes(['country_code' => 'SI']));
    }

    /** Owner 2026-10-23: several countries on the map (country=HR,IT): the options cover all of them. */
    public function test_several_countries_scope_the_options_to_any_of_them(): void
    {
        $this->assertSame(['dalmatia', 'primorska'], $this->regionCodes(['country' => 'HR,SI']));
        $this->assertSame(['dalmatia', 'primorska'], $this->regionCodes(['country' => 'hr, si']));
        $this->assertSame(['dalmatia'], $this->regionCodes(['country' => 'HR,XX']), 'an unknown code adds nothing');
    }

    public function test_cities_follow_several_regions_and_unknown_regions_do_not_scope(): void
    {
        foreach ([1 => 'split', 2 => 'piran'] as $region => $code) {
            $city = \App\Models\City::create(['code' => $code, 'country_code' => $region === 1 ? 'HR' : 'SI', 'region_id' => $region, 'status_id' => 1]);
            \App\Models\CityTranslation::create(['city_id' => $city->id, 'locale' => 'en', 'name' => ucfirst($code), 'slug' => $code]);
            Beach::factory()->count(2)->create(['country_code' => $city->country_code, 'region_id' => $region, 'city_id' => $city->id, 'status_id' => 1]);
        }
        $cities = function (array $query): array {
            $json = $this->getJson('/api/v1/beaches/filters?'.http_build_query($query), ['Accept-Language' => 'en'])->assertOk()->json();

            return collect(data_get($json, 'data.city.options') ?? [])->pluck('code')->sort()->values()->all();
        };

        $this->assertSame(['piran', 'split'], $cities(['region' => 'dalmatia,primorska']));
        $this->assertSame(['split'], $cities(['region' => 'dalmatia']));
        $this->assertSame(['piran', 'split'], $cities(['region' => 'a-localized-slug']), 'unknown region: no scoping, as before');
    }
}
