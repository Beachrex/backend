<?php

namespace Tests\Feature;

use App\Models\Beach;
use App\Models\Media;
use App\Models\MediaCollection;
use App\Models\MediaType;
use App\Models\User;
use App\Support\ImageCopyright;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Owner, 2026-10-08: photos are works of MOJA PLAŽA, d.o.o.; JPEGs carry an IPTC copyright block
 * (on upload, and via `media:embed-copyright` for existing files).
 */
class ImageCopyrightTest extends TestCase
{
    use RefreshDatabase;

    private function jpeg(): string
    {
        $img = imagecreatetruecolor(40, 30);
        imagefilledrectangle($img, 0, 0, 39, 29, imagecolorallocate($img, 0, 178, 201));
        ob_start();
        imagejpeg($img, null, 90);
        imagedestroy($img);

        return (string) ob_get_clean();
    }

    public function test_embeds_creator_credit_and_copyright_into_a_jpeg(): void
    {
        $original = $this->jpeg();
        $this->assertFalse(ImageCopyright::hasNotice($original));

        $out = ImageCopyright::embed($original, 2026);

        $this->assertNotNull($out);
        $iptc = ImageCopyright::readIptc($out);
        $this->assertSame('© 2026 MOJA PLAŽA, d.o.o.', $iptc['2#116'][0]);
        $this->assertSame('MOJA PLAŽA, d.o.o.', $iptc['2#080'][0]);
        $this->assertSame('Beachrex', $iptc['2#110'][0]);
        $this->assertTrue(ImageCopyright::hasNotice($out));
        // Still a valid image of the same size (pixels untouched)
        $this->assertSame([40, 30], array_slice(getimagesizefromstring($out), 0, 2));
    }

    public function test_other_formats_are_left_alone(): void
    {
        $img = imagecreatetruecolor(4, 4);
        ob_start();
        imagepng($img);
        $png = (string) ob_get_clean();

        $this->assertNull(ImageCopyright::embed($png));
        $this->assertNull(ImageCopyright::embed('not an image'));
    }

    public function test_api_upload_writes_the_copyright_into_the_stored_jpeg(): void
    {
        $this->seed([\Database\Seeders\StatusSeeder::class, \Database\Seeders\MediaTypeSeeder::class, \Database\Seeders\MediaCollectionSeeder::class]);
        Storage::fake(config('filesystems.default'));
        $beach = Beach::factory()->create();

        $this->actingAs(User::factory()->admin()->create(), 'sanctum')
            ->postJson('/api/v1/media/upload', [
                'file' => UploadedFile::fake()->createWithContent('beach.jpg', $this->jpeg()),
                'mediable_type' => 'Beach',
                'mediable_id' => $beach->id,
                'media_type_code' => 'image',
                'collection_code' => 'beach_gallery',
            ])
            ->assertCreated();

        $media = Media::firstOrFail();
        $this->assertTrue(ImageCopyright::hasNotice(Storage::disk($media->disk)->get($media->file_path)));
    }

    public function test_backfill_command_writes_missing_notices_and_skips_the_rest(): void
    {
        $this->seed([\Database\Seeders\StatusSeeder::class, \Database\Seeders\MediaTypeSeeder::class, \Database\Seeders\MediaCollectionSeeder::class]);
        Storage::fake('spaces');
        $beach = Beach::factory()->create();
        $make = fn (string $path) => Media::factory()->create([
            'mediable_type' => Beach::class,
            'mediable_id' => $beach->id,
            'media_type_id' => MediaType::where('code', 'image')->value('id'),
            'media_collection_id' => MediaCollection::where('code', 'beach_gallery')->value('id'),
            'disk' => 'spaces',
            'file_path' => $path,
            'mime_type' => 'image/jpeg',
        ]);

        $make('img/beaches/b1/new.jpg');
        Storage::disk('spaces')->put('public/img/beaches/b1/new.jpg', $this->jpeg());
        $make('img/beaches/b1/done.jpg');
        Storage::disk('spaces')->put('public/img/beaches/b1/done.jpg', ImageCopyright::embed($this->jpeg()));
        $make('img/beaches/b1/gone.jpg');
        $make('img/beaches_mobile/b1/user.jpg');
        Storage::disk('spaces')->put('public/img/beaches_mobile/b1/user.jpg', $this->jpeg());

        $this->artisan('media:embed-copyright', ['--dry-run' => true])
            ->expectsOutputToContain('Checked: 3 · would write: 1 · already had it: 1 · missing on Spaces: 1')
            ->assertSuccessful();
        $this->assertFalse(ImageCopyright::hasNotice(Storage::disk('spaces')->get('public/img/beaches/b1/new.jpg')));

        $this->artisan('media:embed-copyright')->assertSuccessful();
        $this->assertTrue(ImageCopyright::hasNotice(Storage::disk('spaces')->get('public/img/beaches/b1/new.jpg')));
        // Mobile uploads may be other authors' photos: untouched
        $this->assertFalse(ImageCopyright::hasNotice(Storage::disk('spaces')->get('public/img/beaches_mobile/b1/user.jpg')));

        $this->artisan('media:embed-copyright')
            ->expectsOutputToContain('written: 0 · already had it: 2')
            ->assertSuccessful();
    }
}
