<?php

namespace Tests\Feature;

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

class BeachDetailsApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed statuses
        Status::insert([
            ['id' => 1, 'name' => 'active'],
            ['id' => 2, 'name' => 'inactive'],
        ]);

        // Seed continents
        Continent::create(['code' => 'EU']);
        ContinentTranslation::insert([
            ['continent_code' => 'EU', 'locale' => 'en', 'name' => 'Europe', 'slug' => 'europe'],
            ['continent_code' => 'EU', 'locale' => 'hr', 'name' => 'Europa', 'slug' => 'europa'],
        ]);

        // Seed countries
        Country::create(['code' => 'HR', 'continent_code' => 'EU']);
        CountryTranslation::insert([
            ['country_code' => 'HR', 'locale' => 'en', 'name' => 'Croatia', 'slug' => 'croatia'],
            ['country_code' => 'HR', 'locale' => 'hr', 'name' => 'Hrvatska', 'slug' => 'hrvatska'],
        ]);

        // Seed region
        Region::insert([
            ['id' => 1, 'code' => 'KV', 'country_code' => 'HR', 'created_at' => now(), 'updated_at' => now()],
        ]);
        RegionTranslation::insert([
            ['region_id' => 1, 'locale' => 'en', 'name' => 'Kvarner', 'slug' => 'kvarner'],
            ['region_id' => 1, 'locale' => 'hr', 'name' => 'Kvarner', 'slug' => 'kvarner'],
        ]);

        // Seed island
        Island::insert([
            ['id' => 1, 'code' => 'CRS', 'created_at' => now(), 'updated_at' => now()],
        ]);
        IslandTranslation::insert([
            ['island_id' => 1, 'locale' => 'en', 'name' => 'Island Cres', 'slug' => 'island-cres'],
            ['island_id' => 1, 'locale' => 'hr', 'name' => 'Otok Cres', 'slug' => 'otok-cres'],
        ]);

        // Seed city
        City::insert([
            ['id' => 1, 'code' => 'CRS', 'country_code' => 'HR', 'created_at' => now(), 'updated_at' => now()],
        ]);
        CityTranslation::insert([
            ['city_id' => 1, 'locale' => 'en', 'name' => 'Cres', 'slug' => 'cres'],
            ['city_id' => 1, 'locale' => 'hr', 'name' => 'Cres', 'slug' => 'cres'],
        ]);
    }

    public function test_can_get_beach_details_by_slug()
    {
        // Create a beach
        $beach = Beach::create([
            'latitude' => '44.9522',
            'longitude' => '14.4606',
            'country_code' => 'HR',
            'region_id' => 1,
            'island_id' => 1,
            'city_id' => 1,
            'status_id' => 1,
            'folder_name' => 'test-beach',
            'mobile_upload' => false,
        ]);

        BeachTranslation::insert([
            [
                'beach_id' => $beach->id,
                'locale' => 'en',
                'name' => 'Test Beach',
                'slug' => 'test-beach',
                'description' => 'A beautiful test beach',
            ],
        ]);

        $response = $this->getJson('/api/v1/beach-details/test-beach', [
            'Accept-Language' => 'en',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'basic_info' => [
                        'id',
                        'name',
                        'slug',
                        'latitude',
                        'longitude',
                    ],
                    'location' => [
                        'continent',
                        'country',
                    ],
                    'navigation' => [
                        'previous',
                        'next',
                    ],
                ],
            ])
            ->assertJson([
                'data' => [
                    'basic_info' => [
                        'slug' => 'test-beach',
                        'name' => 'Test Beach',
                    ],
                    'location' => [
                        'country' => [
                            'code' => 'HR',
                        ],
                    ],
                ],
            ]);
    }

    public function test_can_get_beach_details_by_id()
    {
        $beach = Beach::create([
            'latitude' => '44.9522',
            'longitude' => '14.4606',
            'country_code' => 'HR',
            'region_id' => 1,
            'status_id' => 1,
            'folder_name' => 'test-beach-2',
            'mobile_upload' => false,
        ]);

        BeachTranslation::create([
            'beach_id' => $beach->id,
            'locale' => 'en',
            'name' => 'Test Beach 2',
            'slug' => 'test-beach-2',
        ]);

        $response = $this->getJson("/api/v1/beach-details/{$beach->id}", [
            'Accept-Language' => 'en',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'basic_info' => [
                        'id' => $beach->id,
                        'name' => 'Test Beach 2',
                    ],
                ],
            ]);
    }

    public function test_beach_details_returns_404_for_nonexistent_beach()
    {
        $response = $this->getJson('/api/v1/beach-details/nonexistent-beach', [
            'Accept-Language' => 'en',
        ]);

        $response->assertStatus(404)
            ->assertJson([
                'message' => 'Beach not found',
            ]);
    }

    public function test_beach_details_returns_404_for_inactive_beach()
    {
        $beach = Beach::create([
            'latitude' => '44.9522',
            'longitude' => '14.4606',
            'country_code' => 'HR',
            'region_id' => 1,
            'status_id' => 2, // inactive
            'folder_name' => 'inactive-beach',
            'mobile_upload' => false,
        ]);

        BeachTranslation::create([
            'beach_id' => $beach->id,
            'locale' => 'en',
            'name' => 'Inactive Beach',
            'slug' => 'inactive-beach',
        ]);

        $response = $this->getJson('/api/v1/beach-details/inactive-beach', [
            'Accept-Language' => 'en',
        ]);

        $response->assertStatus(404);
    }

    public function test_beach_details_includes_navigation()
    {
        // Create three beaches in the same region
        $beach1 = Beach::create([
            'latitude' => '44.9522',
            'longitude' => '14.4606',
            'country_code' => 'HR',
            'region_id' => 1,
            'status_id' => 1,
            'folder_name' => 'beach-1',
            'mobile_upload' => false,
        ]);
        BeachTranslation::create([
            'beach_id' => $beach1->id,
            'locale' => 'en',
            'name' => 'Beach 1',
            'slug' => 'beach-1',
        ]);

        $beach2 = Beach::create([
            'latitude' => '44.9600',
            'longitude' => '14.4700',
            'country_code' => 'HR',
            'region_id' => 1,
            'status_id' => 1,
            'folder_name' => 'beach-2',
            'mobile_upload' => false,
        ]);
        BeachTranslation::create([
            'beach_id' => $beach2->id,
            'locale' => 'en',
            'name' => 'Beach 2',
            'slug' => 'beach-2',
        ]);

        $beach3 = Beach::create([
            'latitude' => '44.9700',
            'longitude' => '14.4800',
            'country_code' => 'HR',
            'region_id' => 1,
            'status_id' => 1,
            'folder_name' => 'beach-3',
            'mobile_upload' => false,
        ]);
        BeachTranslation::create([
            'beach_id' => $beach3->id,
            'locale' => 'en',
            'name' => 'Beach 3',
            'slug' => 'beach-3',
        ]);

        // Test middle beach has both previous and next
        $response = $this->getJson('/api/v1/beach-details/beach-2', [
            'Accept-Language' => 'en',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'navigation' => [
                        'previous' => [
                            'id',
                            'name',
                            'slug',
                            'location' => [
                                'continent_slug',
                                'country_slug',
                                'region_slug',
                                'island_slug',
                                'city_slug',
                            ],
                        ],
                        'next' => [
                            'id',
                            'name',
                            'slug',
                            'location',
                        ],
                    ],
                ],
            ]);
    }

    public function test_beach_details_respects_locale()
    {
        $beach = Beach::create([
            'latitude' => '44.9522',
            'longitude' => '14.4606',
            'country_code' => 'HR',
            'region_id' => 1,
            'status_id' => 1,
            'folder_name' => 'multilang-beach',
            'mobile_upload' => false,
        ]);

        BeachTranslation::insert([
            [
                'beach_id' => $beach->id,
                'locale' => 'en',
                'name' => 'Multilingual Beach',
                'slug' => 'multilingual-beach',
            ],
            [
                'beach_id' => $beach->id,
                'locale' => 'hr',
                'name' => 'Višejezična Plaža',
                'slug' => 'visejezicna-plaza',
            ],
        ]);

        // Test English
        $response = $this->getJson('/api/v1/beach-details/multilingual-beach', [
            'Accept-Language' => 'en',
        ]);
        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'basic_info' => [
                        'name' => 'Multilingual Beach',
                    ],
                ],
            ]);

        // Test Croatian
        $response = $this->getJson('/api/v1/beach-details/visejezicna-plaza', [
            'Accept-Language' => 'hr',
        ]);
        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'basic_info' => [
                        'name' => 'Višejezična Plaža',
                    ],
                ],
            ]);
    }

    public function test_views_are_counted_for_browsers_but_not_for_machine_clients()
    {
        $beach = Beach::create([
            'latitude' => '44.9522', 'longitude' => '14.4606', 'country_code' => 'HR',
            'region_id' => 1, 'island_id' => 1, 'city_id' => 1, 'status_id' => 1,
            'folder_name' => 'views-beach', 'mobile_upload' => false,
        ]);
        BeachTranslation::insert([
            ['beach_id' => $beach->id, 'locale' => 'en', 'name' => 'Views Beach', 'slug' => 'views-beach'],
        ]);
        $views = fn () => (int) $beach->fresh()->beach_views;
        $before = $views();

        $this->getJson('/api/v1/beach-details/views-beach', ['Accept-Language' => 'en'])->assertOk();
        $this->assertSame($before + 1, $views());

        // The MCP server (AI assistants) identifies itself and is not counted.
        $this->getJson('/api/v1/beach-details/views-beach', ['Accept-Language' => 'en', 'X-Beachrex-Client' => 'mcp'])->assertOk();
        $this->assertSame($before + 1, $views());
    }

    private function createViewsBeach(int $statusId = 1): Beach
    {
        $beach = Beach::create([
            'latitude' => '44.9522', 'longitude' => '14.4606', 'country_code' => 'HR',
            'region_id' => 1, 'island_id' => 1, 'city_id' => 1, 'status_id' => $statusId,
            'folder_name' => 'count-beach', 'mobile_upload' => false,
        ]);
        BeachTranslation::insert([
            ['beach_id' => $beach->id, 'locale' => 'en', 'name' => 'Count Beach', 'slug' => 'count-beach'],
        ]);

        return $beach;
    }

    public function test_cached_beach_details_count_a_view_with_one_update_not_a_full_reload()
    {
        $beach = $this->createViewsBeach();
        $this->getJson('/api/v1/beach-details/count-beach', ['Accept-Language' => 'en'])->assertOk(); // warms the cache

        \Illuminate\Support\Facades\DB::flushQueryLog();
        \Illuminate\Support\Facades\DB::enableQueryLog();
        $this->getJson('/api/v1/beach-details/count-beach', ['Accept-Language' => 'en'])->assertOk();
        $queries = \Illuminate\Support\Facades\DB::getQueryLog();
        \Illuminate\Support\Facades\DB::disableQueryLog();

        // Before: the slug lookup plus the beach with ~37 relations (~70 queries) for every view.
        $this->assertLessThanOrEqual(2, count($queries), json_encode(array_column($queries, 'query')));
        $this->assertSame(2, (int) $beach->fresh()->beach_views);
    }

    /** Owner 2026-10-30: the map panel (by id) and the beach page (by slug) share one cache entry. */
    public function test_slug_and_id_requests_share_one_cache_entry()
    {
        $beach = $this->createViewsBeach();
        \Illuminate\Support\Facades\Cache::flush();
        $this->getJson('/api/v1/beach-details/count-beach', ['Accept-Language' => 'en'])->assertOk(); // by slug
        $this->assertTrue(\Illuminate\Support\Facades\Cache::has(\App\Support\BeachDetailsCache::key('en', $beach->id)));

        \Illuminate\Support\Facades\DB::flushQueryLog();
        \Illuminate\Support\Facades\DB::enableQueryLog();
        $this->getJson("/api/v1/beach-details/{$beach->id}", ['Accept-Language' => 'en'])
            ->assertOk()
            ->assertJsonPath('data.basic_info.id', $beach->id);
        $queries = \Illuminate\Support\Facades\DB::getQueryLog();
        \Illuminate\Support\Facades\DB::disableQueryLog();

        // A cache hit: at most the view counter update, never the ~70-query rebuild.
        $this->assertLessThanOrEqual(1, count($queries), json_encode(array_column($queries, 'query')));
    }

    public function test_browser_view_endpoint_counts_once_per_visitor_and_window()
    {
        $beach = $this->createViewsBeach();
        $views = fn () => (int) $beach->fresh()->beach_views;
        $updatedAt = $beach->fresh()->updated_at;

        $this->postJson("/api/v1/beaches/{$beach->id}/view")->assertNoContent();
        $this->assertSame(1, $views());

        // Reload by the same visitor within 30 minutes: not counted again.
        $this->postJson("/api/v1/beaches/{$beach->id}/view")->assertNoContent();
        $this->assertSame(1, $views());

        // Another visitor counts.
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])
            ->postJson("/api/v1/beaches/{$beach->id}/view")->assertNoContent();
        $this->assertSame(2, $views());

        // The counter does not change the beach's last edit time.
        $this->assertEquals($updatedAt, $beach->fresh()->updated_at);
    }

    public function test_browser_view_endpoint_rejects_unknown_and_inactive_beaches()
    {
        $inactive = $this->createViewsBeach(2);

        $this->postJson("/api/v1/beaches/{$inactive->id}/view")->assertNotFound();
        $this->postJson('/api/v1/beaches/999999/view')->assertNotFound();
        $this->postJson('/api/v1/beaches/abc/view')->assertNotFound();
        $this->assertSame(0, (int) $inactive->fresh()->beach_views);
    }

    public function test_location_levels_include_published_beach_counts()
    {
        Country::create(['code' => 'IT', 'continent_code' => 'EU']);

        $make = function (string $slug, array $place, int $status = 1): Beach {
            $beach = Beach::create(array_merge([
                'latitude' => '44.9522',
                'longitude' => '14.4606',
                'continent_code' => 'EU',
                'status_id' => $status,
                'folder_name' => $slug,
                'mobile_upload' => false,
            ], $place));
            BeachTranslation::create(['beach_id' => $beach->id, 'locale' => 'en', 'name' => $slug, 'slug' => $slug]);

            return $beach;
        };

        $full = ['country_code' => 'HR', 'region_id' => 1, 'island_id' => 1, 'city_id' => 1];
        $make('count-target', $full);
        $make('count-sibling', $full);
        $make('count-region-only', ['country_code' => 'HR', 'region_id' => 1]);
        $make('count-other-country', ['country_code' => 'IT']);
        // Unpublished beaches in every level: never counted.
        $make('count-unpublished', $full, 2);

        $response = $this->getJson('/api/v1/beach-details/count-target', ['Accept-Language' => 'en']);

        $response->assertOk()
            ->assertJsonPath('data.location.continent.beaches_count', 4)
            ->assertJsonPath('data.location.country.beaches_count', 3)
            ->assertJsonPath('data.location.region.beaches_count', 3)
            ->assertJsonPath('data.location.island.beaches_count', 2)
            ->assertJsonPath('data.location.city.beaches_count', 2);

        foreach (['continent', 'country', 'region', 'island', 'city'] as $level) {
            $this->assertIsInt($response->json("data.location.{$level}.beaches_count"), $level);
        }
    }

    public function test_location_without_island_or_city_has_no_count_for_those_levels()
    {
        $beach = Beach::create([
            'latitude' => '44.9522',
            'longitude' => '14.4606',
            'continent_code' => 'EU',
            'country_code' => 'HR',
            'region_id' => 1,
            'status_id' => 1,
            'folder_name' => 'mainland-beach',
            'mobile_upload' => false,
        ]);
        BeachTranslation::create(['beach_id' => $beach->id, 'locale' => 'en', 'name' => 'Mainland', 'slug' => 'mainland-beach']);

        $response = $this->getJson('/api/v1/beach-details/mainland-beach', ['Accept-Language' => 'en']);

        $response->assertOk()
            ->assertJsonPath('data.location.region.beaches_count', 1)
            ->assertJsonPath('data.location.country.beaches_count', 1);
        $this->assertEmpty($response->json('data.location.island'));
        $this->assertEmpty($response->json('data.location.city'));
        $response->assertJsonMissingPath('data.location.island.beaches_count')
            ->assertJsonMissingPath('data.location.city.beaches_count');
    }
}
