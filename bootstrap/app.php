<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
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

        // API, admin and docs are never indexed (any environment).
        $middleware->append(\App\Http\Middleware\NoIndex::class);

        $middleware->group('api', [
            \App\Http\Middleware\SetLocaleFromHeader::class,
            \App\Http\Middleware\ForceJsonMiddleware::class,
            \App\Http\Middleware\AddCacheHeaders::class,
        ]);

        $middleware->alias([
            'admin' => \App\Http\Middleware\EnsureUserIsAdmin::class,
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
        // 429 (rate limit), 403 (abort), 405 and other HTTP errors keep their status code and headers
        // (e.g. Retry-After); before this, the catch-all below turned them into 500.
        $exceptions->render(function (HttpExceptionInterface $e, $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                $status = $e->getStatusCode();

                return response()->json([
                    'message' => $e->getMessage() !== '' ? $e->getMessage() : (Response::$statusTexts[$status] ?? 'Error'),
                ], $status, $e->getHeaders());
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
