<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Controllers\Api\V1\BeachController;

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
    });
