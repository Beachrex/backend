<?php

namespace Tests\Feature;

use App\Models\Beach;
use App\Models\BeachTranslation;
use App\Models\Media;
use App\Models\MediaCollection;
use App\Services\PostGISService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression (owner 2026-10-12, Bretagne): beaches uploaded from the mobile app have no primary photo, so
 * `main_image` was null; the web skips cards without one, and French region/city pages showed no beaches.
 */
class BeachMainImageFallbackTest extends TestCase
{
    use RefreshDatabase;

    private Beach $beach;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([
            \Database\Seeders\StatusSeeder::class,
            \Database\Seeders\MediaTypeSeeder::class,
            \Database\Seeders\MediaCollectionSeeder::class,
        ]);
        $this->beach = Beach::factory()->create([
            'status_id' => 1, 'latitude' => 48.6952, 'longitude' => -1.8781, 'mobile_upload' => true,
            'folder_name' => 'cancale-plage-du-verger_5',
        ]);
        BeachTranslation::create(['beach_id' => $this->beach->id, 'locale' => 'en', 'name' => 'Plage du Verger', 'slug' => 'plage-du-verger']);
        Media::factory()->create([
            'mediable_id' => $this->beach->id,
            'media_collection_id' => MediaCollection::where('code', 'beach_gallery')->value('id'),
            'file_path' => 'img/beaches_mobile/cancale-plage-du-verger_5/sl1.jpg',
            'is_primary' => false,
            'status_id' => 1,
        ]);
    }

    public function test_card_results_use_the_first_gallery_photo(): void
    {
        $result = collect(app(PostGISService::class)->getBeachesWithinRadius(48.6952, -1.8781, 5))->first();

        $this->assertSame('img/beaches_mobile/cancale-plage-du-verger_5/sl1.jpg', data_get(json_decode(json_encode($result), true), 'main_image'));
    }

    public function test_most_visited_uses_the_first_gallery_photo(): void
    {
        // The first media in the eager-load order (primary first) has no file, as on the real French beaches.
        Media::factory()->create([
            'mediable_id' => $this->beach->id,
            'media_collection_id' => MediaCollection::where('code', 'beach_videos')->value('id'),
            'file_path' => '',
            'is_primary' => true,
            'status_id' => 1,
        ]);

        $this->getJson('/api/v1/beaches/most-visited?limit=5', ['Accept-Language' => 'en'])
            ->assertOk()
            ->assertJsonPath('data.0.main_image', 'img/beaches_mobile/cancale-plage-du-verger_5/sl1.jpg');
    }
}
