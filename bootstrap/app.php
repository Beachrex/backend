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
        // Trust the host-level Nginx reverse proxy (SSL termination).
        // Without this, Laravel ignores X-Forwarded-Proto and generates
        // http:// asset URLs behind an https:// frontend (mixed content).
        $middleware->trustProxies(at: '*');

        $middleware->group('api', [
            \App\Http\Middleware\SetLocaleFromHeader::class,
            \App\Http\Middleware\ForceJsonMiddleware::class,
            \App\Http\Middleware\AddCacheHeaders::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (AuthenticationException $e, $request) {
            // Only return JSON for API requests
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'message' => 'Unauthenticated.',
                ], 401);
            }
        });
        // Validation failed
        $exceptions->render(function (ValidationException $e, $request) {
            // Only return JSON for API requests
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'message' => 'Validation failed.',
                    'errors' => $e->errors(),
                ], 422);
            }
        });
        $exceptions->render(function (NotFoundHttpException $e, $request) {
            // Only return JSON for API requests
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'message' => 'Not found.',
                ], 404);
            }
        });
        $exceptions->render(function (Throwable $e, $request) {
            // Only return JSON for API requests
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'message' => 'Server error.',
                    'error' => config('app.debug') ? $e->getMessage() : null,
                ], 500);
            }
        });
    })->create();
