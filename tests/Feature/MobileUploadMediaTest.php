<?php

namespace Tests\Feature;

use App\Models\Beach;
use App\Models\BeachTranslation;
use App\Models\Media;
use App\Models\MediaCollection;
use App\Services\PostGISService;
use App\Support\SphereTour;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Owner report 2026-10-29, Plage du Port Vieux (3247, uploaded from the mobile app):
 * - the 360° panorama exists but its krpano tour was never generated → black "360° view" popup;
 * - `…-video.mp4` sits in the gallery → shown as a broken photo, and there was no "Video" card.
 */
class MobileUploadMediaTest extends TestCase
{
    use RefreshDatabase;

    private const FOLDER = 'biarritz-plage-du-port-vieux_5';

    private Beach $beach;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([
            \Database\Seeders\StatusSeeder::class,
            \Database\Seeders\MediaTypeSeeder::class,
            \Database\Seeders\MediaCollectionSeeder::class,
        ]);
        Storage::fake('spaces');
        config(['filesystems.disks.spaces.key' => 'test-key']);
        $this->beach = Beach::factory()->create([
            'latitude' => 43.4832, 'longitude' => -1.5586, 'status_id' => 1,
            'folder_name' => self::FOLDER, 'mobile_upload' => true,
        ]);
        BeachTranslation::create(['beach_id' => $this->beach->id, 'locale' => 'en', 'name' => 'Plage du Port Vieux', 'slug' => 'plage-du-port-vieux']);
        $this->add('beach_gallery', 'biarritz-plage-du-port-vieux-085-sl1.jpg');
        $this->add('beach_gallery', 'biarritz-plage-du-port-vieux-935-video.mp4', 'image/jpeg'); // wrong MIME on purpose
        $this->add('beach_360_sphere', 'biarritz-plage-du-port-vieux-365-panorama1.jpg');
        Storage::disk('spaces')->put('public/img/beaches_mobile/' . self::FOLDER . '/biarritz-plage-du-port-vieux-365-panorama1.jpg', 'jpg');
    }

    private function add(string $collection, string $file, string $mime = null): void
    {
        Media::factory()->create([
            'mediable_id' => $this->beach->id,
            'media_collection_id' => MediaCollection::where('code', $collection)->value('id'),
            'file_path' => 'img/beaches_mobile/' . self::FOLDER . '/' . $file,
            'file_name' => $file,
            'mime_type' => $mime ?? (str_ends_with($file, '.mp4') ? 'video/mp4' : 'image/jpeg'),
            'status_id' => 1,
        ]);
    }

    private function tourKey(): string
    {
        return 'public/img/beaches_mobile/' . self::FOLDER . '/vtour/tour.html';
    }

    private function details(): array
    {
        return $this->getJson("/api/v1/beach-details/{$this->beach->id}", ['Accept-Language' => 'en'])->assertOk()->json('data.media');
    }

    public function test_video_file_in_the_gallery_is_a_video_not_a_photo(): void
    {
        $media = $this->details();

        $this->assertSame(['biarritz-plage-du-port-vieux-085-sl1.jpg'], array_column($media['gallery'], 'file_name'));
        $this->assertSame(['biarritz-plage-du-port-vieux-935-video.mp4'], array_column($media['videos'], 'file_name'));
    }

    public function test_missing_tour_gives_no_tour_url_but_keeps_the_panorama(): void
    {
        $media = $this->details();

        $this->assertCount(1, $media['spheres_360']);
        $this->assertNull($media['spheres_360'][0]['tour_url']);
        $this->assertStringEndsWith('panorama1.jpg', $media['spheres_360'][0]['url']);
        $this->assertFalse($this->beach->fresh()->has_vtour, 'result stored, storage asked once');
    }

    public function test_existing_tour_is_returned_and_remembered(): void
    {
        Storage::disk('spaces')->put($this->tourKey(), '<html>krpano</html>');

        $media = $this->details();

        $this->assertSame('img/beaches_mobile/' . self::FOLDER . '/vtour/tour.html', $media['spheres_360'][0]['tour_url']);
        $this->assertTrue($this->beach->fresh()->has_vtour);
    }

    public function test_lists_and_map_cards_leave_out_a_known_missing_tour_and_never_ask_storage(): void
    {
        // Unknown: lists keep the old behaviour (tour assumed present) and do not ask storage.
        $this->assertNotEmpty($this->cardSpheres());
        $this->assertNull($this->beach->fresh()->has_vtour);

        SphereTour::remember($this->beach, false);

        $this->assertSame([], $this->cardSpheres());
        $card = collect($this->getJson('/api/v1/beaches?per_page=50', ['Accept-Language' => 'en'])->json('data'))->firstWhere('id', $this->beach->id);
        $this->assertArrayNotHasKey('spheres_360', $card['media'] ?? []);
        $this->assertSame(['img/beaches_mobile/' . self::FOLDER . '/biarritz-plage-du-port-vieux-935-video.mp4'], array_column($card['media']['videos'], 'url'));
    }

    public function test_check_command_records_present_and_missing_tours(): void
    {
        $other = Beach::factory()->create(['status_id' => 1, 'folder_name' => 'sl-6-k', 'mobile_upload' => false]);
        Media::factory()->create([
            'mediable_id' => $other->id,
            'media_collection_id' => MediaCollection::where('code', 'beach_360_sphere')->value('id'),
            'file_path' => 'img/beaches/sl-6-k/sl-6-k-Panorama.jpg', 'status_id' => 1,
        ]);
        Storage::disk('spaces')->put('public/img/beaches/sl-6-k/vtour/tour.html', 'x');
        Storage::disk('spaces')->put('public/img/beaches/sl-6-k/sl-6-k-Panorama.jpg', 'x');

        $this->artisan('media:check-vtours --dry-run')->expectsOutputToContain('Dry run: 1 with tour, 1 without tour')->assertSuccessful();
        $this->assertNull($this->beach->fresh()->has_vtour);

        $this->artisan('media:check-vtours')->assertSuccessful();
        $this->assertFalse($this->beach->fresh()->has_vtour);
        $this->assertTrue($other->fresh()->has_vtour);

        // Tour generated later: the weekly --all run picks it up.
        Storage::disk('spaces')->put($this->tourKey(), 'x');
        $this->artisan('media:check-vtours')->expectsOutputToContain('0 with tour, 0 without tour')->assertSuccessful();
        $this->artisan('media:check-vtours --all')->assertSuccessful();
        $this->assertTrue($this->beach->fresh()->has_vtour);
    }

    public function test_unreachable_storage_never_marks_a_tour_missing(): void
    {
        // Panorama not found either → storage cannot be trusted: keep the tour, store nothing.
        Storage::disk('spaces')->delete('public/img/beaches_mobile/' . self::FOLDER . '/biarritz-plage-du-port-vieux-365-panorama1.jpg');

        $this->assertNotNull($this->details()['spheres_360'][0]['tour_url']);
        $this->assertNull($this->beach->fresh()->has_vtour);

        // No credentials in the environment → not asked at all.
        config(['filesystems.disks.spaces.key' => null]);
        $this->assertNull(SphereTour::existsInStorage($this->beach));
    }

    private function cardSpheres(): array
    {
        $result = collect(app(PostGISService::class)->getBeachesWithinRadius(43.4832, -1.5586, 5))->first();

        return data_get(json_decode(json_encode($result), true), 'media.spheres_360', []);
    }
}
