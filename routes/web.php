<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Http;

Route::get('/', function () {
    return view('welcome');
});

// Imagor proxy route - proxies image transformation requests to Imagor service
// Usage: https://dev-api.beachrex.com/imagor/unsafe/400x300/img/beaches/...
Route::get('/imagor/{path}', function ($path) {
    $imagorUrl = config('imagor.url'); // http://imagor:8000
    $fullPath = $path . (request()->getQueryString() ? '?' . request()->getQueryString() : '');

    try {
        $response = Http::timeout(60)
            ->withHeaders([
                'Accept' => request()->header('Accept', 'image/*'),
            ])
            ->get("{$imagorUrl}/{$fullPath}");

        return response($response->body(), $response->status())
            ->header('Content-Type', $response->header('Content-Type'))
            ->header('Cache-Control', $response->header('Cache-Control') ?? 'public, max-age=604800')
            ->header('Access-Control-Allow-Origin', '*');

    } catch (\Exception $e) {
        return response()->json([
            'error' => 'Image processing failed',
            'message' => $e->getMessage()
        ], 500);
    }
})->where('path', '.*'); // Match any path including slashes
