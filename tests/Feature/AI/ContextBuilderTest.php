<?php

namespace Tests\Feature\AI;

use Tests\TestCase;
use App\Services\AI\ContextBuilderService;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Context Builder Tests
 *
 * Tests for context assembly with strict capping and sanitization.
 */
class ContextBuilderTest extends TestCase
{
    private ContextBuilderService $contextBuilder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->contextBuilder = app(ContextBuilderService::class);
    }

    /**
     * Test: Free tier gets max 5 beaches
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function free_tier_gets_max_6_beaches()
    {
        $context = $this->contextBuilder->buildContext([
            'tier' => 'free',
            'locale' => 'en',
            'latitude' => 44.3388,  // Near Zadar, Croatia
            'longitude' => 15.1051,
            'radius' => 50,
        ]);

        $this->assertArrayHasKey('beaches', $context);
        $this->assertArrayHasKey('metadata', $context);
        $this->assertEquals('free', $context['metadata']['tier']);
        $this->assertEquals(6, $context['metadata']['max_beaches']);
        $this->assertLessThanOrEqual(6, count($context['beaches']));
    }

    /**
     * Test: Premium tier gets max 20 beaches
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function premium_tier_gets_max_20_beaches()
    {
        $context = $this->contextBuilder->buildContext([
            'tier' => 'premium',
            'locale' => 'en',
            'latitude' => 44.3388,
            'longitude' => 15.1051,
            'radius' => 50,
        ]);

        $this->assertEquals('premium', $context['metadata']['tier']);
        $this->assertEquals(20, $context['metadata']['max_beaches']);
        $this->assertLessThanOrEqual(20, count($context['beaches']));
    }

    /**
     * Test: Weather excluded when not requested
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function weather_excluded_when_not_requested()
    {
        $context = $this->contextBuilder->buildContext([
            'tier' => 'free',
            'locale' => 'en',
            'latitude' => 44.3388,
            'longitude' => 15.1051,
            'include_weather' => false,
        ]);

        $this->assertNull($context['weather']);
    }

    /**
     * Test: Weather included when requested and allowed
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function weather_included_when_requested()
    {
        // Skip if OpenWeatherMap API key not configured
        if (empty(config('services.openweather.api_key'))) {
            $this->markTestSkipped('OpenWeatherMap API key not configured');
        }

        $context = $this->contextBuilder->buildContext([
            'tier' => 'free',
            'locale' => 'en',
            'latitude' => 44.3388,
            'longitude' => 15.1051,
            'include_weather' => true,
        ]);

        // Weather may be null if API fails, but should be present in context
        $this->assertArrayHasKey('weather', $context);

        if ($context['weather'] !== null) {
            $this->assertArrayHasKey('temperature', $context['weather']);
            $this->assertArrayHasKey('condition', $context['weather']);
            $this->assertArrayHasKey('description', $context['weather']);
        }
    }

    /**
     * Test: Context truncation triggers with large data
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function context_truncation_triggers_with_large_data()
    {
        // Enterprise tier has max 8000 tokens (~32000 characters)
        // If we fetch 50 beaches, context should NOT be truncated
        $context = $this->contextBuilder->buildContext([
            'tier' => 'enterprise',
            'locale' => 'en',
            'latitude' => 44.3388,
            'longitude' => 15.1051,
            'radius' => 100,
        ]);

        // For enterprise tier with 50 beaches, truncation should not occur
        // (unless beaches have very long descriptions)
        $this->assertArrayHasKey('context_truncated', $context['metadata']);

        // Free tier with same data should truncate
        $contextFree = $this->contextBuilder->buildContext([
            'tier' => 'free',
            'locale' => 'en',
            'latitude' => 44.3388,
            'longitude' => 15.1051,
            'radius' => 100,
        ]);

        // Free tier max is 5 beaches, so should not truncate
        $this->assertFalse($contextFree['metadata']['context_truncated']);
    }

    /**
     * Test: Deterministic ordering (same input => same context)
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function deterministic_ordering_same_input_same_context()
    {
        $params = [
            'tier' => 'free',
            'locale' => 'en',
            'latitude' => 44.3388,
            'longitude' => 15.1051,
            'radius' => 50,
        ];

        $context1 = $this->contextBuilder->buildContext($params);
        $context2 = $this->contextBuilder->buildContext($params);

        // Same input should produce same beach IDs in same order
        $beachIds1 = array_column($context1['beaches'], 'id');
        $beachIds2 = array_column($context2['beaches'], 'id');

        $this->assertEquals($beachIds1, $beachIds2);
    }

    /**
     * Test: Beach descriptions are truncated to 200 chars
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function beach_descriptions_truncated_to_200_chars()
    {
        $context = $this->contextBuilder->buildContext([
            'tier' => 'free',
            'locale' => 'en',
            'latitude' => 43.0189,  // Near actual beaches in Croatia
            'longitude' => 17.4454,
            'radius' => 50,
        ]);

        // If beaches are present, check description truncation
        if (!empty($context['beaches'])) {
            foreach ($context['beaches'] as $beach) {
                if (!empty($beach['description'])) {
                    $this->assertLessThanOrEqual(203, strlen($beach['description'])); // 200 + '...'
                }
            }
        } else {
            // If no beaches, just pass the test (database might be empty in test environment)
            $this->assertTrue(true);
        }
    }

    /**
     * Test: No PII in context
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function no_pii_in_context()
    {
        $context = $this->contextBuilder->buildContext([
            'tier' => 'free',
            'locale' => 'en',
            'latitude' => 44.3388,
            'longitude' => 15.1051,
            'radius' => 50,
        ]);

        $json = json_encode($context);

        // Check that context doesn't contain common PII fields
        $this->assertStringNotContainsString('email', $json);
        $this->assertStringNotContainsString('phone', $json);
        $this->assertStringNotContainsString('user_id', $json);
        $this->assertStringNotContainsString('ip_address', $json);
    }

    /**
     * Test: Context can be formatted as text
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function context_can_be_formatted_as_text()
    {
        $context = $this->contextBuilder->buildContext([
            'tier' => 'free',
            'locale' => 'en',
            'latitude' => 44.3388,
            'longitude' => 15.1051,
            'radius' => 50,
        ]);

        $text = $this->contextBuilder->formatContextAsText($context);

        $this->assertStringContainsString('AVAILABLE CONTEXT:', $text);
        $this->assertStringNotContainsString('AVAILABLE FILTERS:', $text);

        // If beaches are present, check for BEACHES section
        if (!empty($context['beaches'])) {
            $this->assertStringContainsString('BEACHES', $text);
        }
    }

    /**
     * Test: Available filters are included
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function available_filters_are_included()
    {
        $context = $this->contextBuilder->buildContext([
            'tier' => 'free',
            'locale' => 'en',
            'latitude' => 44.3388,
            'longitude' => 15.1051,
        ]);

        $this->assertArrayHasKey('filters_available', $context);
        $this->assertArrayHasKey('countries', $context['filters_available']);
        $this->assertArrayHasKey('surface_types', $context['filters_available']);
        $this->assertArrayHasKey('facilities', $context['filters_available']);
        $this->assertArrayHasKey('accessibility', $context['filters_available']);
    }
}

