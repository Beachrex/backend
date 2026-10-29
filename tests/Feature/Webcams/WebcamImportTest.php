<?php

namespace Tests\Feature\Webcams;

use App\Services\Webcams\EmbedChecker;
use App\Services\Webcams\WebcamImporter;
use App\Services\Webcams\WindyApiClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** TASK-006 §6.1 with a mocked Windy API (CI never calls the real one). */
class WebcamImportTest extends TestCase
{
    use RefreshDatabase;

    private const API = 'api.windy.com/webcams/api/v3';

    protected function setUp(): void
    {
        parent::setUp();
        config(['webcams.api.key' => 'test-key', 'webcams.api.daily_request_budget' => 100]);
    }

    private function cam(int $id, string $cc = 'HR', array $cats = ['beach']): array
    {
        return [
            'webcamId' => $id, 'title' => "Cam {$id}", 'status' => 'active', 'lastUpdatedOn' => '2026-10-01T10:00:00.000Z',
            'categories' => array_map(fn ($c) => ['id' => $c, 'name' => ucfirst($c)], $cats),
            'location' => ['latitude' => 45.1, 'longitude' => 14.2, 'city' => 'Town', 'region' => 'R', 'region_code' => "{$cc}.01", 'country_code' => $cc],
            'player' => ['day' => "https://webcams.windy.com/webcams/public//player?webcamId={$id}&playerType=day"],
            'images' => ['current' => ['preview' => "https://imgproxy.windy.com/_/preview/token-secret/{$id}.jpg"]],
        ];
    }

    private array $listed = [];
    private ?int $listedTotal = null;
    private bool $listFaked = false;

    /** Pages of the given cams for any /webcams request, honouring offset. Later calls replace the cams. */
    private function fakeList(array $cams, ?int $total = null): void
    {
        $this->listed = $cams;
        $this->listedTotal = $total;
        if ($this->listFaked) {
            return;   // Http::fake stubs stack and the first match wins, so register once
        }
        $this->listFaked = true;
        Http::fake([self::API . '/webcams*' => function (Request $r) {
            parse_str((string) parse_url($r->url(), PHP_URL_QUERY), $q);

            return Http::response(['total' => $this->listedTotal ?? count($this->listed), 'webcams' => array_slice($this->listed, (int) ($q['offset'] ?? 0), 50)]);
        }]);
    }

    private function import(array $countries = ['HR'], bool $fresh = false): array
    {
        $importer = app(WebcamImporter::class);
        $result = $importer->run($importer->runKey($countries, $fresh), $countries);

        return $result + ['stats' => $importer->stats];
    }

    #[Test]
    public function imports_metadata_with_the_key_header_and_comma_separated_arrays(): void
    {
        $this->fakeList(array_map(fn ($i) => $this->cam(1000000000 + $i), range(1, 120)));

        $r = $this->import();

        $this->assertSame(120, DB::table('windy_webcams')->count());
        $this->assertSame(3, $r['requests']);
        $this->assertTrue($r['finished']);
        Http::assertSent(function (Request $req) {
            parse_str((string) parse_url($req->url(), PHP_URL_QUERY), $q);

            return $req->header('x-windy-api-key') === ['test-key'] && $q['countries'] === 'HR'
                && $q['categories'] === 'beach,coast,lake,river,port' && $q['include'] === 'categories,location,player'
                && $q['limit'] === '50' && $q['categoryOperation'] === 'or';
        });
        $row = DB::table('windy_webcams')->where('windy_id', 1000000001)->first();
        $this->assertSame('["beach"]', $row->categories);
        $this->assertSame('POINT(14.2 45.1)', DB::scalar('SELECT ST_AsText(location::geometry) FROM windy_webcams WHERE windy_id = 1000000001'));
    }

