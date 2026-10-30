<?php

namespace Tests\Feature;

use App\Models\Continent;
use App\Models\ContinentTranslation;
use App\Models\Country;
use App\Models\CountryTranslation;
use App\Models\Status;
use App\Models\User;
use App\Support\ApiResponseCache;
use App\Support\BeachDetailsCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Public list responses are cached as JSON (never Eloquent models), per language and parameters, and cleared when
 * content changes (ADR 2026-10-30 "API performance: layered caching").
 */
class ApiResponseCacheTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();

        Status::insert([['id' => 1, 'name' => 'active'], ['id' => 2, 'name' => 'inactive']]);
        Continent::insert([['code' => 'EU']]);
        ContinentTranslation::insert([
            ['continent_code' => 'EU', 'locale' => 'en', 'name' => 'Europe', 'slug' => 'europe'],
            ['continent_code' => 'EU', 'locale' => 'de', 'name' => 'Europa', 'slug' => 'europa'],
        ]);
        Country::insert([['code' => 'HR', 'iso3' => 'HRV', 'continent_code' => 'EU']]);
        CountryTranslation::insert([
            ['country_code' => 'HR', 'locale' => 'en', 'name' => 'Croatia', 'slug' => 'croatia'],
            ['country_code' => 'HR', 'locale' => 'de', 'name' => 'Kroatien', 'slug' => 'kroatien'],
        ]);
        DB::table('beach')->insert([
            'id' => 1, 'name' => 'Zlatni rat', 'latitude' => 43.25, 'longitude' => 16.63, 'status_id' => 1,
            'country_code' => 'HR', 'continent_code' => 'EU',
        ]);
    }

    #[Test]
    public function a_list_is_cached_as_json_per_language_and_served_from_the_cache(): void
    {
        $first = $this->getJson('/api/v1/countries', ['Accept-Language' => 'de'])->assertOk()
            ->assertHeader('X-Cache-Status', 'MISS')
            ->assertJsonPath('data.0.name', 'Kroatien');

        // The stored value is the compressed JSON body, not models.
        app()->setLocale('en');
        $this->assertNull(Cache::get(ApiResponseCache::key('countries.index', ['continent' => null])), 'the key depends on the locale');
        app()->setLocale('de');
        $stored = Cache::get(ApiResponseCache::key('countries.index', ['continent' => null]));
        $this->assertIsString($stored);
        $this->assertSame($first->getContent(), gzuncompress($stored));

        // Served from the cache even when the database changes behind its back (no model event).
        DB::table('country_translation')->where('country_code', 'HR')->where('locale', 'de')->update(['name' => 'Changed']);
        $second = $this->getJson('/api/v1/countries', ['Accept-Language' => 'de'])->assertOk()
            ->assertHeader('X-Cache-Status', 'HIT');
        $this->assertSame($first->getContent(), $second->getContent());

        // Another language is its own entry.
        $this->getJson('/api/v1/countries', ['Accept-Language' => 'en'])->assertOk()
            ->assertHeader('X-Cache-Status', 'MISS')
            ->assertJsonPath('data.0.name', 'Croatia');
    }

    #[Test]
    public function saving_or_deleting_a_content_model_clears_cached_lists(): void
    {
        $this->getJson('/api/v1/countries', ['Accept-Language' => 'de'])->assertJsonPath('data.0.name', 'Kroatien');
        $this->getJson('/api/v1/continents', ['Accept-Language' => 'de'])->assertHeader('X-Cache-Status', 'MISS');

        // An admin edit goes through Eloquent: the translation model's `saved` event clears every list.
        CountryTranslation::query()->where('country_code', 'HR')->where('locale', 'de')->firstOrFail()
            ->update(['name' => 'Kroatien (neu)']);

        $this->getJson('/api/v1/countries', ['Accept-Language' => 'de'])
            ->assertHeader('X-Cache-Status', 'MISS')
            ->assertJsonPath('data.0.name', 'Kroatien (neu)');
        $this->getJson('/api/v1/continents', ['Accept-Language' => 'de'])->assertHeader('X-Cache-Status', 'MISS');
        $this->getJson('/api/v1/continents', ['Accept-Language' => 'de'])->assertHeader('X-Cache-Status', 'HIT');

        $generation = ApiResponseCache::generation();
        CountryTranslation::query()->where('country_code', 'HR')->where('locale', 'de')->firstOrFail()->delete();
        $this->assertGreaterThan($generation, ApiResponseCache::generation());
    }

    #[Test]
    public function saving_a_user_or_ai_record_does_not_clear_the_cache(): void
    {
        $generation = ApiResponseCache::generation();

        User::factory()->create();

        $this->assertSame($generation, ApiResponseCache::generation());
        $this->assertFalse(ApiResponseCache::isContent(new User()));
        $this->assertFalse(ApiResponseCache::isContent(new \App\Models\AiUsage()));
        $this->assertTrue(ApiResponseCache::isContent(new \App\Models\Beach()));
        $this->assertTrue(ApiResponseCache::isContent(new \App\Models\StaticPageTranslation()));
        $this->assertTrue(ApiResponseCache::isContent(new \App\Models\SiteSetting()));
    }

    #[Test]
    public function cache_clear_api_clears_cached_lists(): void
    {
        $this->getJson('/api/v1/continents')->assertHeader('X-Cache-Status', 'MISS');
        $this->getJson('/api/v1/continents')->assertHeader('X-Cache-Status', 'HIT');

        $this->artisan('cache:clear-api', ['--type' => 'reference'])->assertSuccessful();
        $this->getJson('/api/v1/continents')->assertHeader('X-Cache-Status', 'MISS');

        $this->artisan('cache:clear-api', ['--type' => 'all'])->assertSuccessful();
        $this->getJson('/api/v1/continents')->assertHeader('X-Cache-Status', 'MISS');
    }

    #[Test]
    public function an_evicted_generation_never_brings_back_old_entries(): void
    {
        // The shared Redis evicts any key under memory pressure (allkeys-lru), the generation included.
        $old = ApiResponseCache::generation();
        $this->getJson('/api/v1/continents')->assertHeader('X-Cache-Status', 'MISS');
        ApiResponseCache::flush();
        $this->assertGreaterThan($old, ApiResponseCache::generation());

        Cache::forget(ApiResponseCache::GENERATION_KEY);
        usleep(2000);
        $this->assertGreaterThan($old, ApiResponseCache::generation(), 'a recreated generation is newer than every old one');
        $this->getJson('/api/v1/continents')->assertHeader('X-Cache-Status', 'MISS');

        // Same rule for beach details (it used to restart at 1, making old g1 entries current again).
        $key = BeachDetailsCache::key('en', 1);
        BeachDetailsCache::forgetAll();
        Cache::forget('beach_details:generation');
        usleep(2000);
        $this->assertNotSame($key, BeachDetailsCache::key('en', 1));
        $this->assertGreaterThan((int) substr(explode(':', $key)[1], 1), (int) substr(explode(':', BeachDetailsCache::key('en', 1))[1], 1));
    }

    #[Test]
    public function the_key_holds_only_the_given_parameters_in_a_stable_order(): void
    {
        app()->setLocale('de');
        $this->assertSame(
            ApiResponseCache::key('x', ['a' => 1, 'b' => 2]),
            ApiResponseCache::key('x', ['b' => 2, 'a' => 1]),
        );
        $this->assertNotSame(ApiResponseCache::key('x', ['a' => 1]), ApiResponseCache::key('x', ['a' => 2]));
        $this->assertNotSame(ApiResponseCache::key('x'), ApiResponseCache::key('y'));
        app()->setLocale('en');
        $this->assertStringContainsString(':x:en:', ApiResponseCache::key('x'));

        // Unknown query parameters do not create new entries (bots inventing parameters).
        $this->getJson('/api/v1/continents?utm=1')->assertHeader('X-Cache-Status', 'MISS');
        $this->getJson('/api/v1/continents?utm=2&foo=bar')->assertHeader('X-Cache-Status', 'HIT');
    }

    #[Test]
    public function a_waiting_request_uses_the_entry_another_worker_built(): void
    {
        $key = ApiResponseCache::key('test.lock');
        $lock = Cache::lock('lock:'.$key, 30);
        $this->assertTrue($lock->get());

        // While another worker holds the build lock and has already stored the answer, this request reads it
        // instead of running the query itself.
        Cache::put($key, gzcompress('{"from":"other worker"}'), 60);
        $built = false;
        $response = ApiResponseCache::json('test.lock', [], 60, function () use (&$built) {
            $built = true;

            return ['from' => 'this worker'];
        });

        $this->assertFalse($built);
        $this->assertSame('{"from":"other worker"}', $response->getContent());
        $lock->release();
    }

    #[Test]
    public function hits_and_misses_are_counted(): void
    {
        $this->getJson('/api/v1/continents');
        $this->getJson('/api/v1/continents');
        $this->getJson('/api/v1/continents');

        $stats = ApiResponseCache::stats();
        $this->assertSame(1, $stats['miss']);
        $this->assertSame(2, $stats['hit']);
    }
}
