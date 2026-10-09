<?php

namespace Tests\Feature\AI;

use App\Services\AI\Chat\PlaceResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Normalizer;
use Tests\TestCase;

/** TASK-003: place names from the chat → island / city / region, in any language, no guessing. */
class PlaceResolverTest extends TestCase
{
    use RefreshDatabase;

    private array $ids = [];

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        DB::table('status')->insertOrIgnore([['id' => 1, 'name' => 'active'], ['id' => 2, 'name' => 'inactive']]);
        $add = function (string $type, string $code, array $translations) {
            $id = DB::table($type)->insertGetId(['code' => $code]);
            foreach ($translations as $locale => [$name, $slug]) {
                DB::table("{$type}_translation")->insert(["{$type}_id" => $id, 'locale' => $locale, 'name' => $name, 'slug' => $slug]);
            }

            return $id;
        };
        $this->ids['brac'] = $add('island', 'BRA', ['hr' => ['Brač', 'brac'], 'en' => ['Brač Island', 'brac-island']]);
        $this->ids['pag'] = $add('island', 'PAG', ['hr' => ['Pag', 'pag']]);
        $this->ids['hvar-island'] = $add('island', 'HVR', ['en' => ['Hvar', 'hvar']]);
        $this->ids['hvar-town'] = $add('city', 'hvar', ['en' => ['Hvar', 'hvar-town']]);
        $this->ids['makarska'] = $add('city', 'makarska', ['hr' => ['Makarska', 'makarska'], 'de' => ['Makarska', 'makarska']]);
        $this->ids['istria'] = $add('region', 'HR-ISTRIA', ['en' => ['Istria', 'istria'], 'hr' => ['Istra', 'istra'], 'de' => ['Istrien', 'istrien']]);
    }

    private function resolve(?string $place): ?array
    {
        return app(PlaceResolver::class)->resolve($place, 'en');
    }

    public function test_names_and_spellings(): void
    {
        foreach (['Brač', 'brac', 'BRAC', 'otok Brač', 'Brač Island', Normalizer::normalize('Brač', Normalizer::FORM_D)] as $name) {
            $this->assertSame(['type' => 'island', 'id' => $this->ids['brac']], array_intersect_key($this->resolve($name) ?? [], ['type' => 1, 'id' => 1]), $name);
        }
        $this->assertSame('city', $this->resolve('Makarska')['type']);
        $this->assertSame($this->ids['istria'], $this->resolve('Istrien')['id']);
    }

    public function test_inflected_forms(): void
    {
        $this->assertSame($this->ids['brac'], $this->resolve('Braču')['id']);   // na Braču
        $this->assertSame($this->ids['pag'], $this->resolve('Pagu')['id']);     // na Pagu
        $this->assertSame($this->ids['makarska'], $this->resolve('Makarski')['id']); // v Makarski
        $this->assertSame($this->ids['istria'], $this->resolve('Istri')['id']); // v Istri
    }

    public function test_island_wins_over_a_town_with_the_same_name(): void
    {
        $this->assertSame(['island', $this->ids['hvar-island']], [$this->resolve('Hvar')['type'], $this->resolve('Hvar')['id']]);
    }

    public function test_unknown_or_too_short_is_null(): void
    {
        $this->assertNull($this->resolve('Atlantis'));
        $this->assertNull($this->resolve('Br'));
        $this->assertNull($this->resolve(''));
        $this->assertNull($this->resolve(null));
    }

    public function test_display_name_uses_the_locale(): void
    {
        $this->assertSame('Istra', app(PlaceResolver::class)->resolve('Istria', 'hr')['name']);
    }
}
