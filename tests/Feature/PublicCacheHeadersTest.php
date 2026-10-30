<?php

namespace Tests\Feature;

use App\Http\Middleware\PublicCacheHeaders;
use App\Models\Status;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route as RouteFacade;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * HTTP caching headers for public GETs and the opt-in header for the nginx micro-cache
 * (ADR 2026-10-30 "API performance: layered caching").
 */
class PublicCacheHeadersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Status::insert([['id' => 1, 'name' => 'active']]);
    }

    #[Test]
    public function a_public_list_gets_shared_cache_headers_and_the_micro_cache_opt_in(): void
    {
        $response = $this->getJson('/api/v1/continents', ['Accept-Language' => 'de'])->assertOk();

        $cacheControl = $response->headers->get('Cache-Control');
        foreach (['public', 'max-age=60', 's-maxage=60', 'stale-while-revalidate=60', 'stale-if-error=86400'] as $part) {
            $this->assertStringContainsString($part, $cacheControl);
        }
        $this->assertStringNotContainsString('private', $cacheControl);
        $this->assertContains('Accept-Language', $response->getVary());
        $response->assertHeader('X-Accel-Expires', '60');
        $this->assertSame('"'.md5($response->getContent()).'"', $response->headers->get('ETag'));
    }

    #[Test]
    public function a_matching_etag_gets_304_which_is_never_stored_by_nginx(): void
    {
        $etag = $this->getJson('/api/v1/continents')->headers->get('ETag');

        $response = $this->getJson('/api/v1/continents', ['If-None-Match' => $etag]);

        $response->assertStatus(304);
        $this->assertSame('', $response->getContent());
        $this->assertFalse($response->headers->has('X-Accel-Expires'));
    }

    #[Test]
    public function signed_in_requests_errors_and_other_routes_stay_private(): void
    {
        $this->getJson('/api/v1/continents', ['Authorization' => 'Bearer 1|xxxxxxxx'])
            ->assertOk()
            ->assertHeaderMissing('X-Accel-Expires');

        $notFound = $this->getJson('/api/v1/countries/zz')->assertNotFound()->assertHeaderMissing('X-Accel-Expires');
        $this->assertStringContainsString('private', $notFound->headers->get('Cache-Control'));

        $health = $this->getJson('/api/v1/health')->assertHeaderMissing('X-Accel-Expires');
        $this->assertStringNotContainsString('public', $health->headers->get('Cache-Control'));
    }

    #[Test]
    public function only_anonymous_get_routes_are_publicly_cacheable(): void
    {
        $public = collect(RouteFacade::getRoutes()->getRoutes())
            ->filter(fn (Route $route) => collect($route->gatherMiddleware())->contains(
                fn ($m) => is_string($m) && (str_starts_with($m, 'public.cache') || str_starts_with($m, PublicCacheHeaders::class))
            ));

        $this->assertGreaterThanOrEqual(20, $public->count());
        foreach ($public as $route) {
            $this->assertSame(['GET', 'HEAD'], $route->methods(), $route->uri());
            $middleware = $route->gatherMiddleware();
            $this->assertNotContains('auth:sanctum', $middleware, $route->uri());
            $this->assertNotContains('admin', $middleware, $route->uri());
            // Beach details counts a view per request: it must reach PHP every time.
            $this->assertStringNotContainsString('beach-details', $route->uri());
        }
    }
}
