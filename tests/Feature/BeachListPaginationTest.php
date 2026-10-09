<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\Feature\AI\Concerns\SeedsItineraryBeaches;
use Tests\TestCase;

/**
 * Owner 2026-10-09 (city page Risskov showed beaches from Croatia, Germany, Italy and loaded slowly):
 * GET /beaches ignored `city`/`region`/`island`, returned up to 5000 beaches and ignored `page`
 * (the beach sitemap therefore listed the same 50 beaches 50 times).
 */
class BeachListPaginationTest extends TestCase
{
    use RefreshDatabase, SeedsItineraryBeaches;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->seedItineraryBeaches(); // 12 HR beaches in Rovinj, Split, Dubrovnik
    }

    private function names(\Illuminate\Testing\TestResponse $r): array
    {
        return collect($r->json('data'))->pluck('id')->map(fn ($id) => array_search($id, $this->beachIds, true))->all();
    }

    public function test_city_region_and_island_aliases_filter_the_list(): void
    {
        $split = $this->getJson('/api/v1/beaches?city=split')->assertOk();
        $this->assertEqualsCanonicalizing(['Bacvice', 'Znjan', 'Kasjuni', 'Trstenik'], $this->names($split));
        $split->assertJsonPath('meta.total', 4);

        $cityId = DB::table('city')->where('code', 'dubrovnik')->value('id');
        $this->assertCount(4, $this->getJson("/api/v1/beaches?city={$cityId}")->assertOk()->json('data'));

        $this->getJson('/api/v1/beaches?region=HR-ISTRIA')->assertOk()->assertJsonPath('meta.total', 4);
        $this->getJson('/api/v1/beaches?city=nowhere')->assertOk()->assertJsonPath('meta.total', 0)->assertJsonPath('data', []);
    }

    public function test_pages_are_disjoint_ordered_and_complete(): void
    {
        $seen = [];
        foreach ([1, 2, 3] as $page) {
            $r = $this->getJson("/api/v1/beaches?per_page=5&page={$page}")->assertOk()
                ->assertJsonPath('meta.current_page', $page)
                ->assertJsonPath('meta.last_page', 3)
                ->assertJsonPath('meta.total', 12);
            $seen = array_merge($seen, $this->names($r));
        }

        $this->assertCount(12, array_unique($seen));
        $this->assertSame(['Bacvice', 'Banje', 'Mulini', 'Znjan', 'Lone'], array_slice($seen, 0, 5)); // most viewed first
    }

    public function test_limit_is_an_alias_of_per_page_so_the_sitemap_pages_through(): void
    {
        $first = $this->names($this->getJson('/api/v1/beaches?limit=5&page=1')->assertOk());
        $second = $this->names($this->getJson('/api/v1/beaches?limit=5&page=2')->assertOk());

        $this->assertCount(5, $first);
        $this->assertSame([], array_intersect($first, $second));
    }

    public function test_default_and_maximum_page_size(): void
    {
        $this->getJson('/api/v1/beaches')->assertOk()->assertJsonPath('meta.per_page', 20);
        $this->getJson('/api/v1/beaches?per_page=5000')->assertOk()->assertJsonPath('meta.per_page', 100);
        $this->getJson('/api/v1/beaches?page=99')->assertOk()->assertJsonPath('data', []);
    }

    public function test_newest_sort(): void
    {
        DB::table('beach')->where('id', $this->beachIds['Borik'])->update(['created_at' => now()->addDay()]);

        $this->assertSame('Borik', $this->names($this->getJson('/api/v1/beaches?sort=newest&per_page=1')->assertOk())[0]);
    }
}
