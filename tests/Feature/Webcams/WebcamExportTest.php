<?php

namespace Tests\Feature\Webcams;

use App\Models\PlaceWebcam;
use App\Models\WindyWebcam;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** webcams:export: CSV of places with webcams and their website links (read-only). */
class WebcamExportTest extends TestCase
{
    use RefreshDatabase;

    private function csv(): string
    {
        return Storage::disk('local')->get(Storage::disk('local')->files('webcams')[0]);
    }

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        DB::table('status')->insertOrIgnore([['id' => 1, 'name' => 'active']]);
        DB::table('continents')->insert(['code' => 'EU']);
        DB::table('continent_translation')->insert(['continent_code' => 'EU', 'locale' => 'en', 'name' => 'Europe', 'slug' => 'europe']);
        DB::table('countries')->insert(['code' => 'HR', 'continent_code' => 'EU']);
        DB::table('country_translation')->insert(['country_code' => 'HR', 'locale' => 'en', 'name' => 'Croatia', 'slug' => 'croatia']);
        $b = DB::table('beach')->insertGetId(['name' => 'Nikolina', 'country_code' => 'HR', 'continent_code' => 'EU', 'status_id' => 1, 'latitude' => 43.35, 'longitude' => 16.95]);
        DB::table('beach_translation')->insert(['beach_id' => $b, 'locale' => 'en', 'name' => 'Beach Nikolina', 'slug' => 'beach-nikolina']);
        foreach ([1461097621 => 'ok', 1000000002 => 'ok', 1000000003 => 'not_public'] as $id => $embed) {
            WindyWebcam::create(['windy_id' => $id, 'title' => "Cam {$id}", 'lat' => 43.35, 'lon' => 16.95, 'categories' => ['beach'], 'status' => 'active', 'embed_status' => $embed]);
        }
        PlaceWebcam::create(['place_id' => $b, 'windy_id' => 1461097621, 'source' => 'matcher', 'review_status' => 'approved', 'is_primary' => true, 'match_score' => 95, 'distance_m' => 64]);
        PlaceWebcam::create(['place_id' => $b, 'windy_id' => 1000000002, 'source' => 'matcher', 'review_status' => 'pending', 'match_score' => 50]);
        PlaceWebcam::create(['place_id' => $b, 'windy_id' => 1000000003, 'source' => 'legacy', 'review_status' => 'approved', 'match_score' => 70]);
    }

    #[Test]
    public function exports_shown_webcams_with_website_and_windy_links(): void
    {
        $this->artisan('webcams:export', ['--base-url' => 'https://dev.beachrex.com'])->expectsOutputToContain('Rows: 1')->assertSuccessful();

        $csv = $this->csv();
        $this->assertStringContainsString('"place_id","country","beach","beach_url"', $csv);
        $this->assertStringContainsString('"Beach Nikolina","https://dev.beachrex.com/en/beaches/europe/croatia/beach-nikolina","1461097621"', $csv);
        $this->assertStringContainsString('"https://www.windy.com/webcams/1461097621","approved","yes"', $csv);
        $this->assertStringNotContainsString('1000000002', $csv);   // pending
        $this->assertStringNotContainsString('1000000003', $csv);   // player not public
    }

    #[Test]
    public function all_option_lists_every_assignment_and_writes_nothing_else(): void
    {
        $before = DB::table('place_webcams')->orderBy('id')->get();
        $this->artisan('webcams:export', ['--all' => true])->expectsOutputToContain('Rows: 3')->assertSuccessful();

        $this->assertStringContainsString('"1000000002"', $this->csv());
        $this->assertEquals($before, DB::table('place_webcams')->orderBy('id')->get());
    }
}
