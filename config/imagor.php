<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Imagor URL
    |--------------------------------------------------------------------------
    |
    | The base URL for the Imagor service. This should be the internal
    | Docker service URL when running in Docker, or localhost for local dev.
    |
    */

    'url' => env('IMAGOR_URL', 'http://imagor:8000'),

    /*
    |--------------------------------------------------------------------------
    | Imagor Public URL
    |--------------------------------------------------------------------------
    |
    | The public-facing URL for Imagor. This is what gets returned to clients.
    | In production, this might be a CDN or load balancer URL.
    |
    */

    'public_url' => env('IMAGOR_PUBLIC_URL', env('IMAGOR_URL', 'http://localhost:8001')),

    /*
    |--------------------------------------------------------------------------
    | Imagor Secret
    |--------------------------------------------------------------------------
    |
    | Secret key for signing Imagor URLs. This prevents unauthorized image
    | transformations and protects against DDoS attacks.
    |
    | IMPORTANT: Generate a strong random secret for production!
    |
    */

    'secret' => env('IMAGOR_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | Signer Type
    |--------------------------------------------------------------------------
    |
    | The hashing algorithm to use for URL signatures.
    | Supported: sha1, sha256, sha512
    |
    | Note: Imagor uses sha256 by default. sha1 is for Thumbor compatibility.
    |
    */

    'signer_type' => env('IMAGOR_SIGNER_TYPE', 'sha256'),

    /*
    |--------------------------------------------------------------------------
    | Auto WebP
    |--------------------------------------------------------------------------
    |
    | Automatically convert images to WebP format if the browser supports it.
    | This can significantly reduce image file sizes.
    |
    */

    'auto_webp' => env('IMAGOR_AUTO_WEBP', true),

    /*
    |--------------------------------------------------------------------------
    | Auto AVIF
    |--------------------------------------------------------------------------
    |
    | Automatically convert images to AVIF format if the browser supports it.
    | AVIF provides even better compression than WebP but is experimental.
    |
    */

    'auto_avif' => env('IMAGOR_AUTO_AVIF', false),

    /*
    |--------------------------------------------------------------------------
    | Use Public URL
    |--------------------------------------------------------------------------
    |
    | Whether to use the public URL in generated URLs. Set to true to return
    | public-facing URLs to clients (recommended for API responses).
    | Set to false only if you need internal Docker URLs.
    |
    */

    'use_public_url' => env('IMAGOR_USE_PUBLIC_URL', true),

    /*
    |--------------------------------------------------------------------------
    | Image Presets
    |--------------------------------------------------------------------------
    |
    | Predefined image transformation presets for common use cases.
    | You can customize these or add your own presets.
    |
    */

    'presets' => [
        'thumbnail' => [
            'width' => 150,
            'height' => 150,
            'fit_in' => true,
            'smart' => true,
            'filters' => ['quality' => 85],
        ],
        'small' => [
            'width' => 400,
            'height' => 300,
            'fit_in' => true,
            'filters' => ['quality' => 85],
        ],
        'medium' => [
            'width' => 800,
            'height' => 600,
            'fit_in' => true,
            'filters' => ['quality' => 90],
        ],
        'large' => [
            'width' => 1200,
            'height' => 900,
            'fit_in' => true,
            'filters' => ['quality' => 90],
        ],
        'hero' => [
            'width' => 1920,
            'height' => 600,
            'smart' => true,
            'filters' => ['quality' => 90],
        ],
        'card' => [
            'width' => 600,
            'height' => 400,
            'smart' => true,
            'filters' => ['quality' => 85],
        ],
    ],

];

