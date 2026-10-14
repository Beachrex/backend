<?php

namespace Tests\Feature\Webcams;

use App\Filament\BrAdmin\Resources\PlaceWebcamResource\Pages\ListPlaceWebcams;
use App\Models\Beach;
use App\Models\Continent;
use App\Models\Country;
use App\Models\PlaceWebcam;
use App\Models\Status;
use App\Models\User;
use App\Models\WindyWebcam;
use App\Services\Webcams\EmbedChecker;
use App\Services\Webcams\PlaceWebcamReview;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** TASK-006 §7 admin review (authorization, actions) and §8 API fields (only verified webcams shown). */
class WebcamReviewAndApiTest extends TestCase
{
    use RefreshDatabase;

    private Beach $beach;
    private Beach $other;

    protected function setUp(): void
    {
        parent::setUp();
        Status::insert([['id' => 1, 'name' => 'active'], ['id' => 2, 'name' => 'inactive']]);
        Continent::create(['code' => 'EU']);
        Country::create(['code' => 'HR', 'continent_code' => 'EU']);
        config(['webcams.api.key' => 'test-key', 'webcams.import.embed_check_delay_ms' => 0]);
        $this->beach = Beach::factory()->create(['country_code' => 'HR', 'status_id' => 1, 'latitude' => 43.30, 'longitude' => 16.60, 'name' => 'Beach Nikolina']);
        $this->other = Beach::factory()->create(['country_code' => 'HR', 'status_id' => 1, 'latitude' => 43.40, 'longitude' => 16.60]);
    }

    private function cam(int $id, array $extra = []): WindyWebcam
    {
        return WindyWebcam::create(array_merge(['windy_id' => $id, 'title' => "Cam {$id}", 'lat' => 43.301, 'lon' => 16.60,
            'country_code' => 'HR', 'categories' => ['beach'], 'status' => 'active', 'embed_status' => 'ok'], $extra));
    }

    private function assign(int $windyId, string $status, bool $primary = false, ?Beach $beach = null, int $score = 80): PlaceWebcam
    {
        return PlaceWebcam::create(['place_id' => ($beach ?? $this->beach)->id, 'windy_id' => $windyId, 'source' => 'matcher',
            'review_status' => $status, 'is_primary' => $primary, 'match_score' => $score]);
    }

    private function review(): PlaceWebcamReview
    {
        $this->app->bind(EmbedChecker::class, fn () => new EmbedChecker(fn () => null));

        return app(PlaceWebcamReview::class);
    }

    #[Test]
    public function details_show_only_verified_usable_webcams_primary_first(): void
    {
        foreach ([1000000001, 1000000002, 1000000003, 1000000004] as $id) {
            $this->cam($id);
        }
        $this->cam(1000000005, ['embed_status' => 'not_public']);
        $this->assign(1000000001, 'approved', false, null, 60);
        $this->assign(1000000002, 'auto_approved', true, null, 90);
        $this->assign(1000000003, 'pending');
        $this->assign(1000000004, 'rejected');
        $this->assign(1000000005, 'approved', false, null, 99);

        $json = $this->getJson("/api/v1/beach-details/{$this->beach->id}")->assertOk()->json('data');

        $this->assertSame([1000000002, 1000000001], array_column($json['webcams'], 'windy_id'));
        $this->assertSame([true, false], array_column($json['webcams'], 'is_primary'));
        $this->assertSame('https://webcams.windy.com/webcams/public/embed/player?webcamId=1000000002&playerType=day&loop=0&autoPlay=0&interactive=1', $json['webcams'][0]['player_url']);
        $this->assertSame('https://www.windy.com/webcams/1000000002', $json['webcams'][0]['page_url']);
        $this->assertSame(1000000002, $json['embeds']['windy_webcam_id']);
        $this->assertArrayHasKey('webcam', $json['embeds']);   // legacy field kept for old app builds
    }

    #[Test]
    public function places_without_a_verified_webcam_get_empty_fields_everywhere(): void
    {
        $this->cam(1000000001);
        $this->assign(1000000001, 'pending');

        $details = $this->getJson("/api/v1/beach-details/{$this->beach->id}")->assertOk()->json('data');
        $this->assertSame([], $details['webcams']);
        $this->assertNull($details['embeds']['windy_webcam_id']);

        $list = $this->getJson('/api/v1/beaches?per_page=50')->assertOk()->json('data');
        $this->assertNull(collect($list)->firstWhere('id', $this->beach->id)['media']['embeds']['windy_webcam_id']);
    }

