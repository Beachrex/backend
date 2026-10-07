<?php

namespace Tests\Feature;

use App\Models\Beach;
use App\Models\Media;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Normalizer;
use Tests\TestCase;

/** TASK-002 §3: --copy, --update-db and --delete-old. */
class AsciiStoragePathsMigrationTest extends TestCase
{
    use RefreshDatabase;

    private const OLD = 'public/img/beaches/hr-114-o-brač/hr-114-o-brač-sl17.jpg';
    private const NEW = 'public/img/beaches/hr-114-o-brac/hr-114-o-brac-sl17.jpg';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([
            \Database\Seeders\StatusSeeder::class,
            \Database\Seeders\MediaTypeSeeder::class,
            \Database\Seeders\MediaCollectionSeeder::class,
        ]);
        Storage::fake('spaces');
    }

    private function nfd(string $value): string
    {
        return Normalizer::normalize($value, Normalizer::FORM_D);
    }

    private function step(string $step, array $options = []): \Illuminate\Testing\PendingCommand
    {
        return $this->artisan('storage:ascii-paths', ['--'.$step => true] + $options);
    }

    /** A beach with legacy (pre-TASK-002) non-ASCII paths, written past the model guard. */
    private function legacyBeach(): array
    {
        $beach = Beach::factory()->create();
        DB::table('beach')->where('id', $beach->id)->update(['folder_name' => 'hr-114-o-brač']);
        $media = Media::factory()->create(['mediable_id' => $beach->id]);
        DB::table('media')->where('id', $media->id)->update([
            'file_name' => $this->nfd('hr-114-o-brač-sl17.jpg'),
            'file_path' => 'img/beaches/hr-114-o-brač/hr-114-o-brač-sl17.jpg',
        ]);

        return [$beach, $media];
    }

    // ---------------------------------------------------------------- --copy

    public function test_copy_dry_run_changes_nothing(): void
    {
        Storage::disk('spaces')->put($this->nfd(self::OLD), 'jpeg');

        $this->step('copy', ['--dry-run' => true])->expectsOutputToContain('[dry-run] would copy 1 key(s)')->assertSuccessful();

        $this->assertSame([$this->nfd(self::OLD)], Storage::disk('spaces')->allFiles('public'));
    }

    public function test_copy_copies_nfc_and_nfd_keys_keeps_old_and_is_idempotent(): void
    {
        $spaces = Storage::disk('spaces');
        $spaces->put($this->nfd(self::OLD), 'jpeg-1');
        $spaces->put('public/img/beaches/hr-1-o-šolta/x.jpg', 'jpeg-2');
        $spaces->put('public/img/beaches/hr-69-o-pag/ok.jpg', 'jpeg-3');

        $this->step('copy')->expectsOutputToContain('Copied 2 key(s)')->assertSuccessful();

        $this->assertSame('jpeg-1', $spaces->get(self::NEW));
        $this->assertSame('jpeg-2', $spaces->get('public/img/beaches/hr-1-o-solta/x.jpg'));
        $this->assertTrue($spaces->exists($this->nfd(self::OLD)), 'old key is kept');
        $this->assertSame('public', $spaces->getVisibility(self::NEW));

        $this->step('copy')->expectsOutputToContain('Copied 0 key(s)')->assertSuccessful();
    }

    public function test_copy_aborts_on_collision_and_copies_nothing(): void
    {
        $spaces = Storage::disk('spaces');
        $spaces->put('public/img/beaches/a/brač.jpg', '1');
        $spaces->put($this->nfd('public/img/beaches/a/brač.jpg'), '2');
        $spaces->put('public/img/beaches/b/šip.jpg', 'x');

        $this->step('copy')->expectsOutputToContain('COLLISION')->assertFailed();

        $this->assertFalse($spaces->exists('public/img/beaches/a/brac.jpg'));
        $this->assertFalse($spaces->exists('public/img/beaches/b/sip.jpg'));
    }

    public function test_a_different_file_at_the_target_is_a_collision_even_with_the_same_size(): void
    {
        $spaces = Storage::disk('spaces');
        $spaces->put('public/img/beaches/b/žal.jpg', 'AAAA');
        $spaces->put('public/img/beaches/b/zal.jpg', 'BBBB');

        $this->step('copy')->expectsOutputToContain('a different file already exists')->assertFailed();

        $this->assertSame('BBBB', $spaces->get('public/img/beaches/b/zal.jpg'));
    }

    public function test_copy_rewrites_vtour_references_but_not_other_text(): void
    {
        $spaces = Storage::disk('spaces');
        // Tour in a non-ASCII folder: rewritten at its ASCII copy, the old file stays as is.
        $oldTour = 'public/img/beaches/hr-114-o-brač/vtour/tour.xml';
        $xml = '<krpano title="Plaža Brač"><image url="panos/brač_1.tiles/l1.jpg" /></krpano>';
        $spaces->put($oldTour, $xml);
        $spaces->put('public/img/beaches/hr-114-o-brač/vtour/panos/brač_1.tiles/l1.jpg', 'tile');
        // Tour in an ASCII folder that references a non-ASCII (NFD) pano name: rewritten in place.
        $spaces->put('public/img/beaches/hr-5/vtour/tour.xml', '<image url="'.$this->nfd('panos/čiovo.tiles/l1.jpg').'" />');
        $spaces->put($this->nfd('public/img/beaches/hr-5/vtour/panos/čiovo.tiles/l1.jpg'), 'tile');

        $this->step('copy')->assertSuccessful();

        $this->assertSame(
            '<krpano title="Plaža Brač"><image url="panos/brac_1.tiles/l1.jpg" /></krpano>',
            $spaces->get('public/img/beaches/hr-114-o-brac/vtour/tour.xml')
        );
        $this->assertSame($xml, $spaces->get($oldTour));
        $this->assertSame('<image url="panos/ciovo.tiles/l1.jpg" />', $spaces->get('public/img/beaches/hr-5/vtour/tour.xml'));
        $this->assertTrue($spaces->exists('public/img/beaches/hr-5/vtour/panos/ciovo.tiles/l1.jpg'));

        // Second run: nothing left to copy, and the rewritten tours are not counted as collisions.
        $this->step('copy')->expectsOutputToContain('Copied 0 key(s)')->assertSuccessful();
    }

    // ----------------------------------------------------------- --update-db

    public function test_update_db_refuses_before_copy(): void
    {
        [$beach] = $this->legacyBeach();
        Storage::disk('spaces')->put($this->nfd(self::OLD), 'jpeg');

        $this->step('update-db')->expectsOutputToContain('Run --copy first')->assertFailed();

        $this->assertSame('hr-114-o-brač', DB::table('beach')->where('id', $beach->id)->value('folder_name'));
    }

    public function test_update_db_rewrites_only_matching_rows_and_is_idempotent(): void
    {
        [$beach, $media] = $this->legacyBeach();
        $ascii = Beach::factory()->create(['folder_name' => 'hr-69-o-pag']);
        $url = 'https://example.com/plaža.jpg';
        DB::table('beach')->where('id', $ascii->id)->update(['path_to_beach_map_url' => $url]);
        Storage::disk('spaces')->put($this->nfd(self::OLD), 'jpeg');
        $this->step('copy')->assertSuccessful();

        $this->step('update-db', ['--dry-run' => true])->expectsOutputToContain('[dry-run] would update 3 DB value(s)')->assertSuccessful();
        $this->assertSame('hr-114-o-brač', DB::table('beach')->where('id', $beach->id)->value('folder_name'));

        $this->step('update-db')->expectsOutputToContain('Updated 3 DB value(s)')->assertSuccessful();

        $this->assertSame('hr-114-o-brac', DB::table('beach')->where('id', $beach->id)->value('folder_name'));
        $row = DB::table('media')->where('id', $media->id)->first();
        $this->assertSame('img/beaches/hr-114-o-brac/hr-114-o-brac-sl17.jpg', $row->file_path);
        $this->assertSame('hr-114-o-brac-sl17.jpg', $row->file_name);
        $this->assertSame('hr-69-o-pag', DB::table('beach')->where('id', $ascii->id)->value('folder_name'));
        $this->assertSame($url, DB::table('beach')->where('id', $ascii->id)->value('path_to_beach_map_url'), 'URLs are left for a manual fix');

        $this->step('update-db')->expectsOutputToContain('Updated 0 DB value(s)')->assertSuccessful();
    }

    public function test_update_db_rewrites_strings_inside_json_columns(): void
    {
        Schema::create('ascii_json_test', function (Blueprint $table) {
            $table->id();
            $table->json('image_data');
        });
        $id = DB::table('ascii_json_test')->insertGetId(['image_data' => json_encode([
            'hero' => 'img/home/plaža.jpg', 'alt' => 'ascii', 'list' => ['img/home/čiovo.jpg'],
        ])]);

        $this->step('update-db')->assertSuccessful();

        $this->assertSame(
            ['hero' => 'img/home/plaza.jpg', 'alt' => 'ascii', 'list' => ['img/home/ciovo.jpg']],
            json_decode(DB::table('ascii_json_test')->where('id', $id)->value('image_data'), true)
        );
    }

    // ---------------------------------------------------------- --delete-old

    public function test_delete_old_requires_confirmation(): void
    {
        Storage::disk('spaces')->put(self::OLD, 'jpeg');

        $this->step('delete-old')->expectsOutputToContain('--confirm-delete')->assertFailed();

        $this->assertTrue(Storage::disk('spaces')->exists(self::OLD));
    }

    public function test_delete_old_refuses_while_this_env_db_still_has_old_paths(): void
    {
        $this->legacyBeach();
        Storage::disk('spaces')->put($this->nfd(self::OLD), 'jpeg');
        $this->step('copy')->assertSuccessful();

        $this->step('delete-old', ['--confirm-delete' => true])->expectsOutputToContain('Run --update-db first')->assertFailed();

        $this->assertTrue(Storage::disk('spaces')->exists($this->nfd(self::OLD)));
    }

    public function test_full_flow_ends_with_an_ascii_only_bucket_and_db(): void
    {
        $this->legacyBeach();
        $spaces = Storage::disk('spaces');
        $spaces->put($this->nfd(self::OLD), 'jpeg');
        $spaces->put('public/img/beaches/hr-69-o-pag/ok.jpg', 'ok');

        $this->step('delete-old', ['--confirm-delete' => true])->expectsOutputToContain('Run --copy first')->assertFailed();
        $this->step('copy')->assertSuccessful();
        $this->step('update-db')->assertSuccessful();
        $this->step('delete-old', ['--dry-run' => true])->expectsOutputToContain('[dry-run] would delete 1')->assertSuccessful();
        $this->assertTrue($spaces->exists($this->nfd(self::OLD)));

        $this->step('delete-old', ['--confirm-delete' => true])->expectsOutputToContain('Deleted 1 old key(s)')->assertSuccessful();

        $this->assertSame(['public/img/beaches/hr-114-o-brac/hr-114-o-brac-sl17.jpg', 'public/img/beaches/hr-69-o-pag/ok.jpg'],
            $spaces->allFiles('public'));
        $csv = sys_get_temp_dir().'/ascii-final-'.uniqid().'.csv';
        $this->step('inventory', ['--output' => $csv])->assertSuccessful();
        $this->assertCount(1, file($csv), 'only the CSV header: nothing non-ASCII left');
        unlink($csv);
    }

    public function test_only_one_step_at_a_time(): void
    {
        $this->artisan('storage:ascii-paths', ['--copy' => true, '--update-db' => true])->assertFailed();
    }
}
