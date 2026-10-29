<?php

namespace Tests\Feature\Webcams;

use App\Models\PlaceWebcam;
use App\Models\WindyWebcam;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** TASK-006 §6.3: local matching on real PostGIS — radius, uniqueness, reviewed rows, rejected pairs. */
class WebcamMatcherTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        DB::table('status')->insertOrIgnore([['id' => 1, 'name' => 'active']]);
        DB::table('countries')->insertOrIgnore(['code' => 'HR']);
    }

    private function place(string $name, float $lat, float $lon): int
    {
        $id = DB::table('beach')->insertGetId(['name' => $name, 'country_code' => 'HR', 'status_id' => 1, 'latitude' => $lat, 'longitude' => $lon]);
        DB::table('beach_translation')->insert(['beach_id' => $id, 'locale' => 'en', 'name' => $name, 'slug' => 'b-' . $id]);

        return $id;
    }

    private function cam(int $id, string $title, float $lat, float $lon, array $cats = ['beach'], array $extra = []): void
    {
        WindyWebcam::create(array_merge(['windy_id' => $id, 'title' => $title, 'lat' => $lat, 'lon' => $lon, 'country_code' => 'HR',
            'categories' => $cats, 'status' => 'active', 'embed_status' => 'ok'], $extra));
    }

    /** Coordinates in the tests: 0.001° latitude ≈ 111 m. */
    private function match(array $args = []): void
    {
        $this->artisan('webcams:match', $args)->assertSuccessful();
    }

    #[Test]
    public function auto_approves_a_clear_match_and_makes_it_primary(): void
    {
        $p = $this->place('Beach Nikolina', 43.3541, 16.9486);
        $this->cam(1461097621, 'Baska Voda: Plaža Nikolina', 43.3550, 16.9486);  // ~100 m, beach, name → 95
        $this->cam(1000000002, 'Baska Voda: Port', 43.3700, 16.9486, ['port']);  // ~1.8 km, port → 20

        $this->match();

        $row = PlaceWebcam::sole();
        $this->assertSame([$p, 1461097621, 'auto_approved', true, 'matcher', 95],
            [$row->place_id, $row->windy_id, $row->review_status, $row->is_primary, $row->source, $row->match_score]);
        $this->assertSame(20, $row->score_breakdown['runner_up']);
    }

    #[Test]
    public function close_scores_go_to_review_and_low_scores_get_nothing(): void
    {
        $a = $this->place('Beach Alpha', 45.0, 14.0);
        $this->cam(1000000001, 'Town: Alpha beach', 45.0005, 14.0);             // 95
        $this->cam(1000000002, 'Town: Alpha bay view', 45.0008, 14.0);          // 95 (bay is a stopword)
        $b = $this->place('Beach Bravo', 46.0, 14.0);
        $this->cam(1000000003, 'Somewhere: Traffic', 46.0200, 14.0, ['traffic']); // ~2.2 km, 10 − 20 → none

        $this->match();

        $this->assertSame('pending', PlaceWebcam::where('place_id', $a)->sole()->review_status);
        $this->assertFalse(PlaceWebcam::where('place_id', $a)->sole()->is_primary);
        $this->assertSame(0, PlaceWebcam::where('place_id', $b)->count());
    }

    #[Test]
    public function ignores_webcams_beyond_the_radius_and_unusable_ones(): void
    {
        $p = $this->place('Beach Far', 45.0, 14.0);
        $this->cam(1000000001, 'Beach Far', 45.0280, 14.0);                          // ~3.1 km
        $this->cam(1000000002, 'Beach Far', 45.001, 14.0, ['beach'], ['embed_status' => 'not_public']);
        $this->cam(1000000003, 'Beach Far', 45.001, 14.0, ['beach'], ['status' => 'inactive']);
        $this->cam(1000000004, 'Beach Far', 45.001, 14.0, ['beach'], ['missing_since' => now()]);

        $this->match();

        $this->assertSame(0, PlaceWebcam::where('place_id', $p)->count());
    }

    #[Test]
    public function one_webcam_goes_to_the_higher_score_and_the_other_place_falls_back(): void
    {
        $a = $this->place('Beach Nikolina', 43.0, 16.0);
        $b = $this->place('Beach Other', 43.002, 16.0);
        $this->cam(1000000001, 'Town: Nikolina beach', 43.001, 16.0);           // A: 111 m + name = 95; B: 111 m = 65
        $this->cam(1000000002, 'Town: sea', 43.0045, 16.0, ['landscape']);     // B: 278 m = 40; A: 500 m = 25

        $this->match();

        $this->assertSame([1000000001, 'auto_approved'], [PlaceWebcam::where('place_id', $a)->sole()->windy_id, PlaceWebcam::where('place_id', $a)->sole()->review_status]);
        $this->assertSame([1000000002, 'pending'], [PlaceWebcam::where('place_id', $b)->sole()->windy_id, PlaceWebcam::where('place_id', $b)->sole()->review_status]);
        $this->assertSame(1, PlaceWebcam::where('windy_id', 1000000001)->count());
    }

    #[Test]
    public function contested_webcam_with_equal_scores_goes_to_the_nearer_place(): void
    {
        $far = $this->place('Beach One', 43.0, 16.0);
        $near = $this->place('Beach Two', 43.0035, 16.0);
        $this->cam(1000000001, 'Town: sea', 43.0025, 16.0);  // 278 m from One… 111 m from Two; both band 40 + beach 25 = 65

        $this->match();

        $this->assertSame($near, PlaceWebcam::sole()->place_id);
        $this->assertSame(0, PlaceWebcam::where('place_id', $far)->count());
    }

    #[Test]
    public function never_changes_reviewed_or_manual_rows_and_never_reproposes_rejected_pairs(): void
    {
        $p = $this->place('Beach Nikolina', 43.0, 16.0);
        $q = $this->place('Beach Manual', 44.0, 16.0);
        $this->cam(1000000001, 'Town: Nikolina beach', 43.001, 16.0);
        $this->cam(1000000002, 'Town: Manual beach', 44.001, 16.0);
        $this->cam(1000000003, 'Town: Manual beach 2', 44.0012, 16.0);
        $this->cam(1000000004, 'Town: sea', 43.0015, 16.0, ['landscape']);   // p: 167 m = 40 → next candidate
        PlaceWebcam::create(['place_id' => $p, 'windy_id' => 1000000001, 'source' => 'matcher', 'review_status' => 'rejected', 'match_score' => 95, 'reviewed_at' => now()]);
        PlaceWebcam::create(['place_id' => $q, 'windy_id' => 1000000003, 'source' => 'manual', 'review_status' => 'approved', 'match_score' => 0, 'is_primary' => true]);

        $this->match();
        $this->match();

        $this->assertSame([1000000001 => 'rejected', 1000000004 => 'pending'],
            PlaceWebcam::where('place_id', $p)->orderBy('windy_id')->pluck('review_status', 'windy_id')->all());
        $manual = PlaceWebcam::where('place_id', $q)->sole();
        $this->assertSame([1000000003, 'approved', 0, true], [$manual->windy_id, $manual->review_status, $manual->match_score, $manual->is_primary]);
    }

    /** Regression (dev, 2026-10-14): an approved matcher row was treated as replaceable, so a neighbour got the
     *  same webcam and the run died on place_webcams_one_place_per_webcam. */
    #[Test]
    public function a_webcam_approved_on_one_place_is_never_offered_to_another(): void
    {
        $owner = $this->place('Beach Prvi Zal', 43.0, 16.0);
        $neighbour = $this->place('Beach Prvi Zal Two', 43.002, 16.0);
        $this->cam(1000000001, 'Lumbarda: Prvi Zal beach', 43.001, 16.0);   // 111 m from both, name hit for both → 95
        PlaceWebcam::create(['place_id' => $owner, 'windy_id' => 1000000001, 'source' => 'matcher', 'review_status' => 'approved',
            'is_primary' => true, 'match_score' => 95, 'reviewed_at' => now()]);

        $this->match();

        $this->assertSame(0, PlaceWebcam::where('place_id', $neighbour)->count());
        $this->assertSame([$owner], PlaceWebcam::where('windy_id', 1000000001)->pluck('place_id')->all());
    }

    #[Test]
    public function rerun_is_idempotent_and_dry_run_writes_nothing(): void
    {
        $this->place('Beach Nikolina', 43.0, 16.0);
        $this->cam(1000000001, 'Town: Nikolina beach', 43.001, 16.0);

        $this->match(['--dry-run' => true]);
        $this->assertSame(0, PlaceWebcam::count());

        $this->match();
        $first = PlaceWebcam::sole()->only(['place_id', 'windy_id', 'review_status', 'match_score', 'is_primary']);
        $this->match();
        $this->assertSame($first, PlaceWebcam::sole()->only(['place_id', 'windy_id', 'review_status', 'match_score', 'is_primary']));
    }

    #[Test]
    public function database_allows_one_primary_per_place_and_one_shown_place_per_webcam(): void
    {
        $p = $this->place('Beach A', 43.0, 16.0);
        $q = $this->place('Beach B', 44.0, 16.0);
        $this->cam(1000000001, 'x', 43.0, 16.0);
        $this->cam(1000000002, 'y', 43.0, 16.0);
        PlaceWebcam::create(['place_id' => $p, 'windy_id' => 1000000001, 'source' => 'manual', 'review_status' => 'approved', 'is_primary' => true]);

        try {
            DB::transaction(fn () => PlaceWebcam::create(['place_id' => $p, 'windy_id' => 1000000002, 'source' => 'manual', 'review_status' => 'approved', 'is_primary' => true]));
            $this->fail('second primary accepted');
        } catch (QueryException) {
        }
        try {
            DB::transaction(fn () => PlaceWebcam::create(['place_id' => $q, 'windy_id' => 1000000001, 'source' => 'manual', 'review_status' => 'approved']));
            $this->fail('same webcam shown on two places');
        } catch (QueryException) {
        }
        $this->assertSame(1, PlaceWebcam::count());
    }

    #[Test]
    public function location_follows_lat_lon(): void
    {
        $this->cam(1000000001, 'x', 45.5, 13.5);
        $wkt = DB::scalar('SELECT ST_AsText(location::geometry) FROM windy_webcams WHERE windy_id = 1000000001');
        $this->assertSame('POINT(13.5 45.5)', $wkt);
    }
}