    #[Test]
    public function list_and_map_carry_the_primary_webcam_id(): void
    {
        $this->cam(1000000001);
        $this->assign(1000000001, 'approved', true);

        $list = $this->getJson('/api/v1/beaches?per_page=50')->assertOk()->json('data');
        $this->assertSame(1000000001, collect($list)->firstWhere('id', $this->beach->id)['media']['embeds']['windy_webcam_id']);

        $map = $this->getJson('/api/v1/spatial/beaches/bounding-box?min_lat=43&min_lon=16&max_lat=44&max_lon=17')->assertOk()->json();
        $rows = collect(data_get($map, 'data.beaches') ?? data_get($map, 'data'))->keyBy('id');
        $this->assertSame(1000000001, $rows[$this->beach->id]['media']['embeds']['windy_webcam_id']);
        $this->assertNull($rows[$this->other->id]['media']['embeds']['windy_webcam_id']);
    }

    #[Test]
    public function approve_reject_and_primary_follow_the_rules(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->cam(1000000001);
        $this->cam(1000000002);
        $a = $this->assign(1000000001, 'pending', false, null, 90);
        $b = $this->assign(1000000002, 'pending', false, null, 70);
        $s = $this->review();

        $s->approve($a, $admin->id);
        $this->assertSame(['approved', true, $admin->id], [$a->fresh()->review_status, $a->fresh()->is_primary, $a->fresh()->reviewed_by]);
        $s->approve($b, $admin->id);
        $this->assertFalse($b->fresh()->is_primary);   // first approved stays primary

        $s->makePrimary($b->fresh(), $admin->id);
        $this->assertSame([false, true], [$a->fresh()->is_primary, $b->fresh()->is_primary]);

        $s->reject($b->fresh(), $admin->id);
        $this->assertSame(['rejected', false], [$b->fresh()->review_status, $b->fresh()->is_primary]);
        $this->assertTrue($a->fresh()->is_primary);   // primary handed back

        $this->expectException(InvalidArgumentException::class);
        $s->makePrimary($b->fresh(), $admin->id);
    }

    #[Test]
    public function approving_a_webcam_shown_on_another_place_is_refused(): void
    {
        $this->cam(1000000001);
        $this->assign(1000000001, 'approved', true, $this->other);
        $row = $this->assign(1000000001, 'pending');

        $this->expectExceptionMessage('already shown on place ' . $this->other->id);
        $this->review()->approve($row, User::factory()->create()->id);
    }

    #[Test]
    public function manual_add_parses_links_verifies_unknown_ids_once_and_refuses_junk(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        Http::fake([
            'api.windy.com/*' => Http::response(['total' => 1, 'webcams' => [['webcamId' => 1616668973, 'title' => 'Neum', 'status' => 'active',
                'categories' => [['id' => 'beach']], 'location' => ['latitude' => 43.302, 'longitude' => 16.60, 'country_code' => 'HR']]]]),
            'webcams.windy.com/*' => Http::response('<title>Webcam player - Neum</title>'),
        ]);

        $row = $this->review()->addManual($this->beach->id, 'https://www.windy.com/hr/-Webkamere/Neum/webcams/1616668973', $admin->id);

        $this->assertSame([1616668973, 'manual', 'approved', true], [$row->windy_id, $row->source, $row->review_status, $row->is_primary]);
        Http::assertSentCount(2);   // one API verify + one player check

        try {
            $this->review()->addManual($this->beach->id, 'brez', $admin->id);
            $this->fail('junk accepted');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('No Windy webcam id', $e->getMessage());
        }
    }

    #[Test]
    public function review_changes_clear_the_cached_details(): void
    {
        $this->cam(1000000001);
        $row = $this->assign(1000000001, 'pending');
        $this->getJson("/api/v1/beach-details/{$this->beach->id}")->assertJsonPath('data.webcams', []);

        $this->review()->approve($row, User::factory()->create()->id);

        $this->getJson("/api/v1/beach-details/{$this->beach->id}")->assertJsonPath('data.webcams.0.windy_id', 1000000001);
    }

    #[Test]
    public function only_admins_reach_the_review_page(): void
    {
        $this->get(route('filament.admin.resources.place-webcams.index'))->assertRedirect();
        $user = User::factory()->create(['is_admin' => false]);
        $this->actingAs($user)->get(route('filament.admin.resources.place-webcams.index'))->assertForbidden();
    }

    #[Test]
    public function admin_lists_pending_by_default_and_approves_from_the_table(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->cam(1000000001);
        $this->cam(1000000002);
        $pending = $this->assign(1000000001, 'pending');
        $approved = $this->assign(1000000002, 'approved', true, $this->other);
        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(ListPlaceWebcams::class)
            ->assertOk()
            ->assertCanSeeTableRecords([$pending])
            ->assertCanNotSeeTableRecords([$approved])
            ->callTableAction('approve', $pending);

        $this->assertSame('approved', $pending->fresh()->review_status);
        $this->assertSame($admin->id, $pending->fresh()->reviewed_by);
    }
}
