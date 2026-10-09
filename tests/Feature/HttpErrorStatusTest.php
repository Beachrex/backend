<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Regression 2026-10-09: the API's catch-all exception renderer answered every HTTP error with 500,
 * so a rate-limited request got "500 Server error." instead of 429 with Retry-After.
 */
class HttpErrorStatusTest extends TestCase
{
    public function test_rate_limited_api_requests_get_429_with_retry_after(): void
    {
        Route::middleware(['api', 'throttle:2,1'])->get('/api/v1/_test-throttle', fn () => ['ok' => true]);

        $this->getJson('/api/v1/_test-throttle')->assertOk();
        $this->getJson('/api/v1/_test-throttle')->assertOk();
        $this->getJson('/api/v1/_test-throttle')
            ->assertStatus(429)
            ->assertHeader('Retry-After')
            ->assertJsonPath('message', 'Too Many Attempts.');
    }

    public function test_aborts_and_wrong_methods_keep_their_status(): void
    {
        Route::middleware('api')->get('/api/v1/_test-abort', fn () => abort(403));

        $this->getJson('/api/v1/_test-abort')->assertStatus(403)->assertJsonPath('message', 'Forbidden');
        $this->deleteJson('/api/v1/health')->assertStatus(405);
        $this->getJson('/api/v1/does-not-exist')->assertStatus(404)->assertJsonPath('message', 'Not found.');
    }

    public function test_real_errors_are_still_500(): void
    {
        Route::middleware('api')->get('/api/v1/_test-error', fn () => throw new \RuntimeException('boom'));

        $this->getJson('/api/v1/_test-error')->assertStatus(500)->assertJsonPath('message', 'Server error.');
    }
}
