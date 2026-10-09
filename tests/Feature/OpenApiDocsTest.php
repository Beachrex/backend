<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * The web client and the app generate their API clients from storage/api-docs/api-docs.json. On 2026-10-09 the
 * generator had been failing quietly (swagger-php 5.8 rejects the schemas that config/l5-swagger.php scanned
 * twice), so the committed file lost /app-config, /me/favorites and /ai/itinerary-plans. This test fails when
 * generation errors or when the committed file misses an endpoint the routes define.
 */
class OpenApiDocsTest extends TestCase
{
    #[\PHPUnit\Framework\Attributes\Test]
    public function the_committed_docs_are_generated_without_errors_and_up_to_date(): void
    {
        $file = storage_path('api-docs/api-docs.json');
        $raw = (string) file_get_contents($file);
        $committed = json_decode($raw, true);

        $this->assertSame(0, Artisan::call('l5-swagger:generate'));
        $generated = json_decode((string) file_get_contents($file), true);
        file_put_contents($file, $raw); // leave the repo file exactly as it was

        $this->assertSame(
            array_keys($generated['paths']),
            array_keys($committed['paths']),
            'storage/api-docs/api-docs.json is out of date: run php artisan l5-swagger:generate and commit it'
        );
        foreach (['/api/v1/app-config', '/api/v1/me/favorites', '/api/v1/ai/itinerary-plans', '/api/v1/payments/products'] as $path) {
            $this->assertArrayHasKey($path, $committed['paths']);
        }
    }
}