    #[Test]
    public function never_stores_image_urls(): void
    {
        $this->fakeList([$this->cam(1000000001)]);

        $this->import();

        $dump = json_encode(DB::table('windy_webcams')->get());
        $this->assertStringNotContainsString('imgproxy', $dump);
        $this->assertStringNotContainsString('token-secret', $dump);
        $this->assertStringContainsString('player', $dump);
    }

    #[Test]
    public function running_twice_gives_the_same_state(): void
    {
        $this->fakeList([$this->cam(1000000001), $this->cam(1000000002)]);
        $this->import();
        $first = DB::table('windy_webcams')->orderBy('windy_id')->get(['windy_id', 'title', 'status', 'categories', 'first_seen_at', 'missing_since']);

        $r = $this->import(fresh: true);

        $this->assertEquals($first, DB::table('windy_webcams')->orderBy('windy_id')->get(['windy_id', 'title', 'status', 'categories', 'first_seen_at', 'missing_since']));
        $this->assertSame(['new' => 0, 'updated' => 2, 'missing' => 0], $r['stats']['HR']);
    }

    #[Test]
    public function stops_at_the_budget_and_resumes_from_the_checkpoint(): void
    {
        config(['webcams.api.daily_request_budget' => 2]);
        $this->fakeList(array_map(fn ($i) => $this->cam(1000000000 + $i), range(1, 160)));

        $r = $this->import();
        $this->assertFalse($r['finished']);
        $this->assertSame(2, $r['requests']);
        $this->assertSame(100, DB::table('windy_webcams')->count());
        $this->assertSame(['running', 100], [DB::table('windy_import_slices')->value('status'), DB::table('windy_import_slices')->value('next_offset')]);

        $this->travel(1)->days();
        $r = $this->import();
        $this->assertTrue($r['finished']);
        $this->assertSame(160, DB::table('windy_webcams')->count());
        $this->assertSame(1, DB::table('windy_import_slices')->count());   // same run resumed
        Http::assertSentCount(4);                                         // 0, 50 | 100, 150
    }

    #[Test]
    public function backs_off_on_429_and_5xx(): void
    {
        $slept = [];
        $this->app->bind(WindyApiClient::class, function () use (&$slept) {
            return new WindyApiClient(function (int $s) use (&$slept) { $slept[] = $s; });
        });
        Http::fake([self::API . '/webcams*' => Http::sequence()
            ->push('', 429, ['Retry-After' => '3'])
            ->push('', 503)
            ->push(['total' => 1, 'webcams' => [$this->cam(1000000001)]])]);

        $r = $this->import();

        $this->assertSame([3, 4], $slept);
        $this->assertSame(3, $r['requests']);   // retries count against the budget
        $this->assertSame(1, DB::table('windy_webcams')->count());
    }

    #[Test]
    public function splits_big_countries_by_region(): void
    {
        Http::fake([
            self::API . '/regions*' => Http::response([['code' => 'IT.01', 'name' => 'A'], ['code' => 'IT.02', 'name' => 'B'], ['code' => 'FR.01', 'name' => 'C']]),
            self::API . '/webcams*' => function (Request $r) {
                parse_str((string) parse_url($r->url(), PHP_URL_QUERY), $q);
                if (isset($q['countries'])) {
                    return Http::response(['total' => 5000, 'webcams' => [$this->cam(1000000009, 'IT')]]);
                }

                return Http::response(['total' => 1, 'webcams' => [$this->cam($q['regions'] === 'IT.01' ? 1000000001 : 1000000002, 'IT')]]);
            },
        ]);

        $this->import(['IT']);

        $this->assertSame(['IT' => 'split', 'IT.01' => 'done', 'IT.02' => 'done'],
            DB::table('windy_import_slices')->orderBy('id')->get()->mapWithKeys(fn ($s) => [$s->region_code ?? $s->country_code => $s->status])->all());
        $this->assertEqualsCanonicalizing([1000000001, 1000000002], DB::table('windy_webcams')->pluck('windy_id')->map(fn ($v) => (int) $v)->all());
    }

