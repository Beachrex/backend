<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Controllers\Api\V1\BeachController;
use App\Http\Controllers\Api\V1\ContinentController;
use App\Http\Controllers\Api\V1\CountryController;
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

Route::prefix('api/v1')
    ->middleware('api')
    ->group(function () {
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/login', [AuthController::class, 'login']);

        Route::middleware('auth:sanctum')->group(function () {
            Route::post('/logout', [AuthController::class, 'logout']);
            Route::get('/user', [UserController::class, 'show']);
        });

        Route::get('/beaches', [BeachController::class, 'index']);
        Route::post('/beaches', [BeachController::class, 'store']);
        Route::get('/beaches/{id}', [BeachController::class, 'show']);

        // Continent routes
        Route::get('/continents', [ContinentController::class, 'index']);
        Route::get('/continents/{code}', [ContinentController::class, 'show']);

        // Country routes
        Route::get('/countries', [CountryController::class, 'index']);
        Route::get('/countries/{code}', [CountryController::class, 'show']);

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

        // PostGIS routes
        Route::prefix('spatial')->group(function () {
            Route::get('/beaches/within-radius', [PostGISController::class, 'beachesWithinRadius']);
            Route::get('/beaches/nearest', [PostGISController::class, 'nearestBeaches']);
            Route::get('/beaches/bounding-box', [PostGISController::class, 'beachesInBoundingBox']);
            Route::get('/beaches/density-grid', [PostGISController::class, 'beachDensityGrid']);
            Route::get('/beaches/statistics', [PostGISController::class, 'beachStatistics']);
            Route::get('/distance/calculate', [PostGISController::class, 'calculateDistance']);
        });
    });
