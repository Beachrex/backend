<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Controllers\Api\V1\BeachController;
use App\Http\Controllers\Api\V1\ContinentController;
use App\Http\Controllers\Api\V1\CountryController;
use App\Http\Controllers\Api\V1\RegionController;
use App\Http\Controllers\Api\V1\IslandController;
use App\Http\Controllers\Api\V1\CityController;
use App\Http\Controllers\Api\V1\PostGISController;
use App\Http\Controllers\Api\V1\WaterbodyController;
use App\Http\Controllers\Api\V1\BeachTypeController;
use App\Http\Controllers\Api\V1\AreaDescriptionController;
use App\Http\Controllers\Api\V1\AccessibilityController;
use App\Http\Controllers\Api\V1\RoadToParkingTypeController;
use App\Http\Controllers\Api\V1\ParkingCapacityController;
use App\Http\Controllers\Api\V1\ParkingBeachDistanceController;
use App\Http\Controllers\Api\V1\ParkingBeachHeightController;
use App\Http\Controllers\Api\V1\LocationLengthController;
use App\Http\Controllers\Api\V1\BayLengthController;
use App\Http\Controllers\Api\V1\BeachInclinationController;
use App\Http\Controllers\Api\V1\SwimmingDepthOfOneMController;
use App\Http\Controllers\Api\V1\OccupancyRateController;
use App\Http\Controllers\Api\V1\AccommodationAroundController;
use App\Http\Controllers\Api\V1\CulinaryOfferController;
use App\Http\Controllers\Api\V1\LocationInfrastructureController;
use App\Http\Controllers\Api\V1\NaturalLocationConditionController;
use App\Http\Controllers\Api\V1\RecommendedTransportTypeController;
use App\Http\Controllers\Api\V1\SportingActivitiesController;
use App\Http\Controllers\Api\V1\UnderwaterCharacteristicController;
use App\Http\Controllers\Api\V1\UserTypeController;
use App\Http\Controllers\Api\V1\MediaController;
use App\Http\Controllers\Api\V1\FilterController;
use App\Http\Controllers\Api\V1\FirstPageContentController;
use App\Http\Controllers\Api\V1\BeachDetailsController;
use App\Http\Controllers\Api\V1\SlugResolverController;
use App\Http\Controllers\Api\V1\SearchController;
use App\Http\Controllers\AI\TestController;

// Public OpenAPI spec endpoint (no authentication required)
Route::get('/openapi.json', function () {
    $path = storage_path('api-docs/api-docs.json');

    if (!file_exists($path)) {
        return response()->json([
            'error' => 'OpenAPI specification not found',
            'message' => 'Run: php artisan l5-swagger:generate'
        ], 404);
    }

    return response()->file($path, [
        'Content-Type' => 'application/json',
        'Access-Control-Allow-Origin' => '*', // Allow CORS for frontend
    ]);
});

