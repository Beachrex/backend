<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(function () {
        require base_path('routes/web.php');
        require base_path('routes/api.php');
        require base_path('routes/console.php');
    })
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->group('api', [
            \App\Http\Middleware\SetLocaleFromHeader::class,
            \App\Http\Middleware\ForceJsonMiddleware::class,
            \App\Http\Middleware\AddCacheHeaders::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (AuthenticationException $e, $request) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        });
        // Validation failed
        $exceptions->render(function (ValidationException $e, $request) {
            return response()->json([
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        });
        $exceptions->render(function (NotFoundHttpException $e, $request) {
            return response()->json([
                'message' => 'Not found.',
            ], 404);
        });
        $exceptions->render(function (Throwable $e, $request) {
            return response()->json([
                'message' => 'Server error.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        });
    })->create();
