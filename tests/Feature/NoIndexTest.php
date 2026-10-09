<?php

namespace Tests\Feature;

use App\Http\Middleware\NoIndex;
use Tests\TestCase;

/**
 * The backend domains (dev-api, uat-api, api, dock-*) must not be indexed by search engines or
 * AI crawlers (owner, 2026-10-08, TASK-004).
 */
class NoIndexTest extends TestCase
{
    public function test_api_responses_carry_the_noindex_header(): void
    {
        $this->getJson('/api/v1/health')->assertHeader('X-Robots-Tag', NoIndex::HEADER_VALUE);
    }

    public function test_error_responses_carry_it_too(): void
    {
        $this->getJson('/api/v1/no-such-route')->assertNotFound()->assertHeader('X-Robots-Tag', NoIndex::HEADER_VALUE);
    }

    public function test_robots_txt_blocks_everything(): void
    {
        $robots = file_get_contents(public_path('robots.txt'));
        $this->assertMatchesRegularExpression('/^User-agent: \*\s*\nDisallow: \/\s*$/m', $robots);
    }
}
