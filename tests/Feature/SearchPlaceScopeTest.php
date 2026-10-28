<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Scout\Builder;
use Laravel\Scout\EngineManager;
use Laravel\Scout\Engines\CollectionEngine;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Follow-up 2026-10-28 on dev: "toscana" counted 43 beaches but 0 towns (the Toscana page: 21 towns), "kvarner"
 * 0 islands (page: 4), "krk" 0 towns (page: 19). The place filter on the city and island indexes matched nothing
 * (their documents did not carry the new region_id / island_id). Search must not depend on that: inside a named
 * place it counts and lists like the place pages do.
 *
 * The "stale" engine is Scout's collection engine whose documents lack region_id, island_id and city_id, as on dev.
 */
class SearchPlaceScopeTest extends TestCase
{
    use RefreshDatabase;

    private int $toscana;

    private int $elba;

    private array $beaches = [];

    protected function setUp(): void
    {
        parent::setUp();

        app(EngineManager::class)->extend('stale', fn () => new class extends CollectionEngine
        {
            protected function searchModels(Builder $builder)
            {
                if (array_intersect(array_keys($builder->wheres), ['region_id', 'island_id', 'city_id'])) {
                    return $builder->model->newCollection();
                }

                return parent::searchModels($builder);
            }
        });
        config(['scout.driver' => 'stale']);

        DB::table('status')->insertOrIgnore([['id' => 1, 'name' => 'active'], ['id' => 2, 'name' => 'inactive']]);
        DB::table('countries')->insertOrIgnore([['code' => 'IT']]);
        DB::table('country_translation')->insert(['country_code' => 'IT', 'locale' => 'en', 'name' => 'Italy', 'slug' => 'italy']);

        $this->toscana = DB::table('region')->insertGetId(['code' => 'toscana', 'country_code' => 'IT', 'status_id' => 1]);
        DB::table('region_translation')->insert(['region_id' => $this->toscana, 'locale' => 'en', 'name' => 'Toscana', 'slug' => 'toscana']);

        $this->elba = DB::table('island')->insertGetId(['code' => 'elba', 'country_code' => 'IT', 'region_id' => $this->toscana, 'status_id' => 1]);
        DB::table('island_translation')->insert(['island_id' => $this->elba, 'locale' => 'en', 'name' => 'Elba', 'slug' => 'elba']);

        $grosseto = $this->city('grosseto', 'Grosseto', null);
        $viareggio = $this->city('viareggio', 'Viareggio', null);
        $portoferraio = $this->city('portoferraio', 'Portoferraio', $this->elba);
        $ghost = $this->city('ghost-town', 'Ghost Town', null); // only an inactive beach

        $this->beach('Principina a Mare', $grosseto, null, 50);
        $this->beach('Marina di Grosseto', $grosseto, null, 900);
        $this->beach('Viareggio Beach', $viareggio, null, 10);
        $this->beach('Sansone', $portoferraio, $this->elba, 300);
        $this->beach('Closed beach', $ghost, null, 9999, status: 2);
    }

    private function city(string $code, string $name, ?int $island): int
    {
        $id = DB::table('city')->insertGetId([
            'code' => $code, 'country_code' => 'IT', 'region_id' => $this->toscana, 'island_id' => $island, 'status_id' => 1,
        ]);
        DB::table('city_translation')->insert(['city_id' => $id, 'locale' => 'en', 'name' => $name, 'slug' => $code]);

        return $id;
    }

    private function beach(string $name, int $city, ?int $island, int $views, int $status = 1): void
    {
        $id = DB::table('beach')->insertGetId([
            'name' => $name, 'country_code' => 'IT', 'status_id' => $status, 'latitude' => 43.0, 'longitude' => 10.5,
            'region_id' => $this->toscana, 'city_id' => $city, 'island_id' => $island, 'beach_views' => $views,
        ]);
        DB::table('beach_translation')->insert(['beach_id' => $id, 'locale' => 'en', 'name' => $name, 'slug' => str($name)->slug()]);
        $this->beaches[$name] = $id;
    }

    private function search(string $q, int $limit = 10): \Illuminate\Testing\TestResponse
    {
        return $this->withHeaders(['Accept-Language' => 'en'])
            ->getJson('/api/v1/search?_nocache=1&limit=' . $limit . '&q=' . urlencode($q))
            ->assertOk();
    }

    #[Test]
    public function a_region_counts_its_towns_islands_and_beaches_like_the_region_page(): void
    {
        $search = $this->search('toscana');

        $search->assertJsonPath('meta.place.type', 'region')
            ->assertJsonPath('meta.totals_by_type.beaches', 4)
            ->assertJsonPath('meta.totals_by_type.cities', 3)
            ->assertJsonPath('meta.totals_by_type.islands', 1)
            ->assertJsonCount(3, 'data.cities')
            ->assertJsonCount(1, 'data.islands');

        // The numbers the Toscana page shows
        $page = $this->getJson('/api/v1/regions/toscana')->assertOk();
        $this->assertSame($page->json('data.beaches_count'), $search->json('meta.totals_by_type.beaches'));
        $this->assertCount($search->json('meta.totals_by_type.cities'), $this->getJson('/api/v1/cities?region_code=toscana')->json('data'));
        $this->assertCount($search->json('meta.totals_by_type.islands'), $this->getJson('/api/v1/islands?region_code=toscana')->json('data'));
    }

    #[Test]
    public function an_island_counts_its_towns_and_beaches_like_the_island_page(): void
    {
        $search = $this->search('elba');

        $search->assertJsonPath('meta.place.type', 'island')
            ->assertJsonPath('meta.totals_by_type.beaches', 1)
            ->assertJsonPath('meta.totals_by_type.cities', 1)
            ->assertJsonPath('data.cities.0.name', 'Portoferraio');

        $this->assertSame($this->getJson('/api/v1/islands/elba')->json('data.beaches_count'), 1);
        $this->assertCount(1, $this->getJson('/api/v1/cities?island_code=elba')->json('data'));
    }

    #[Test]
    public function a_town_counts_its_active_beaches(): void
    {
        $this->search('grosseto')
            ->assertJsonPath('meta.place.type', 'city')
            ->assertJsonPath('meta.totals_by_type.beaches', 2)
            ->assertJsonCount(2, 'data.beaches');
        $this->search('ghost town')
            ->assertJsonPath('meta.totals_by_type.beaches', 0, 'an inactive beach is not counted');
    }

    #[Test]
    public function beaches_in_a_place_are_listed_most_viewed_first_and_towns_with_most_beaches_first(): void
    {
        $search = $this->search('toscana', 2);

        $search->assertJsonCount(2, 'data.beaches')
            ->assertJsonPath('meta.totals_by_type.beaches', 4);
        $this->assertSame(['Marina di Grosseto', 'Sansone'], array_column($search->json('data.beaches'), 'name'));
        $this->assertSame('Grosseto', $search->json('data.cities.0.name'));
        $this->assertNotContains('Closed beach', array_column($this->search('toscana', 20)->json('data.beaches'), 'name'));
    }
}
