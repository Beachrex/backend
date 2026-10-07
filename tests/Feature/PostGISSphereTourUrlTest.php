<?php

namespace Tests\Feature;

use App\Models\Beach;
use App\Models\Media;
use App\Models\MediaCollection;
use App\Services\PostGISService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Regression: the 360° tour URL in search/map results ignored mobile_upload (BeachDetailsResource already respects it). */
class PostGISSphereTourUrlTest extends TestCase
{
    use RefreshDatabase;

    private function tourUrl(bool $mobileUpload): ?string
    {
        $this->seed([
            \Database\Seeders\StatusSeeder::class,
            \Database\Seeders\MediaTypeSeeder::class,
            \Database\Seeders\MediaCollectionSeeder::class,
        ]);
        $beach = Beach::factory()->create([
            'latitude' => 43.2566, 'longitude' => 16.6347, 'status_id' => 1,
            'folder_name' => 'hr-114-o-brac', 'mobile_upload' => $mobileUpload,
        ]);
        Media::factory()->create([
            'mediable_id' => $beach->id,
            'media_collection_id' => MediaCollection::where('code', 'beach_360_sphere')->value('id'),
            'file_path' => 'img/beaches/hr-114-o-brac/vtour/panos/p1.jpg',
        ]);

        $result = collect(app(PostGISService::class)->getBeachesWithinRadius(43.2566, 16.6347, 5))->first();
        $result = json_decode(json_encode($result), true);

        return data_get($result, 'media.spheres_360.0.tour_url');
    }

    public function test_regular_upload_uses_img_beaches(): void
    {
        $this->assertSame('img/beaches/hr-114-o-brac/vtour/tour.html', $this->tourUrl(false));
    }

    public function test_mobile_upload_uses_img_beaches_mobile(): void
    {
        $this->assertSame('img/beaches_mobile/hr-114-o-brac/vtour/tour.html', $this->tourUrl(true));
    }
}