Route::prefix('api/v1')
    ->middleware('api')
    ->group(function () {
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/login', [AuthController::class, 'login']);

        Route::middleware('auth:sanctum')->group(function () {
            Route::post('/logout', [AuthController::class, 'logout']);
            Route::get('/user', [UserController::class, 'show']);

            // Media routes (authenticated)
            Route::post('/media/upload', [MediaController::class, 'upload']);
            Route::put('/media/{id}', [MediaController::class, 'update']);
            Route::delete('/media/{id}', [MediaController::class, 'destroy']);
            Route::post('/media/reorder', [MediaController::class, 'reorder']);
        });

        // Public media routes
        Route::get('/media/{id}', [MediaController::class, 'show']);

        // Global search route
        Route::get('/search', [SearchController::class, 'search']);

        // Beach routes - filters, map-bounds, and most-visited must come before {id} route
        Route::get('/beaches/map-bounds', [BeachController::class, 'mapBounds']);
        Route::get('/beaches/filters', [FilterController::class, 'index']);
        Route::get('/beaches/most-visited', [BeachController::class, 'mostVisited']);
        Route::get('/beaches', [BeachController::class, 'index']);
        Route::post('/beaches', [BeachController::class, 'store']);
        Route::get('/beaches/{id}', [BeachController::class, 'show']);

        // Beach Details routes - comprehensive beach information for detail pages
        Route::get('/beach-details/{identifier}', [BeachDetailsController::class, 'show']);
        Route::get('/beach-details/{identifier}/weather', [BeachDetailsController::class, 'weather']);

        // Slug Resolver - determine entity type from slug
        Route::get('/resolve-slug/{slug}', [SlugResolverController::class, 'resolve']);

        // Continent routes
        Route::get('/continents', [ContinentController::class, 'index']);
        Route::get('/continents/{code}', [ContinentController::class, 'show']);

        // Country routes
        Route::get('/countries', [CountryController::class, 'index']);
        Route::get('/countries/{codeOrSlug}', [CountryController::class, 'show']);
        Route::get('/countries/{codeOrSlug}/weather', [CountryController::class, 'weather']);

        // Region routes
        Route::get('/regions', [RegionController::class, 'index']);
        Route::get('/regions/{slug}', [RegionController::class, 'show']);
        Route::get('/regions/{slug}/weather', [RegionController::class, 'weather']);

        // Island routes
        Route::get('/islands', [IslandController::class, 'index']);
        Route::get('/islands/{slug}', [IslandController::class, 'show']);
        Route::get('/islands/{slug}/weather', [IslandController::class, 'weather']);

        // City routes
        Route::get('/cities', [CityController::class, 'index']);
        Route::get('/cities/{slug}', [CityController::class, 'show']);
        Route::get('/cities/{slug}/weather', [CityController::class, 'weather']);

        // Waterbody routes
        Route::get('/waterbodies', [WaterbodyController::class, 'index']);
        Route::get('/waterbodies/{id}', [WaterbodyController::class, 'show']);

        // Beach Type routes
        Route::get('/beach-types', [BeachTypeController::class, 'index']);
        Route::get('/beach-types/{id}', [BeachTypeController::class, 'show']);

        // Area Description routes
        Route::get('/area-descriptions', [AreaDescriptionController::class, 'index']);
        Route::get('/area-descriptions/{id}', [AreaDescriptionController::class, 'show']);

        // Accessibility routes
        Route::get('/accessibilities', [AccessibilityController::class, 'index']);
        Route::get('/accessibilities/{id}', [AccessibilityController::class, 'show']);

        // Road to Parking Type routes
        Route::get('/road-to-parking-types', [RoadToParkingTypeController::class, 'index']);
        Route::get('/road-to-parking-types/{id}', [RoadToParkingTypeController::class, 'show']);

        // Parking Capacity routes
        Route::get('/parking-capacities', [ParkingCapacityController::class, 'index']);
        Route::get('/parking-capacities/{id}', [ParkingCapacityController::class, 'show']);

        // Parking Beach Distance routes
        Route::get('/parking-beach-distances', [ParkingBeachDistanceController::class, 'index']);
        Route::get('/parking-beach-distances/{id}', [ParkingBeachDistanceController::class, 'show']);

        // Parking Beach Height routes
        Route::get('/parking-beach-heights', [ParkingBeachHeightController::class, 'index']);
        Route::get('/parking-beach-heights/{id}', [ParkingBeachHeightController::class, 'show']);

        // Location Length routes
        Route::get('/location-lengths', [LocationLengthController::class, 'index']);
        Route::get('/location-lengths/{id}', [LocationLengthController::class, 'show']);

        // Bay Length routes
        Route::get('/bay-lengths', [BayLengthController::class, 'index']);
        Route::get('/bay-lengths/{id}', [BayLengthController::class, 'show']);

        // Beach Inclination routes
        Route::get('/beach-inclinations', [BeachInclinationController::class, 'index']);
        Route::get('/beach-inclinations/{id}', [BeachInclinationController::class, 'show']);

        // Swimming Depth routes
        Route::get('/swimming-depths', [SwimmingDepthOfOneMController::class, 'index']);
        Route::get('/swimming-depths/{id}', [SwimmingDepthOfOneMController::class, 'show']);

        // Occupancy Rate routes
        Route::get('/occupancy-rates', [OccupancyRateController::class, 'index']);
        Route::get('/occupancy-rates/{id}', [OccupancyRateController::class, 'show']);

        // Accommodation Around routes
        Route::get('/accommodation-around', [AccommodationAroundController::class, 'index']);
        Route::get('/accommodation-around/{id}', [AccommodationAroundController::class, 'show']);

        // Culinary Offer routes
        Route::get('/culinary-offers', [CulinaryOfferController::class, 'index']);
        Route::get('/culinary-offers/{id}', [CulinaryOfferController::class, 'show']);

        // Location Infrastructure routes
        Route::get('/location-infrastructure', [LocationInfrastructureController::class, 'index']);
        Route::get('/location-infrastructure/{id}', [LocationInfrastructureController::class, 'show']);

        // Natural Location Condition routes
        Route::get('/natural-location-conditions', [NaturalLocationConditionController::class, 'index']);
        Route::get('/natural-location-conditions/{id}', [NaturalLocationConditionController::class, 'show']);

        // Recommended Transport Type routes
        Route::get('/recommended-transport-types', [RecommendedTransportTypeController::class, 'index']);
        Route::get('/recommended-transport-types/{id}', [RecommendedTransportTypeController::class, 'show']);

        // Sporting Activities routes
        Route::get('/sporting-activities', [SportingActivitiesController::class, 'index']);
        Route::get('/sporting-activities/{id}', [SportingActivitiesController::class, 'show']);

        // Underwater Characteristic routes
        Route::get('/underwater-characteristics', [UnderwaterCharacteristicController::class, 'index']);
        Route::get('/underwater-characteristics/{id}', [UnderwaterCharacteristicController::class, 'show']);

        // User Type routes
        Route::get('/user-types', [UserTypeController::class, 'index']);
        Route::get('/user-types/{id}', [UserTypeController::class, 'show']);

        // First Page Content routes
        Route::get('/first-page-content', [FirstPageContentController::class, 'index']);
        Route::get('/first-page-content/{code}', [FirstPageContentController::class, 'show']);

        // PostGIS routes
        Route::prefix('spatial')->group(function () {
            Route::get('/beaches/within-radius', [PostGISController::class, 'beachesWithinRadius']);
            Route::get('/beaches/nearest', [PostGISController::class, 'nearestBeaches']);
            Route::get('/beaches/bounding-box', [PostGISController::class, 'beachesInBoundingBox']);
            Route::get('/beaches/density-grid', [PostGISController::class, 'beachDensityGrid']);
            Route::get('/beaches/statistics', [PostGISController::class, 'beachStatistics']);
            Route::get('/distance/calculate', [PostGISController::class, 'calculateDistance']);
        });

        // AI routes (with middleware stack)
        // Note: OptionalAuthMiddleware allows both anonymous and authenticated access
        Route::prefix('ai')
            ->middleware([
                \App\Http\Middleware\AI\OptionalAuthMiddleware::class,
                \App\Http\Middleware\AI\CircuitBreakerMiddleware::class,
                \App\Http\Middleware\AI\RateLimitMiddleware::class,
                \App\Http\Middleware\AI\TokenBudgetMiddleware::class,
            ])
            ->group(function () {
                // Chat endpoint (primary AI interaction)
                Route::post('/chat', [\App\Http\Controllers\AI\ChatController::class, 'chat']);

                // Test endpoint (for testing middleware stack)
                Route::post('/test', [TestController::class, 'chat']);
            });
    });
