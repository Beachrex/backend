<?php

namespace Tests\Feature;

use App\Support\Watermark;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

/**
 * ADR 2026-10-29: Imagor draws the logo watermark on mobile-app photos. `media:publish-watermark`
 * (run by every deploy) puts the PNG on the shared bucket once, at a versioned path.
 */
class PublishWatermarkTest extends TestCase
{
    public function test_the_watermark_asset_is_a_transparent_png(): void
    {
        $bytes = (string) file_get_contents(Watermark::sourcePath());
        $info = getimagesizefromstring($bytes);

        $this->assertSame(IMAGETYPE_PNG, $info[2]);
        $this->assertGreaterThanOrEqual(600, $info[0], 'wide enough for 2800 px photos at ~20 %');
        $this->assertGreaterThan($info[1], $info[0], 'horizontal logo');

        // A corner pixel is fully transparent (alpha 127 in GD).
        $img = imagecreatefromstring($bytes);
        $this->assertSame(127, (imagecolorat($img, 0, 0) >> 24) & 0x7F);
    }

    public function test_uploads_the_watermark_public_when_missing(): void
    {
        Storage::fake('spaces');

        $this->artisan('media:publish-watermark')
            ->expectsOutputToContain('Watermark uploaded')
            ->assertSuccessful();

        $key = 'public/img/watermark/beachrex-watermark-v1.png';
        $this->assertSame($key, Watermark::storageKey());
        Storage::disk('spaces')->assertExists($key);
        $this->assertSame(file_get_contents(Watermark::sourcePath()), Storage::disk('spaces')->get($key));
        $this->assertSame('public', Storage::disk('spaces')->getVisibility($key));
    }

    public function test_running_again_never_overwrites_an_existing_file(): void
    {
        Storage::fake('spaces');
        Storage::disk('spaces')->put(Watermark::storageKey(), 'already-there');

        $this->artisan('media:publish-watermark')->assertSuccessful();
        $this->artisan('media:publish-watermark')
            ->expectsOutputToContain('already on the bucket')
            ->assertSuccessful();

        $this->assertSame('already-there', Storage::disk('spaces')->get(Watermark::storageKey()));
    }

    public function test_fails_when_the_bucket_refuses_the_write(): void
    {
        // The real spaces disk has throw=false: a failed write returns false instead of throwing.
        $disk = Mockery::mock(Filesystem::class);
        $disk->shouldReceive('exists')->andReturn(false);
        $disk->shouldReceive('put')->once()->andReturn(false);
        Storage::set('spaces', $disk);

        $this->artisan('media:publish-watermark')
            ->expectsOutputToContain('Could not write')
            ->assertFailed();
    }
}
