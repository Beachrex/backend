<?php

namespace Tests\Feature;

use App\Filament\BrAdmin\Resources\BeachResource\Pages\EditBeach;
use App\Models\Beach;
use App\Models\BeachTranslation;
use App\Models\Media;
use App\Models\User;
use App\Support\BeachDetailsCache;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Owner 2026-10-30: beach details are cached for 30 days and cleared on every change, plus a "clear cache" button on
 * each beach in the admin (BeachDetailsCache).
 */
class BeachDetailsCacheTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        \App\Models\Status::insert([['id' => 1, 'name' => 'active'], ['id' => 2, 'name' => 'inactive']]);
        \App\Models\Continent::create(['code' => 'EU']);
        \App\Models\ContinentTranslation::insert([['continent_code' => 'EU', 'locale' => 'en', 'name' => 'Europe', 'slug' => 'europe']]);
        \App\Models\Country::create(['code' => 'HR', 'continent_code' => 'EU']);
        \App\Models\CountryTranslation::insert([['country_code' => 'HR', 'locale' => 'en', 'name' => 'Croatia', 'slug' => 'croatia']]);
        \App\Models\Region::insert([['id' => 1, 'code' => 'KV', 'country_code' => 'HR', 'created_at' => now(), 'updated_at' => now()]]);
        \App\Models\RegionTranslation::insert([['region_id' => 1, 'locale' => 'en', 'name' => 'Kvarner', 'slug' => 'kvarner']]);
        \App\Models\Island::insert([['id' => 1, 'code' => 'CRS', 'created_at' => now(), 'updated_at' => now()]]);
        \App\Models\IslandTranslation::insert([['island_id' => 1, 'locale' => 'en', 'name' => 'Cres', 'slug' => 'cres']]);
        \App\Models\City::insert([['id' => 1, 'code' => 'CRS', 'country_code' => 'HR', 'created_at' => now(), 'updated_at' => now()]]);
        \App\Models\CityTranslation::insert([['city_id' => 1, 'locale' => 'en', 'name' => 'Cres', 'slug' => 'cres']]);
        Cache::flush();
    }

    private function beach(string $slug, int $status = 1): Beach
    {
        $beach = Beach::create([
            'latitude' => '44.95', 'longitude' => '14.46', 'country_code' => 'HR',
            'region_id' => 1, 'island_id' => 1, 'city_id' => 1, 'status_id' => $status,
            'folder_name' => $slug, 'mobile_upload' => false,
        ]);
        BeachTranslation::insert([
            ['beach_id' => $beach->id, 'locale' => 'en', 'name' => ucfirst($slug), 'slug' => $slug, 'description' => 'Old text'],
        ]);

        return $beach;
    }

    private function details(Beach $beach)
    {
        return $this->getJson("/api/v1/beach-details/{$beach->id}", ['Accept-Language' => 'en', 'X-Beachrex-Client' => 'test'])
            ->assertOk();
    }

    public function test_entries_are_kept_for_30_days(): void
    {
        $this->assertSame(30 * 24 * 3600, BeachDetailsCache::TTL);
    }

    public function test_saving_a_translation_shows_the_change_at_once(): void
    {
        $beach = $this->beach('alpha');
        $this->details($beach);
        $this->assertTrue(Cache::has(BeachDetailsCache::key('en', $beach->id)));

        BeachTranslation::where('beach_id', $beach->id)->first()->update(['description' => 'New text']);

        $this->assertFalse(Cache::has(BeachDetailsCache::key('en', $beach->id)));
        $this->assertStringContainsString('New text', json_encode($this->details($beach)->json()));
    }

    public function test_saving_beach_media_clears_only_that_beach(): void
    {
        $alpha = $this->beach('alpha');
        $beta = $this->beach('beta');
        $this->details($alpha);
        $this->details($beta);

        $media = new Media();
        $media->forceFill(['mediable_type' => Beach::class, 'mediable_id' => $alpha->id]);
        // Only the model event matters here, not a real media row.
        Media::getEventDispatcher()->dispatch('eloquent.saved: '.Media::class, $media);

        $this->assertFalse(Cache::has(BeachDetailsCache::key('en', $alpha->id)));
        $this->assertTrue(Cache::has(BeachDetailsCache::key('en', $beta->id)));
    }

    /**
     * Which beach saves clear every beach. (The array cache in tests is flushed by the existing beach-count clearing
     * on every beach save, so the decision is tested directly; on Redis only the beach-count keys are deleted.)
     */
    public function test_only_create_delete_publish_and_move_clear_every_beach(): void
    {
        $created = $this->beach('alpha');
        $this->assertTrue($created->affectsOtherBeachDetails());

        $edited = Beach::find($created->id);
        $edited->update(['latitude' => '44.96']);
        $this->assertFalse($edited->affectsOtherBeachDetails());

        $published = Beach::find($created->id);
        $published->update(['status_id' => 2]);
        $this->assertTrue($published->affectsOtherBeachDetails());

        $moved = Beach::find($created->id);
        $moved->update(['city_id' => null]);
        $this->assertTrue($moved->affectsOtherBeachDetails());

        $deleted = Beach::find($created->id);
        $deleted->delete();
        $this->assertTrue($deleted->affectsOtherBeachDetails());
    }

    public function test_forget_all_makes_every_old_entry_unreachable(): void
    {
        $beach = $this->beach('alpha');
        $this->details($beach);
        $old = BeachDetailsCache::key('en', $beach->id);

        BeachDetailsCache::forgetAll();

        $this->assertNotSame($old, BeachDetailsCache::key('en', $beach->id));
        $this->assertFalse(Cache::has(BeachDetailsCache::key('en', $beach->id)));
    }

    public function test_admin_clear_cache_button_clears_the_beach(): void
    {
        $beach = $this->beach('alpha');
        $this->details($beach);
        $this->assertTrue(Cache::has(BeachDetailsCache::key('en', $beach->id)));

        $this->actingAs(User::factory()->create(['is_admin' => true]));
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Livewire::test(EditBeach::class, ['record' => $beach->getRouteKey()])
            ->assertActionExists('clearCache')
            ->callAction('clearCache')
            ->assertNotified('Predpomnilnik plaže je počiščen');

        $this->assertFalse(Cache::has(BeachDetailsCache::key('en', $beach->id)));
    }
}
