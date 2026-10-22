<?php

namespace Tests\Feature;

use App\Models\Beach;
use App\Models\BeachTranslation;
use App\Models\Lake;
use App\Models\LakeTranslation;
use App\Models\Waterbody;
use Database\Seeders\StatusSeeder;
use Database\Seeders\WaterbodySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Regression: lake beaches from the old database were imported with the sea waterbody
 * (old DB had waterbody_id = 3 on every beach; lakes were marked only by location_id = 3 + lake_name).
 * `beaches:fix-lake-waterbody` corrects the existing rows without a re-import.
 */
class FixLakeBeachWaterbodyTest extends TestCase
{
    use RefreshDatabase;

    private int $sea;

    private int $lake;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([StatusSeeder::class, WaterbodySeeder::class]);
        $this->sea = Waterbody::where('code', 'sea')->value('id');
        $this->lake = Waterbody::where('code', 'lake')->value('id');
    }

    private function beach(int $id, string $name, ?int $lakeId = null): Beach
    {
        $beach = Beach::factory()->create(['id' => $id, 'status_id' => 1, 'waterbody_id' => $this->sea, 'lake_id' => $lakeId]);
        BeachTranslation::create(['beach_id' => $id, 'locale' => 'en', 'name' => $name, 'slug' => Str::slug($name)]);

        return $beach;
    }

    private function lakeNamed(string $name, string $code): Lake
    {
        $lake = Lake::create(['code' => $code, 'status_id' => 1]);
        LakeTranslation::create(['lake_id' => $lake->id, 'locale' => 'en', 'name' => $name, 'slug' => $code]);

        return $lake;
    }

    private function listFile(array $beaches): string
    {
        $path = tempnam(sys_get_temp_dir(), 'lakes');
        file_put_contents($path, json_encode(['beaches' => $beaches]));

        return $path;
    }

    private function sampleList(): string
    {
        return $this->listFile([
            ['beach_id' => 4023, 'name' => 'Strand Schildmeer', 'lake_name' => 'Schildmeer', 'lake_slug' => 'schildmeer'],
            ['beach_id' => 4166, 'name' => 'Strand Nieuwdonk', 'lake_name' => 'Nieuwdonk', 'lake_slug' => 'nieuwdonk'],
            ['beach_id' => 9999, 'name' => 'Not in DB', 'lake_name' => 'Nowhere', 'lake_slug' => 'nowhere'],
        ]);
    }

    public function test_dry_run_writes_nothing(): void
    {
        $this->beach(4023, 'Strand Schildmeer');
        $this->beach(4166, 'Strand Nieuwdonk');

        $this->artisan('beaches:fix-lake-waterbody', ['--file' => $this->sampleList()])
            ->expectsOutputToContain('DRY RUN COMPLETE')
            ->assertSuccessful();

        $this->assertSame(2, Beach::where('waterbody_id', $this->sea)->count());
        $this->assertSame(0, Beach::whereNotNull('lake_id')->count());
        $this->assertSame(0, Lake::count());
    }

    public function test_apply_sets_lake_waterbody_links_lakes_and_skips_missing_beaches(): void
    {
        $existing = $this->lakeNamed('Schildmeer', 'schildmeer');
        $this->beach(4023, 'Strand Schildmeer');
        $this->beach(4166, 'Strand Nieuwdonk');
        $untouched = $this->beach(100, 'Sea beach');

        $this->artisan('beaches:fix-lake-waterbody', ['--file' => $this->sampleList(), '--apply' => true])
            ->expectsOutputToContain('COMPLETE - lake beaches updated')
            ->assertSuccessful();

        $schildmeer = Beach::find(4023);
        $this->assertSame($this->lake, (int) $schildmeer->waterbody_id);
        $this->assertSame($existing->id, (int) $schildmeer->lake_id, 'reuses the existing lake by English name');

        $nieuwdonk = Beach::find(4166);
        $this->assertSame($this->lake, (int) $nieuwdonk->waterbody_id);
        $this->assertSame('Nieuwdonk', LakeTranslation::where('lake_id', $nieuwdonk->lake_id)->where('locale', 'en')->value('name'));

        $this->assertSame($this->sea, (int) $untouched->fresh()->waterbody_id, 'beaches not in the list stay unchanged');
        $this->assertNull(Beach::find(9999));
        $this->assertSame(2, Lake::count());
    }

    public function test_second_run_changes_nothing(): void
    {
        $this->beach(4023, 'Strand Schildmeer');
        $this->beach(4166, 'Strand Nieuwdonk');
        $file = $this->sampleList();

        $this->artisan('beaches:fix-lake-waterbody', ['--file' => $file, '--apply' => true])->assertSuccessful();
        $lakesAfterFirst = Lake::count();

        $this->artisan('beaches:fix-lake-waterbody', ['--file' => $file, '--apply' => true])
            ->expectsOutputToContain('Already correct:          2')
            ->assertSuccessful();

        $this->assertSame($lakesAfterFirst, Lake::count());
    }

    public function test_existing_lake_link_with_another_name_is_reported_not_changed(): void
    {
        $other = $this->lakeNamed('Gravelingenmeer', 'gravelingenmeer');
        $this->beach(4023, 'Strand Schildmeer', $other->id);

        $this->artisan('beaches:fix-lake-waterbody', ['--file' => $this->listFile([
            ['beach_id' => 4023, 'name' => 'Strand Schildmeer', 'lake_name' => 'Schildmeer', 'lake_slug' => 'schildmeer'],
        ]), '--apply' => true])
            ->expectsOutputToContain('Lake name differs:        1')
            ->assertSuccessful();

        $beach = Beach::find(4023);
        $this->assertSame($other->id, (int) $beach->lake_id);
        $this->assertSame($this->lake, (int) $beach->waterbody_id);
    }

    public function test_missing_file_fails(): void
    {
        $this->artisan('beaches:fix-lake-waterbody', ['--file' => '/nonexistent/lakes.json'])->assertFailed();
    }

    public function test_bundled_list_holds_the_42_lake_beaches_from_the_old_dump(): void
    {
        $data = json_decode(file_get_contents(database_path('legacy/lake-beaches.json')), true);
        $ids = array_column($data['beaches'], 'beach_id');

        $this->assertCount(42, $ids);
        $this->assertSame($ids, array_values(array_unique($ids)));
        foreach ($data['beaches'] as $row) {
            $this->assertNotSame('', trim($row['lake_name']));
        }
        // The two location_id = 3 rows without a lake name are sea beaches and must stay out.
        $this->assertNotContains(2546, $ids);
        $this->assertNotContains(2649, $ids);
    }
}
