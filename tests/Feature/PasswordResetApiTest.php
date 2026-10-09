<?php

namespace Tests\Feature;

use App\Models\Status;
use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

/**
 * Owner request 2026-10-09: "forgot password" for e-mail accounts (imported old-site users have a
 * random password). The e-mail is localized and links to the website's reset page.
 */
class PasswordResetApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Status::insert([
            ['id' => 1, 'name' => 'active'],
            ['id' => 2, 'name' => 'inactive'],
        ]);
        config(['app.frontend_url' => 'https://dev.beachrex.com']);
    }

    public function test_forgot_password_sends_a_localized_link_to_the_website(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'ana@example.test']);

        $this->withHeader('Accept-Language', 'si')
            ->postJson('/api/v1/forgot-password', ['email' => 'ana@example.test'])
            ->assertOk()
            ->assertJsonPath('message', 'Če za ta e-naslov obstaja račun, smo poslali povezavo za ponastavitev gesla.');

        Notification::assertSentTo($user, ResetPasswordNotification::class, function (ResetPasswordNotification $n) use ($user) {
            $mail = $n->toMail($user);
            $url = $n->resetUrl($user, 'sl');

            return $n->locale === 'sl'
                && $mail->subject === 'Ponastavite geslo za Beachrex'
                && str_starts_with($url, 'https://dev.beachrex.com/si/auth/reset-password?token=')
                && str_contains($url, 'email=ana%40example.test');
        });
    }

    public function test_the_email_renders_in_the_requested_language(): void
    {
        $user = User::factory()->create(['email' => 'ana@example.test']);
        $html = (string) (new ResetPasswordNotification('tok123'))->locale('de')->toMail($user)->render();

        $this->assertStringContainsString('Neues Passwort festlegen', $html);
        $this->assertStringContainsString('https://dev.beachrex.com/de/auth/reset-password?token=tok123&amp;email=ana%40example.test', $html);
        $this->assertStringContainsString('beachrex_horizontal_color_1000px.png', $html);
    }

    public function test_unknown_email_gets_the_same_answer_and_no_mail(): void
    {
        Notification::fake();

        $this->postJson('/api/v1/forgot-password', ['email' => 'nobody@example.test'])
            ->assertOk()
            ->assertJsonPath('message', 'If an account exists for this e-mail, we have sent a link to reset the password.');

        Notification::assertNothingSent();
    }

    public function test_a_mail_server_error_does_not_fail_the_request(): void
    {
        User::factory()->create(['email' => 'ana@example.test']);
        Mail::shouldReceive('mailer')->andThrow(new \RuntimeException('SMTP down'));
        Notification::shouldReceive('send')->andThrow(new \RuntimeException('SMTP down'));

        $this->postJson('/api/v1/forgot-password', ['email' => 'ana@example.test'])->assertOk();
    }

    public function test_forgot_password_validates_and_is_rate_limited(): void
    {
        Notification::fake();

        $this->postJson('/api/v1/forgot-password', ['email' => 'not-an-email'])->assertStatus(422);
        for ($i = 0; $i < 4; $i++) {
            $this->postJson('/api/v1/forgot-password', ['email' => "x{$i}@example.test"])->assertOk();
        }
        $this->postJson('/api/v1/forgot-password', ['email' => 'y@example.test'])->assertStatus(429);
    }

    public function test_reset_sets_the_password_revokes_tokens_and_allows_login(): void
    {
        $user = User::factory()->create(['email' => 'ana@example.test', 'email_verified_at' => null]);
        $user->createToken('old-device');
        $token = Password::broker()->createToken($user);

        $this->postJson('/api/v1/reset-password', [
            'token' => $token,
            'email' => 'ana@example.test',
            'password' => 'new-secret-123',
            'password_confirmation' => 'new-secret-123',
        ])->assertOk()->assertJsonPath('message', 'Your password has been changed. You can now sign in.');

        $user->refresh();
        $this->assertTrue(Hash::check('new-secret-123', $user->password));
        $this->assertSame(0, $user->tokens()->count());
        $this->assertNotNull($user->email_verified_at);

        $this->postJson('/api/v1/login', ['email' => 'ana@example.test', 'password' => 'new-secret-123'])
            ->assertOk()
            ->assertJsonStructure(['token']);

        // The token works only once.
        $this->postJson('/api/v1/reset-password', [
            'token' => $token,
            'email' => 'ana@example.test',
            'password' => 'another-123',
            'password_confirmation' => 'another-123',
        ])->assertStatus(422)->assertJsonValidationErrors('token');
    }

    public function test_reset_rejects_a_wrong_token_in_the_requested_language(): void
    {
        User::factory()->create(['email' => 'ana@example.test']);

        $this->withHeader('Accept-Language', 'hr')
            ->postJson('/api/v1/reset-password', [
                'token' => 'wrong',
                'email' => 'ana@example.test',
                'password' => 'new-secret-123',
                'password_confirmation' => 'new-secret-123',
            ])
            ->assertStatus(422)
            ->assertJsonPath('errors.token.0', 'Poveznica za ponovno postavljanje lozinke nije valjana ili je istekla.');
    }

    public function test_reset_validates_the_new_password(): void
    {
        $this->postJson('/api/v1/reset-password', [
            'token' => 'x',
            'email' => 'ana@example.test',
            'password' => 'short',
            'password_confirmation' => 'other',
        ])->assertStatus(422)->assertJsonValidationErrors('password');
    }
}
