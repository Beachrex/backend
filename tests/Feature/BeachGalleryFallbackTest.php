<?php

namespace Tests\Feature;

use App\Models\Beach;
use App\Models\BeachTranslation;
use App\Models\Media;
use App\Models\MediaCollection;
use App\Services\PostGISService;
use App\Support\BeachGallery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression (owner 2026-10-11, Njivice id 844): a beach with photos only in other collections (parking,
 * 360° panorama) showed no photos. The gallery now falls back to the beach's other photo collections.
 */
class BeachGalleryFallbackTest extends TestCase
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
            'latitude' => 45.1707, 'longitude' => 14.5443, 'status_id' => 1, 'folder_name' => 'hr-137-o-krk',
        ]);
    }

    private function addMedia(string $collection, string $file): void
    {
        Media::factory()->create([
            'mediable_id' => $this->beach->id,
            'media_collection_id' => MediaCollection::where('code', $collection)->value('id'),
            'file_path' => "img/beaches/hr-137-o-krk/{$file}",
            'file_name' => $file,
            'status_id' => 1,
        ]);
    }

    private function cardGalleryUrls(): array
    {
        $result = collect(app(PostGISService::class)->getBeachesWithinRadius(45.1707, 14.5443, 5))->first();

        return array_column(data_get(json_decode(json_encode($result), true), 'media.gallery', []), 'url');
    }

    public function test_beach_without_gallery_photos_shows_its_other_photos(): void
    {
        $this->addMedia('beach_360_sphere', 'hr-137-o-krk-Panorama.jpg');
        $this->addMedia('parking_images', 'njivice-camp7.jpg');

        $this->assertSame(['img/beaches/hr-137-o-krk/njivice-camp7.jpg'], $this->cardGalleryUrls());
    }

    public function test_beach_page_api_shows_the_other_photos_in_the_gallery(): void
    {
        BeachTranslation::create(['beach_id' => $this->beach->id, 'locale' => 'en', 'name' => 'Njivice', 'slug' => 'beach-njivice']);
        $this->addMedia('beach_360_sphere', 'hr-137-o-krk-Panorama.jpg');
        $this->addMedia('parking_images', 'njivice-camp7.jpg');

        $media = $this->getJson("/api/v1/beach-details/{$this->beach->id}", ['Accept-Language' => 'en'])
            ->assertOk()->json('data.media');

        $this->assertSame(['njivice-camp7.jpg'], array_column($media['gallery'], 'file_name'));
        $this->assertCount(1, $media['spheres_360']);
        $this->assertCount(1, $media['parking_images']);
    }

    public function test_gallery_photos_win_over_other_collections(): void
    {
        $this->addMedia('parking_images', 'parking.jpg');
        $this->addMedia('beach_gallery', 'beach.jpg');

        $this->assertSame(['img/beaches/hr-137-o-krk/beach.jpg'], $this->cardGalleryUrls());
    }

    public function test_pick_never_uses_videos_or_spheres(): void
    {
        $grouped = collect([
            'beach_videos' => collect(['video']),
            'beach_360_sphere' => collect(['sphere']),
        ]);

        $this->assertTrue(BeachGallery::pick($grouped)->isEmpty());
        $this->assertSame(['spot', 'parking'], BeachGallery::pick(collect([
            'parking_images' => collect(['parking']),
            'beach_spot_images' => collect(['spot']),
        ]))->all());
    }
}
