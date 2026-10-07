<?php

namespace Tests\Feature;

use App\Models\Beach;
use App\Models\City;
use App\Models\Continent;
use App\Models\Country;
use App\Models\Island;
use App\Models\Media;
use App\Models\Region;
use App\Models\Traits\HasAsciiPaths;
use App\Support\AsciiPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Normalizer;
use Symfony\Component\Finder\Finder;
use Tests\TestCase;

/** TASK-002 §4: no new non-ASCII storage paths. */
class AsciiPathPreventionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([
            \Database\Seeders\StatusSeeder::class,
            \Database\Seeders\MediaTypeSeeder::class,
            \Database\Seeders\MediaCollectionSeeder::class,
        ]);
    }

    public function test_new_beach_folder_name_is_stored_ascii(): void
    {
        $beach = Beach::factory()->create(['folder_name' => 'hr-114-o-brač']);

        $this->assertSame('hr-114-o-brac', $beach->fresh()->folder_name);
    }

    public function test_nfd_folder_name_is_stored_ascii(): void
    {
        $beach = Beach::factory()->create(['folder_name' => Normalizer::normalize('hr-1-o-šolta', Normalizer::FORM_D)]);

        $this->assertSame('hr-1-o-solta', $beach->fresh()->folder_name);
    }

    public function test_changed_folder_name_is_stored_ascii(): void
    {
        $beach = Beach::factory()->create(['folder_name' => 'hr-69-o-pag']);
        $beach->update(['folder_name' => 'hr-70-o-žirje']);

        $this->assertSame('hr-70-o-zirje', $beach->fresh()->folder_name);
    }

    public function test_unchanged_legacy_value_is_left_alone_until_the_migration(): void
    {
        $beach = Beach::factory()->create(['folder_name' => 'hr-114-o-brac']);
        // A legacy row written before TASK-002 (bypasses the model, like the old import).
        DB::table('beach')->where('id', $beach->id)->update(['folder_name' => 'hr-114-o-brač']);

        $legacy = Beach::find($beach->id);
        $legacy->update(['mobile_upload' => ! $legacy->mobile_upload]);

        // Rewriting it here would point the beach at keys that don't exist yet.
        $this->assertSame('hr-114-o-brač', $legacy->fresh()->folder_name);
    }

    public function test_media_paths_are_stored_ascii(): void
    {
        $beach = Beach::factory()->create(['folder_name' => 'hr-114-o-brac']);
        $media = Media::factory()->create([
            'mediable_id' => $beach->id,
            'file_name' => 'Plaža Čiovo.jpg',
            'file_path' => 'img/beaches/hr-114-o-brač/Plaža Čiovo.jpg',
        ])->fresh();

        $this->assertSame('Plaza-Ciovo.jpg', $media->file_name);
        $this->assertSame('img/beaches/hr-114-o-brac/Plaza-Ciovo.jpg', $media->file_path);
    }

    public function test_every_model_with_a_storage_path_uses_the_trait(): void
    {
        $expected = [
            Beach::class => ['folder_name'],
            Media::class => ['file_path', 'file_name'],
            City::class => ['main_image'],
            Region::class => ['main_image'],
            Island::class => ['main_image'],
            Continent::class => ['main_image'],
            Country::class => ['image_path'],
        ];

        foreach ($expected as $class => $attributes) {
            $this->assertContains(HasAsciiPaths::class, class_uses_recursive($class), $class);
            $property = (new \ReflectionClass($class))->getProperty('asciiPathAttributes');
            $this->assertSame($attributes, $property->getValue(new $class), $class);
        }
    }

    public function test_upload_file_name_is_ascii(): void
    {
        foreach (['Plaža Čiovo.jpg' => 'jpg', 'IMG_1.JPEG' => 'jpeg', 'x.pnž' => 'pnz'] as $original => $extension) {
            $name = AsciiPath::uploadFileName($original);
            $this->assertMatchesRegularExpression('/^[0-9A-Z]{26}\.'.$extension.'$/', $name, $original);
        }
        $this->assertMatchesRegularExpression('/^[0-9A-Z]{26}$/', AsciiPath::uploadFileName('no-extension'));
        $this->assertNotSame(AsciiPath::uploadFileName('a.jpg'), AsciiPath::uploadFileName('a.jpg'));
    }

    public function test_every_filament_file_upload_uses_ascii_file_names(): void
    {
        $files = Finder::create()->files()->in(app_path('Filament'))->name('*.php')->contains('FileUpload::make(');
        $this->assertGreaterThan(0, count($files));

        foreach ($files as $file) {
            $source = $file->getContents();
            $this->assertSame(
                substr_count($source, 'FileUpload::make('),
                substr_count($source, 'AsciiPath::uploadFileName('),
                $file->getRelativePathname().': every FileUpload needs ->getUploadedFileNameForStorageUsing(… AsciiPath::uploadFileName …)'
            );
        }
    }

    public function test_seeders_and_factories_contain_no_non_ascii_paths(): void
    {
        $pathLine = '/(folder_name|file_path|file_name|main_image|image_path|img\/)/';
        $offending = [];
        foreach (Finder::create()->files()->in([database_path('seeders'), database_path('factories')])->name('*.php') as $file) {
            foreach (explode("\n", $file->getContents()) as $number => $line) {
                if (preg_match($pathLine, $line) && ! AsciiPath::isAscii($line)) {
                    $offending[] = $file->getRelativePathname().':'.($number + 1);
                }
            }
        }
        $this->assertSame([], $offending);

        $media = Media::factory()->create()->fresh();
        $this->assertTrue(AsciiPath::isAscii($media->file_path.$media->file_name));
    }
}
