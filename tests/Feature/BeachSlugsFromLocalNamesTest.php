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
use App\Models\Region;
use App\Models\RegionTranslation;
use App\Models\Status;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Owner 2026-10-23: beach URLs ended in an English "beach-" slug (`/pl/plaze/.../beach-svetilnik`). */
class BeachSlugsFromLocalNamesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Status::insert([['id' => 1, 'name' => 'active'], ['id' => 2, 'name' => 'inactive']]);
        Continent::create(['code' => 'EU']);
        ContinentTranslation::insert([['continent_code' => 'EU', 'locale' => 'en', 'name' => 'Europe', 'slug' => 'europe']]);
        Country::create(['code' => 'HR', 'continent_code' => 'EU']);
        CountryTranslation::insert([['country_code' => 'HR', 'locale' => 'en', 'name' => 'Croatia', 'slug' => 'croatia']]);
        Region::insert([['id' => 1, 'code' => 'IS', 'country_code' => 'HR', 'created_at' => now(), 'updated_at' => now()]]);
        RegionTranslation::insert([['region_id' => 1, 'locale' => 'en', 'name' => 'Istra', 'slug' => 'istra']]);
        City::insert([
            ['id' => 1, 'code' => 'KRK', 'country_code' => 'HR', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'code' => 'PAG', 'country_code' => 'HR', 'created_at' => now(), 'updated_at' => now()],
        ]);
        CityTranslation::insert([
            ['city_id' => 1, 'locale' => 'en', 'name' => 'Krk', 'slug' => 'krk'],
            ['city_id' => 2, 'locale' => 'en', 'name' => 'Pag', 'slug' => 'pag'],
        ]);
    }

    /** @param array<string, array{0: string, 1: string}> $rows locale => [name, slug] */
    private function beach(array $rows, int $cityId = 1): int
    {
        $beach = Beach::create([
            'latitude' => '45', 'longitude' => '14', 'country_code' => 'HR', 'region_id' => 1, 'city_id' => $cityId,
            'status_id' => 1, 'folder_name' => 'f', 'mobile_upload' => false,
        ]);
        foreach ($rows as $locale => [$name, $slug]) {
            BeachTranslation::insert(['beach_id' => $beach->id, 'locale' => $locale, 'name' => $name, 'slug' => $slug, 'description' => null]);
        }

        return $beach->id;
    }

    private function slug(int $beachId, string $locale = 'en'): string
    {
        return BeachTranslation::where('beach_id', $beachId)->where('locale', $locale)->value('slug');
    }

    private function migrate(): void
    {
        $migration = require database_path('migrations/2026_10_23_140000_beach_slugs_from_local_names.php');
        $migration->up();
        $migration->up(); // idempotent
    }

    #[Test]
    public function slugs_follow_the_local_name_and_old_slugs_redirect(): void
    {
        $svetilnik = $this->beach(['en' => ['Svetilnik', 'beach-svetilnik'], 'pl' => ['Svetilnik', 'beach-svetilnik'], 'ru' => ['Светильник', 'beach-svetilnik']]);
        $garoupe = $this->beach(['en' => ['Plage de la Garoupe', 'beach-de-la-garoupe']]);
        $zal1 = $this->beach(['en' => ['Žal', 'beach-zal-krk']], 1);
        $zal2 = $this->beach(['en' => ['Žal', 'beach-zal-pag']], 2);
        $krk = $this->beach(['en' => ['Krk', 'beach-krk']], 2);
        $kept = $this->beach(['en' => ['Bondi', 'bondi']]);

        $this->migrate();

        $this->assertSame('svetilnik', $this->slug($svetilnik));
        $this->assertSame('svetilnik', $this->slug($svetilnik, 'pl'));
        $this->assertSame('svetilnik', $this->slug($svetilnik, 'ru'), 'one Latin slug in every language');
        $this->assertSame('plage-de-la-garoupe', $this->slug($garoupe), 'the local word for beach comes back');
        $this->assertSame('zal-krk', $this->slug($zal1), 'a repeated name gets its town');
        $this->assertSame('zal-pag', $this->slug($zal2));
        $this->assertSame('krk-pag', $this->slug($krk), 'a slug equal to a place slug gets its town');
        $this->assertSame('bondi', $this->slug($kept), 'slugs without the prefix stay');
        $this->assertSame(3, DB::table('beach_slug_redirects')->where('beach_id', $svetilnik)->count(), 'one redirect per language');

        // Old URLs still find the beach; the response carries the new slug.
        $this->getJson('/api/v1/beach-details/beach-svetilnik', ['Accept-Language' => 'pl'])
            ->assertOk()->assertJsonPath('data.basic_info.slug', 'svetilnik');
        $this->getJson('/api/v1/beach-details/svetilnik', ['Accept-Language' => 'pl'])->assertOk();
        $this->getJson('/api/v1/beaches/beach-de-la-garoupe')->assertOk();
        $this->getJson('/api/v1/beaches/no-such-beach')->assertNotFound();
        $this->getJson('/api/v1/resolve-slug/beach-svetilnik?segments=3', ['Accept-Language' => 'de'])
            ->assertOk()->assertJsonPath('data.type', 'beach');
    }

    #[Test]
    public function an_earlier_redirect_is_pointed_at_the_newest_slug(): void
    {
        $id = $this->beach(['en' => ['Svetilnik', 'beach-svetilnik']]);
        DB::table('beach_slug_redirects')->insert(['old_slug' => 'plaza-svetilnik', 'new_slug' => 'beach-svetilnik', 'locale' => 'en', 'beach_id' => $id, 'created_at' => now(), 'updated_at' => now()]);

        $this->migrate();

        $this->assertSame('svetilnik', DB::table('beach_slug_redirects')->where('old_slug', 'plaza-svetilnik')->value('new_slug'));
    }
}
