<?php

namespace Tests\Feature;

use App\Models\Status;
use App\Models\User;
use App\Notifications\PreviewAccessLinkNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Owner 2026-10-29: "dev in uat stran bi morali zapreti — vpišem svoj mail, dobim magic link".
 * ADR "Closed dev/uat websites behind an e-mail magic link".
 */
class PreviewAccessApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Status::insert([
            ['id' => 1, 'name' => 'active'],
            ['id' => 2, 'name' => 'inactive'],
        ]);
        config([
            'app.frontend_url' => 'https://dev.beachrex.com',
            'preview_access.enabled' => true,
            'preview_access.allowed_emails' => ['tester@example.test'],
        ]);
    }

    private function requestLink(string $email, ?string $returnPath = '/si/plaze', array $headers = [])
    {
        return $this->withHeaders($headers)->postJson('/api/v1/preview-access/request', array_filter([
            'email' => $email,
            'return_path' => $returnPath,
        ], fn ($v) => $v !== null));
    }

    /** The token from the one link sent to $email. */
    private function sentToken(string $email): string
    {
        $token = null;
        Notification::assertSentOnDemand(PreviewAccessLinkNotification::class, function ($n, $channels, AnonymousNotifiable $to) use ($email, &$token) {
            if (($to->routes['mail'] ?? null) !== $email) {
                return false;
            }
            parse_str((string) parse_url($n->url(), PHP_URL_QUERY), $q);
            $token = $q['token'];

            return true;
        });

        return $token;
    }

    public function test_an_admin_gets_a_one_time_link_to_the_website_callback(): void
    {
        Notification::fake();
        User::factory()->create(['email' => 'Owner@Example.test', 'is_admin' => true]);

        $this->requestLink('owner@example.test')
            ->assertOk()
            ->assertExactJson(['message' => 'If this e-mail may open the test website, we have sent a link.']);

        Notification::assertSentOnDemand(PreviewAccessLinkNotification::class, function (PreviewAccessLinkNotification $n) {
            return str_starts_with($n->url(), 'https://dev.beachrex.com/preview-access/callback?token=');
        });
        $token = $this->sentToken('owner@example.test');
        $this->assertSame(64, strlen($token));
        // Only the hash is stored
        $this->assertSame(0, DB::table('preview_access_tokens')->where('token_hash', $token)->count());
        $this->assertSame(1, DB::table('preview_access_tokens')->where('token_hash', hash('sha256', $token))->count());
    }

    public function test_an_address_from_the_allow_list_gets_a_link(): void
    {
        Notification::fake();

        $this->requestLink('Tester@example.test')->assertOk();

        $this->sentToken('tester@example.test');
    }

    public function test_other_addresses_get_the_same_answer_and_no_mail(): void
    {
        Notification::fake();
        User::factory()->create(['email' => 'user@example.test', 'is_admin' => false]);
        User::factory()->create(['email' => 'admin@example.test', 'is_admin' => true]);

        $allowed = $this->requestLink('admin@example.test');
        $normalUser = $this->requestLink('user@example.test');
        $unknown = $this->requestLink('nobody@example.test');

        $this->assertSame($allowed->getContent(), $normalUser->getContent());
        $this->assertSame($allowed->getContent(), $unknown->getContent());
        $this->assertSame($allowed->getStatusCode(), $unknown->getStatusCode());
        Notification::assertSentOnDemandTimes(PreviewAccessLinkNotification::class, 1);
        $this->assertSame(1, DB::table('preview_access_tokens')->count());
    }

    public function test_the_link_works_once_and_returns_the_path(): void
    {
        Notification::fake();
        $this->requestLink('tester@example.test', '/si/plaze?x=1');
        $token = $this->sentToken('tester@example.test');

        $this->postJson('/api/v1/preview-access/verify', ['token' => $token])
            ->assertOk()
            ->assertExactJson(['ok' => true, 'email' => 'tester@example.test', 'return_path' => '/si/plaze?x=1']);

        $this->postJson('/api/v1/preview-access/verify', ['token' => $token])
            ->assertStatus(422)
            ->assertJsonPath('ok', false);
    }

    public function test_the_link_expires_after_15_minutes(): void
    {
        Notification::fake();
        $this->requestLink('tester@example.test');
        $token = $this->sentToken('tester@example.test');

        $this->travel(15)->minutes();
        $this->travel(1)->seconds();

        $this->postJson('/api/v1/preview-access/verify', ['token' => $token])->assertStatus(422);
    }

    public function test_the_link_still_works_just_before_expiry(): void
    {
        Notification::fake();
        $this->requestLink('tester@example.test');
        $token = $this->sentToken('tester@example.test');

        $this->travel(14)->minutes();

        $this->postJson('/api/v1/preview-access/verify', ['token' => $token])->assertOk();
    }

    public function test_an_unknown_token_is_rejected(): void
    {
        $this->postJson('/api/v1/preview-access/verify', ['token' => str_repeat('a', 64)])
            ->assertStatus(422)
            ->assertJsonPath('message', 'This link is invalid, already used or expired.');
    }

    public function test_one_mail_per_address_per_minute_and_five_per_hour(): void
    {
        Notification::fake();

        $this->requestLink('tester@example.test')->assertOk();
        $this->requestLink('tester@example.test')->assertOk();
        Notification::assertSentOnDemandTimes(PreviewAccessLinkNotification::class, 1);

        for ($i = 0; $i < 6; $i++) {
            $this->travel(61)->seconds();
            $this->requestLink('tester@example.test')->assertOk();
        }
        Notification::assertSentOnDemandTimes(PreviewAccessLinkNotification::class, 5);
    }

    public function test_requests_are_rate_limited_per_ip(): void
    {
        Notification::fake();

        for ($i = 0; $i < 5; $i++) {
            $this->requestLink("someone{$i}@example.test")->assertOk();
        }
        $this->requestLink('someone9@example.test')->assertStatus(429);
    }

    public function test_return_paths_to_other_sites_are_rejected(): void
    {
        Notification::fake();

        foreach (['https://evil.example', '//evil.example', '/\\evil.example', 'javascript:alert(1)', "/\nx", 'evil.example/x'] as $bad) {
            $this->travel(61)->seconds(); // stay under the per-IP limit
            $this->requestLink('tester@example.test', $bad)
                ->assertStatus(422)
                ->assertJsonValidationErrors('return_path');
        }
        Notification::assertNothingSent();
    }

    public function test_the_return_path_defaults_to_the_home_page(): void
    {
        Notification::fake();
        $this->requestLink('tester@example.test', null)->assertOk();
        $token = $this->sentToken('tester@example.test');

        $this->postJson('/api/v1/preview-access/verify', ['token' => $token])->assertJsonPath('return_path', '/');
    }

    public function test_both_endpoints_are_404_when_the_gate_is_off(): void
    {
        Notification::fake();
        config(['preview_access.enabled' => false]);

        $this->requestLink('tester@example.test')->assertNotFound();
        $this->postJson('/api/v1/preview-access/verify', ['token' => 'x'])->assertNotFound();
        Notification::assertNothingSent();
    }

    public function test_both_endpoints_are_404_on_production_even_when_switched_on(): void
    {
        Notification::fake();
        config(['app.frontend_url' => 'https://beachrex.com']);

        $this->requestLink('tester@example.test')->assertNotFound();
        $this->postJson('/api/v1/preview-access/verify', ['token' => 'x'])->assertNotFound();
        Notification::assertNothingSent();
    }

    public function test_the_email_renders_in_the_requested_language(): void
    {
        $html = (string) (new PreviewAccessLinkNotification('tok123', 15))->locale('sl')
            ->toMail(new AnonymousNotifiable)->render();

        $this->assertStringContainsString('Odpri testno stran', $html);
        $this->assertStringContainsString('dev.beachrex.com', $html);
        $this->assertStringContainsString('https://dev.beachrex.com/preview-access/callback?token=tok123', $html);
    }

    public function test_the_mail_is_sent_in_the_language_of_the_request(): void
    {
        Notification::fake();

        $this->requestLink('tester@example.test', '/', ['Accept-Language' => 'si'])
            ->assertJsonPath('message', 'Če ima ta e-naslov dostop do testne strani, smo poslali povezavo.');

        Notification::assertSentOnDemand(PreviewAccessLinkNotification::class, fn ($n) => $n->locale === 'sl');
    }
}