    #[Test]
    public function marks_webcams_not_returned_as_missing_and_clears_it_when_they_return(): void
    {
        $this->fakeList([$this->cam(1000000001), $this->cam(1000000002)]);
        $this->import();
        DB::table('windy_webcams')->insert(['windy_id' => 1000000003, 'lat' => 45, 'lon' => 14, 'country_code' => 'HR', 'categories' => '["city"]',
            'last_seen_at' => now()->subYear(), 'created_at' => now(), 'updated_at' => now()]);   // outside the import categories

        $this->travel(1)->hours();
        $this->fakeList([$this->cam(1000000001)]);
        $r = $this->import(fresh: true);
        $this->assertSame(1, $r['stats']['HR']['missing']);
        $this->assertNotNull(DB::table('windy_webcams')->where('windy_id', 1000000002)->value('missing_since'));
        $this->assertNull(DB::table('windy_webcams')->where('windy_id', 1000000003)->value('missing_since'));

        $this->travel(1)->hours();
        $this->fakeList([$this->cam(1000000001), $this->cam(1000000002)]);
        $this->import(fresh: true);
        $this->assertNull(DB::table('windy_webcams')->where('windy_id', 1000000002)->value('missing_since'));
    }

    #[Test]
    public function truncated_slices_do_not_mark_anything_missing(): void
    {
        $this->fakeList([$this->cam(1000000001)]);
        $this->import();
        $this->travel(1)->hours();
        Http::fake([self::API . '/regions*' => Http::response([])]);   // no regions to split into
        $this->fakeList([], 2000);

        $this->import(fresh: true);

        $this->assertSame('truncated', DB::table('windy_import_slices')->orderByDesc('id')->value('status'));
        $this->assertNull(DB::table('windy_webcams')->where('windy_id', 1000000001)->value('missing_since'));
    }

    #[Test]
    public function command_fails_without_a_key_and_dry_run_calls_nothing(): void
    {
        Http::fake();
        $this->artisan('webcams:import', ['--country' => ['HR'], '--dry-run' => true])->expectsOutputToContain('[dry-run]')->assertSuccessful();
        Http::assertNothingSent();

        config(['webcams.api.key' => null]);
        $this->artisan('webcams:import', ['--country' => ['HR']])->expectsOutputToContain('WINDY_API_KEY is not set')->assertFailed();
        Http::assertNothingSent();
    }

    #[Test]
    public function embed_check_classifies_the_player_answers(): void
    {
        foreach ([1000000001 => 'ok', 1000000002 => 'not_public', 1000000003 => 'unavailable'] as $id => $_) {
            DB::table('windy_webcams')->insert(['windy_id' => $id, 'lat' => 45, 'lon' => 14, 'created_at' => now(), 'updated_at' => now()]);
        }
        Http::fake(['webcams.windy.com/webcams/public/embed/player*' => function (Request $r) {
            parse_str((string) parse_url($r->url(), PHP_URL_QUERY), $q);

            return Http::response(match ((int) $q['webcamId']) {
                1000000001 => '<html><head><title>Webcam player - Neum</title></head></html>',
                1000000002 => "<div>\n\tSorry. The embed player for this webcam is not publicly available. <a>x</a></div>",
                default => "<div>\n\tSorry. The embed player for this webcam is currently not available. <a>x</a></div>",
            });
        }]);
        $this->app->bind(EmbedChecker::class, fn () => new EmbedChecker(fn () => null));

        $this->artisan('webcams:embed-check', ['--id' => [1000000001, 1000000002, 1000000003]])->assertSuccessful();

        $this->assertSame([1000000001 => 'ok', 1000000002 => 'not_public', 1000000003 => 'unavailable'],
            DB::table('windy_webcams')->orderBy('windy_id')->pluck('embed_status', 'windy_id')->all());
        Http::assertNotSent(fn (Request $r) => $r->hasHeader('x-windy-api-key'));
        $this->assertSame('unknown', EmbedChecker::classify('<html>something else</html>'));
    }
}
