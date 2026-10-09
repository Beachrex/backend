<?php

namespace Tests\Unit;

use App\Services\BeachFilterService;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Spatial endpoints (within-radius, bounding-box, ...) use buildRawFilters().
 * Their OpenAPI docs name the params region/island/city, while /beaches uses
 * region_code/island_code/city_code - both spellings must filter.
 */
class BeachFilterServiceRawFiltersTest extends TestCase
{
    public static function locationParams(): array
    {
        return [
            'region_code' => ['region_code', 'istria', 'reg.code IN'],
            'region'      => ['region', 'istria', 'reg.code IN'],
            'island_code' => ['island_code', 'krk', 'isl.code IN'],
            'island'      => ['island', 'krk', 'isl.code IN'],
            'city_code'   => ['city_code', 'zadar', 'city.code IN'],
            'city'        => ['city', 'zadar', 'city.code IN'],
            'country'      => ['country', 'HR', 'country_code IN'],
            'country_code' => ['country_code', 'HR', 'country_code IN'],
        ];
    }

    #[Test]
    #[DataProvider('locationParams')]
    public function location_filter_is_applied(string $param, string $value, string $expectedSql): void
    {
        $filters = app(BeachFilterService::class)
            ->buildRawFilters(Request::create('/', 'GET', [$param => $value]));

        $this->assertStringContainsString($expectedSql, $filters['sql']);
        $this->assertContains($value, $filters['bindings']);
    }

    #[Test]
    #[DataProvider('countryParams')]
    public function beaches_endpoint_filters_by_country_with_either_param_name(string $param): void
    {
        // /api/v1/beaches uses applyFilters(); the MCP server and the frontend send country_code
        $query = app(BeachFilterService::class)->applyFilters(
            \App\Models\Beach::query(),
            Request::create('/', 'GET', [$param => 'HR'])
        );

        $this->assertStringContainsString('country_code', $query->toSql());
        $this->assertContains('HR', $query->getBindings());
    }

    public static function countryParams(): array
    {
        return ['country' => ['country'], 'country_code' => ['country_code']];
    }
}
