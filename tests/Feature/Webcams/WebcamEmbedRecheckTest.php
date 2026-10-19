<?php

namespace Tests\Feature\Webcams;

use App\Models\Beach;
use App\Models\PlaceWebcam;
use App\Models\WindyWebcam;
use App\Services\Webcams\EmbedChecker;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Owner report 2026-10-19: a beach page showed Windy's "The embed player for this webcam is not publicly
 * available". The webcam passed the player check on 2026-10-14 and was never checked again (30-day stale
 * window), and cached beach details kept it. Shown webcams are now re-checked daily.
 */
class WebcamEmbedRecheckTest extends TestCase
{
    use RefreshDatabase;

    private const PLAYER = 'webcams.windy.com/webcams/public/embed/player*';

    protected function setUp(): void
    {
        parent::setUp();
        config(['webcams.import.embed_check_delay_ms' => 0, 'app.frontend_url' => 'https://dev.beachrex.com']);
        $this->app->bind(EmbedChecker::class, fn () => new EmbedChecker(fn () => null));
        DB::table('status')->insertOrIgnore([['id' => 1, 'name' => 'active']]);
        DB::table('countries')->insertOrIgnore(['code' => 'FR']);
    }

    private function place(string $name): int
    {
        $id = DB::table('beach')->insertGetId(['name' => $name, 'country_code' => 'FR', 'status_id' => 1, 'latitude' => 43.69, 'longitude' => 7.27]);
        DB::table('beach_translation')->insert(['beach_id' => $id, 'locale' => 'en', 'name' => $name, 'slug' => 'b-' . $id]);

        return $id;
    }

    private function cam(int $id, string $embed = 'ok'): void
    {
        WindyWebcam::create(['windy_id' => $id, 'title' => "Nice {$id}", 'lat' => 43.69, 'lon' => 7.27, 'country_code' => 'FR',
            'categories' => ['beach'], 'status' => 'active', 'embed_status' => $embed, 'embed_checked_at' => now()->subDays(5),
            'last_seen_at' => now()]);
    }

    /** @param array<int,string> $bodies webcam id → player body */
    private function fakePlayer(array $bodies): void
    {
        Http::fake([self::PLAYER => function (Request $r) use ($bodies) {
            parse_str((string) parse_url($r->url(), PHP_URL_QUERY), $q);
            $body = $bodies[(int) $q['webcamId']] ?? null;

            return $body === null ? Http::response('', 500) : Http::response($body);
        }]);
    }

    private const OK = '<html><head><title>Webcam player - Nice</title></head></html>';
    private const NOT_PUBLIC = "<div>\n\tSorry. The embed player for this webcam is not publicly available. <a>x</a></div>";

    #[Test]
    public function shown_webcam_that_stopped_embedding_is_hidden_and_its_page_cache_cleared(): void
    {
        $nice = $this->place('Beach Nice');
        $this->cam(1000000001);            // primary, now "not publicly available"
        $this->cam(1000000002);            // second shown webcam, still fine
        $this->cam(1000000003);            // only pending: not shown, not re-checked
        PlaceWebcam::create(['place_id' => $nice, 'windy_id' => 1000000001, 'source' => 'matcher', 'review_status' => 'auto_approved', 'is_primary' => true, 'match_score' => 90]);
        PlaceWebcam::create(['place_id' => $nice, 'windy_id' => 1000000002, 'source' => 'matcher', 'review_status' => 'approved', 'is_primary' => false, 'match_score' => 80]);
        PlaceWebcam::create(['place_id' => $nice, 'windy_id' => 1000000003, 'source' => 'matcher', 'review_status' => 'pending', 'match_score' => 50]);
        Cache::put("beach_details:en:b-{$nice}", ['cached' => true], 3600);
        Cache::put("beach_details:en:{$nice}", ['cached' => true], 3600);
        $this->fakePlayer([1000000001 => self::NOT_PUBLIC, 1000000002 => self::OK]);

        // Checked 5 days ago: the stale-based run has nothing to do, which is how the broken embed stayed visible.
        $this->artisan('webcams:embed-check')->assertSuccessful();
        Http::assertNothingSent();

        $this->artisan('webcams:embed-check', ['--shown' => true])
            ->expectsOutputToContain("Places whose shown webcam changed: {$nice}")
            ->assertSuccessful();

        $this->assertSame([1000000001 => 'not_public', 1000000002 => 'ok', 1000000003 => 'ok'],
            WindyWebcam::orderBy('windy_id')->pluck('embed_status', 'windy_id')->all());
        Http::assertSentCount(2);
        $this->assertSame([1000000001 => false, 1000000002 => true, 1000000003 => false],
            PlaceWebcam::orderBy('windy_id')->pluck('is_primary', 'windy_id')->all());
        $this->assertFalse(Cache::has("beach_details:en:b-{$nice}"));
        $this->assertFalse(Cache::has("beach_details:en:{$nice}"));

        $this->assertSame([1000000002], Beach::findOrFail($nice)->shownWebcams->pluck('windy_id')->all());
    }

    #[Test]
    public function a_failed_request_keeps_the_last_known_result(): void
    {
        $this->cam(1000000001, 'ok');
        $this->cam(1000000002, 'unknown');
        $this->fakePlayer([]);   // every request answers 500

        $this->artisan('webcams:embed-check', ['--id' => [1000000001, 1000000002]])->assertSuccessful();

        $this->assertSame([1000000001 => 'ok', 1000000002 => 'unknown'],
            WindyWebcam::orderBy('windy_id')->pluck('embed_status', 'windy_id')->all());
    }

    #[Test]
    public function the_check_asks_the_player_like_our_iframe_does(): void
    {
        $this->cam(1000000001, 'unknown');
        $this->fakePlayer([1000000001 => self::OK]);

        $this->artisan('webcams:embed-check', ['--id' => [1000000001]])->assertSuccessful();

        Http::assertSent(function (Request $r) {
            parse_str((string) parse_url($r->url(), PHP_URL_QUERY), $q);

            return $r->header('Referer') === ['https://dev.beachrex.com/']
                && $q === ['webcamId' => '1000000001', 'playerType' => 'day', 'loop' => '0', 'autoPlay' => '0', 'interactive' => '1'];
        });
    }

    #[Test]
    public function shown_webcams_are_rechecked_daily_by_the_scheduler(): void
    {
        $events = collect(app(Schedule::class)->events())->filter(fn ($e) => str_contains((string) $e->command, 'webcams:embed-check --shown'));
        $this->assertCount(1, $events);
        $this->assertSame('10 5 * * *', $events->first()->expression);
    }
}
