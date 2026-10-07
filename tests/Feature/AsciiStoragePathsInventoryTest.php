<?php

namespace Tests\Feature;

use App\Models\Beach;
use App\Models\Media;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Normalizer;
use Tests\TestCase;

class AsciiStoragePathsInventoryTest extends TestCase
{
    use RefreshDatabase;

    private string $csv;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            \Database\Seeders\StatusSeeder::class,
            \Database\Seeders\MediaTypeSeeder::class,
            \Database\Seeders\MediaCollectionSeeder::class,
        ]);
        Storage::fake('spaces');
        $this->csv = sys_get_temp_dir().'/ascii-inventory-'.uniqid().'.csv';
    }

    protected function tearDown(): void
    {
        @unlink($this->csv);
        parent::tearDown();
    }

    private function nfd(string $value): string
    {
        return Normalizer::normalize($value, Normalizer::FORM_D);
    }

    /** Legacy rows were written before TASK-002, so they bypass the model's ASCII guard. */
    private function legacy(string $table, int $id, array $values): void
    {
        DB::table($table)->where('id', $id)->update($values);
    }

    /** @return list<array<string, string>> */
    private function rows(): array
    {
        $lines = array_map('str_getcsv', file($this->csv, FILE_IGNORE_NEW_LINES));
        $header = array_shift($lines);

        return array_map(fn ($line) => array_combine($header, $line), $lines);
    }

    private function runInventory(): void
    {
        $this->artisan('storage:ascii-paths', ['--inventory' => true, '--output' => $this->csv])
            ->expectsOutputToContain('Read-only')
            ->assertSuccessful();
    }

    public function test_finds_nfc_and_nfd_keys_and_maps_them_to_ascii(): void
    {
        $spaces = Storage::disk('spaces');
        $spaces->put($this->nfd('public/img/beaches/hr-114-o-brač/hr-114-o-brač-sl17.jpg'), 'a');
        $spaces->put('public/img/beaches/hr-1-o-šolta/x.jpg', 'b');
        $spaces->put('public/img/beaches/hr-69-o-pag/ok.jpg', 'c');
        $spaces->put('public/other/ž.jpg', 'outside the prefix');

        $this->runInventory();

        $spacesRows = array_values(array_filter($this->rows(), fn ($r) => $r['source'] === 'spaces'));
        $this->assertCount(2, $spacesRows);

        $byNew = array_column($spacesRows, null, 'new');
        $brac = $byNew['public/img/beaches/hr-114-o-brac/hr-114-o-brac-sl17.jpg'];
        $this->assertSame('NFD', $brac['form']);
        $this->assertSame($this->nfd('public/img/beaches/hr-114-o-brač/hr-114-o-brač-sl17.jpg'), $brac['old']);
        $this->assertSame('', $brac['issue']);
        $this->assertSame('NFC', $byNew['public/img/beaches/hr-1-o-solta/x.jpg']['form']);
    }

    public function test_reports_collisions(): void
    {
        $spaces = Storage::disk('spaces');
        // NFC and NFD variants of the same name → same new key
        $spaces->put('public/img/beaches/a/brač.jpg', '1');
        $spaces->put($this->nfd('public/img/beaches/a/brač.jpg'), '2');
        // new key already exists in the bucket
        $spaces->put('public/img/beaches/b/žal.jpg', '3');
        $spaces->put('public/img/beaches/b/zal.jpg', '4');

        $this->runInventory();

        $collisions = array_filter($this->rows(), fn ($r) => $r['issue'] === 'collision');
        $this->assertCount(3, $collisions);
        $this->assertSame(['public/img/beaches/a/brac.jpg', 'public/img/beaches/b/zal.jpg'],
            array_values(array_unique(array_column($collisions, 'new'))));
    }

    public function test_finds_non_ascii_references_in_vtour_text_files(): void
    {
        $spaces = Storage::disk('spaces');
        $spaces->put('public/img/beaches/hr-9/vtour/tour.xml', '<image url="panos/brač_1.tiles/pano.jpg" />');
        $spaces->put('public/img/beaches/hr-9/vtour/tour.html', '<html>ascii only</html>');
        $spaces->put('public/img/beaches/hr-9/vtour/panos/x.jpg', 'binary ž is not scanned');

        $this->runInventory();

        $vtour = array_values(array_filter($this->rows(), fn ($r) => $r['source'] === 'vtour_ref'));
        $this->assertCount(1, $vtour);
        $this->assertSame('public/img/beaches/hr-9/vtour/tour.xml', $vtour[0]['location']);
        $this->assertStringContainsString('panos/brač_1.tiles/pano.jpg', $vtour[0]['detail']);
    }

    public function test_finds_non_ascii_db_paths(): void
    {
        $beach = Beach::factory()->create();
        $this->legacy('beach', $beach->id, ['folder_name' => 'hr-114-o-brač']);
        $ascii = Beach::factory()->create(['folder_name' => 'hr-69-o-pag']);
        $media = Media::factory()->create(['mediable_id' => $beach->id]);
        $this->legacy('media', $media->id, [
            'file_name' => $this->nfd('hr-114-o-brač-sl17.jpg'),
            'file_path' => 'img/beaches/hr-114-o-brač/hr-114-o-brač-sl17.jpg',
        ]);

        $this->runInventory();

        $db = array_column(array_filter($this->rows(), fn ($r) => $r['source'] === 'db'), null, 'location');
        $this->assertSame('hr-114-o-brac', $db["beach.folder_name#{$beach->id}"]['new']);
        $this->assertArrayNotHasKey("beach.folder_name#{$ascii->id}", $db);
        $this->assertSame('img/beaches/hr-114-o-brac/hr-114-o-brac-sl17.jpg', $db["media.file_path#{$media->id}"]['new']);
        $this->assertSame('NFD', $db["media.file_name#{$media->id}"]['form']);
        $this->assertSame('hr-114-o-brac-sl17.jpg', $db["media.file_name#{$media->id}"]['new']);
    }

    public function test_is_read_only(): void
    {
        $spaces = Storage::disk('spaces');
        $key = 'public/img/beaches/hr-114-o-brač/x.jpg';
        $spaces->put($key, 'original');
        $beach = Beach::factory()->create();
        $this->legacy('beach', $beach->id, ['folder_name' => 'hr-114-o-brač']);

        $this->runInventory();

        $this->assertSame([$key], $spaces->allFiles('public'));
        $this->assertSame('original', $spaces->get($key));
        $this->assertSame('hr-114-o-brač', $beach->fresh()->folder_name);
    }

    public function test_requires_a_step(): void
    {
        $this->artisan('storage:ascii-paths')->assertFailed();
    }
}
