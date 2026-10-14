<?php

namespace Tests\Feature\Webcams;

use App\Models\PlaceWebcam;
use App\Models\WindyWebcam;
use App\Services\Webcams\EmbedChecker;
use App\Services\Webcams\WebcamRefresher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** TASK-006 §6.2 legacy salvage and §6.4 refresh, Windy API and player mocked. */
class WebcamLegacyAndRefreshTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['webcams.api.key' => 'test-key', 'webcams.api.daily_request_budget' => 100, 'webcams.import.embed_check_delay_ms' => 0]);
        DB::table('status')->insertOrIgnore([['id' => 1, 'name' => 'active']]);
        DB::table('countries')->insertOrIgnore(['code' => 'HR']);
        Storage::fake('local');
    }

    private function place(string $name, float $lat, ?string $webcam = null): int
    {
        $id = DB::table('beach')->insertGetId(['name' => $name, 'country_code' => 'HR', 'status_id' => 1, 'latitude' => $lat, 'longitude' => 16.0, 'webcam' => $webcam]);
        DB::table('beach_translation')->insert(['beach_id' => $id, 'locale' => 'en', 'name' => $name, 'slug' => 'b-' . $id]);

        return $id;
    }

    private function cam(int $id, string $title, float $lat, array $cats = ['beach'], array $extra = []): void
    {
        WindyWebcam::create(array_merge(['windy_id' => $id, 'title' => $title, 'lat' => $lat, 'lon' => 16.0, 'country_code' => 'HR',
            'categories' => $cats, 'status' => 'active', 'embed_status' => 'ok', 'last_seen_at' => now()], $extra));
    }

    private static function windy(int $id): string
    {
        return '<a name="windy-webcam-timelapse-player" data-id="' . $id . '" data-play="day" href="https://windy.com/webcams/' . $id . '">x</a><script async src="https://webcams.windy.com/webcams/public/embed/script/player.js"></script>';
    }

    private static function lookr(int $id): string
    {
        return '<a name="lkr-timelapse-player" data-id="' . $id . '" data-play="day" href="//lookr.com/' . $id . '">x</a><script async src="https://api.lookr.com/embed/script/timelapse.js"></script>';
    }

    private function fakeWindy(): void
    {
        Http::fake([
            'api.windy.com/webcams/api/v3/webcams*' => Http::response(['total' => 1, 'webcams' => [[
                'webcamId' => 1000000005, 'title' => 'Town: Echo beach', 'status' => 'active',
                'categories' => [['id' => 'beach', 'name' => 'Beach']],
                'location' => ['latitude' => 47.0005, 'longitude' => 16.0, 'country_code' => 'HR'],
                'images' => ['current' => ['preview' => 'https://imgproxy.windy.com/token/x.jpg']],
            ]]]),
            'webcams.windy.com/webcams/public/embed/player*' => Http::response('<title>Webcam player - Echo</title>'),
        ]);
    }

    #[Test]
    public function salvages_every_legacy_format_and_reports_each_place(): void
    {
        $this->cam(1000000001, 'Town: Alpha beach', 43.001);                                        // 111 m, name → kept
        $this->cam(1000000002, 'Town: Bravo beach', 44.040);                                        // 4.4 km → pending
        $this->cam(1000000003, 'Town: x', 45.001, ['beach'], ['embed_status' => 'not_public']);    // dead
        $this->cam(1000000004, 'Elsewhere', 46.026, ['city']);                                      // 2.9 km, 10 − 20 → below
        $alpha = $this->place('Beach Alpha', 43.0, self::windy(1000000001));
        $bravo = $this->place('Beach Bravo', 44.0, self::lookr(1000000002));
        $this->place('Beach Charlie', 45.0, self::lookr(1000000003));
        $this->place('Beach Delta', 46.0, self::windy(1000000004));
        $echo = $this->place('Beach Echo', 47.0, self::windy(1000000005));                         // unknown → API → kept
        $this->place('Beach Fox', 48.0, self::windy(1000000006));                                   // unknown, not returned → dead
        $this->place('Beach Golf', 49.0, 'brez');
        $this->place('Beach Hotel', 50.0, 'http://www.whatsupcams.com/en/webcams/slovenia/piran');
        $this->place('Beach India', 51.0);
        $this->fakeWindy();

        $this->artisan('webcams:legacy-extract')->expectsOutputToContain('API requests used: 1')->assertSuccessful();

        $this->assertSame([
            $alpha => [1000000001, 'legacy', 'auto_approved', true],
            $bravo => [1000000002, 'legacy', 'pending', false],
            $echo => [1000000005, 'legacy', 'auto_approved', true],
        ], PlaceWebcam::orderBy('place_id')->get()->mapWithKeys(fn ($r) => [$r->place_id => [$r->windy_id, $r->source, $r->review_status, $r->is_primary]])->all());
        $this->assertSame(['beyond radius'], PlaceWebcam::where('place_id', $bravo)->sole()->score_breakdown['notes']);
        $this->assertStringNotContainsString('imgproxy', json_encode(DB::table('windy_webcams')->get()));
        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'webcamIds=1000000005%2C1000000006') || str_contains($r->url(), 'webcamIds=1000000005,1000000006'));

        $csv = Storage::disk('local')->get(Storage::disk('local')->files('webcams')[0]);
        foreach (['"Beach Alpha","windy_embed","1000000001","kept"', '"Beach Bravo","lookr_embed","1000000002","pending"',
            '"Beach Charlie","lookr_embed","1000000003","dead","player not_public"', '"Beach Delta","windy_embed","1000000004","below_threshold"',
            '"Beach Fox","windy_embed","1000000006","dead","not returned by Windy"', '"Beach Hotel","unparseable"'] as $line) {
            $this->assertStringContainsString($line, $csv);
        }
        $this->assertStringNotContainsString('Beach Golf', $csv);
        $this->assertStringNotContainsString('Beach India', $csv);
    }

    #[Test]
    public function dry_run_calls_nothing_and_writes_no_assignments_and_reruns_are_idempotent(): void
    {
        $this->cam(1000000001, 'Town: Alpha beach', 43.001);
        $this->place('Beach Alpha', 43.0, self::windy(1000000001));
        $this->place('Beach Echo', 47.0, self::windy(1000000005));
        $this->fakeWindy();

        $this->artisan('webcams:legacy-extract', ['--dry-run' => true])->assertSuccessful();
        Http::assertNothingSent();
        $this->assertSame(0, PlaceWebcam::count());

        $this->artisan('webcams:legacy-extract')->assertSuccessful();
        $this->artisan('webcams:legacy-extract')->assertSuccessful();
        $this->assertSame(2, PlaceWebcam::count());
        Http::assertSentCount(2);   // one API lookup + one player check, only on the first real run
    }

    #[Test]
    public function the_same_legacy_id_on_two_places_is_shown_on_one_only(): void
    {
        $this->cam(1000000001, 'Town: Alpha beach', 43.001);
        $a = $this->place('Beach Alpha', 43.0, self::windy(1000000001));
        $b = $this->place('Beach Alpha', 43.0005, self::lookr(1000000001));   // same name: would also score 95

        $this->artisan('webcams:legacy-extract')->assertSuccessful();

        $this->assertSame('auto_approved', PlaceWebcam::where('place_id', $a)->sole()->review_status);
        $this->assertSame('pending', PlaceWebcam::where('place_id', $b)->sole()->review_status);
    }

    #[Test]
    public function refresh_moves_the_primary_away_from_a_lost_webcam_and_alerts_on_big_losses(): void
    {
        Log::spy();
        config(['webcams.refresh.alert_email' => null]);
        $p = $this->place('Beach Alpha', 43.0);
        $this->cam(1000000001, 'Town: Alpha beach', 43.001, ['beach'], ['missing_since' => now()]);
        $this->cam(1000000002, 'Town: Alpha 2', 43.002);
        PlaceWebcam::create(['place_id' => $p, 'windy_id' => 1000000001, 'source' => 'manual', 'review_status' => 'approved', 'is_primary' => true, 'match_score' => 90]);
        PlaceWebcam::create(['place_id' => $p, 'windy_id' => 1000000002, 'source' => 'manual', 'review_status' => 'approved', 'match_score' => 60]);

        $result = app(WebcamRefresher::class)->demote();
        $this->assertSame(['lost' => 1, 'places' => [$p]], $result);
        $this->assertSame([1000000001 => false, 1000000002 => true], PlaceWebcam::orderBy('windy_id')->pluck('is_primary', 'windy_id')->all());
        $this->assertSame(['lost' => 0, 'places' => [$p]], app(WebcamRefresher::class)->demote());   // counted once

        // Full run: import (mocked, 1 webcam) then alert because 1 of 1 places lost its webcam.
        PlaceWebcam::query()->update(['is_primary' => false]);
        PlaceWebcam::where('windy_id', 1000000001)->update(['is_primary' => true]);
        Http::fake(['api.windy.com/*' => Http::response(['total' => 0, 'webcams' => []])]);
        $this->app->bind(EmbedChecker::class, fn () => new EmbedChecker(fn () => null));
        $out = app(WebcamRefresher::class)->run();
        $this->assertSame('refreshed', $out['action']);
        $this->assertSame(1, $out['lost']);
        Log::shouldHaveReceived('warning')->once()->withArgs(fn ($m) => str_contains($m, '1 of 1 places lost their main webcam'));
    }

    #[Test]
    public function refresh_does_nothing_when_the_last_run_is_recent_and_resumes_an_open_one(): void
    {
        $this->place('Beach Alpha', 43.0);
        DB::table('windy_import_slices')->insert(['run_key' => 'old', 'country_code' => 'HR', 'status' => 'done', 'created_at' => now()->subDays(3), 'updated_at' => now()->subDays(3)]);
        Http::fake();

        $this->assertSame('nothing to do', app(WebcamRefresher::class)->run()['action']);
        Http::assertNothingSent();

        $this->travel(28)->days();
        Http::fake(['api.windy.com/*' => Http::response(['total' => 0, 'webcams' => []])]);
        $this->assertSame('refreshed', app(WebcamRefresher::class)->run()['action']);
    }
}
