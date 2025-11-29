# Imagor Integration Guide

This document describes the Imagor image processing integration for BeachRex.

## Overview

Imagor is a fast, secure image processing server written in Go that uses libvips for high-performance image manipulation. It provides:

- **High Performance**: 4-8x faster than ImageMagick with better memory efficiency
- **Dynamic Image Transformations**: Resize, crop, filters, watermarks on-the-fly
- **Format Optimization**: Auto-convert to WebP/AVIF based on browser support
- **Security**: URL signature support to prevent DDoS attacks
- **CDN Integration**: Works seamlessly with DigitalOcean Spaces

## Architecture

```
┌─────────────┐      ┌──────────────┐      ┌─────────────────┐
│   Next.js   │─────▶│   Imagor     │─────▶│  DO Spaces CDN  │
│  Frontend   │      │   Service    │      │  (S3-compatible)│
└─────────────┘      └──────────────┘      └─────────────────┘
                            │
                            ▼
                     ┌──────────────┐
                     │ Laravel API  │
                     │ (ImagorService)│
                     └──────────────┘
```

## Setup

### 1. Environment Configuration

Add the following to your `.env` file:

```bash
# Imagor Configuration
IMAGOR_PORT=8001
IMAGOR_SECRET=your_random_secret_key_here
IMAGOR_URL=http://imagor:8000
IMAGOR_PUBLIC_URL=http://localhost:8001
IMAGOR_AUTO_WEBP=1
IMAGOR_AUTO_AVIF=0
IMAGOR_CACHE_HEADER_TTL=168h
IMAGOR_MAX_WIDTH=5000
IMAGOR_MAX_HEIGHT=5000
```

**Important**: Generate a strong random secret for `IMAGOR_SECRET` in production!

```bash
# Generate a random secret
openssl rand -base64 32
```

### 2. Start Imagor Service

The Imagor service is configured in `docker-compose.yml` and will start automatically with Laravel Sail:

```bash
./vendor/bin/sail up -d
```

Imagor will be available at `http://localhost:8001`

### 3. Verify Installation

Test that Imagor is running:

```bash
curl http://localhost:8001/
```

You should see the Imagor welcome page.

## Usage

### Backend (Laravel)

#### Using ImagorService

```php
use App\Services\ImagorService;

$imagor = app(ImagorService::class);

// Generate URLs with presets
$thumbnail = $imagor->thumbnail('img/beaches/hr-102-o-pag/image.jpg');
$small = $imagor->small('img/beaches/hr-102-o-pag/image.jpg');
$medium = $imagor->medium('img/beaches/hr-102-o-pag/image.jpg');
$large = $imagor->large('img/beaches/hr-102-o-pag/image.jpg');
$hero = $imagor->hero('img/beaches/hr-102-o-pag/image.jpg');

// Custom transformations
$url = $imagor->url('img/beaches/hr-102-o-pag/image.jpg', [
    'width' => 800,
    'height' => 600,
    'fit_in' => true,
    'smart' => true,
    'filters' => [
        'quality' => 90,
        'blur' => 5,
        'watermark' => ['logo.png', 10, 10, 50],
    ],
]);
```

#### Using Media Model

```php
$media = Media::find(1);

// Get Imagor URLs
$thumbnail = $media->imagorThumbnail();
$small = $media->imagorSmall();
$medium = $media->imagorMedium();
$large = $media->imagorLarge();
$hero = $media->imagorHero();

// Custom transformations
$url = $media->imagorUrl([
    'width' => 1000,
    'height' => 750,
    'smart' => true,
]);
```

#### API Endpoints

The following API endpoints are available:

```
POST /api/v1/imagor/url
GET  /api/v1/imagor/presets
POST /api/v1/imagor/preset/{preset}
POST /api/v1/imagor/batch
```

Example request:

```bash
curl -X POST http://localhost/api/v1/imagor/url \
  -H "Content-Type: application/json" \
  -d '{
    "image_path": "img/beaches/hr-102-o-pag/image.jpg",
    "width": 800,
    "height": 600,
    "fit_in": true,
    "smart": true
  }'
```

### Frontend (Next.js)

#### Using Imagor Utilities

```tsx
import { imagor, imagorMedium, imagorThumbnail } from '@/lib/utils/imagor';
import Image from 'next/image';

// Using helper functions
<Image 
  src={imagorMedium('img/beaches/hr-102-o-pag/image.jpg')} 
  alt="Beach"
  width={800}
  height={600}
/>

// Using the service directly
<Image 
  src={imagor.url('img/beaches/hr-102-o-pag/image.jpg', {
    width: 1200,
    height: 800,
    smart: true,
    filters: { quality: 90 }
  })} 
  alt="Beach"
  width={1200}
  height={800}
/>

// Thumbnail
<Image 
  src={imagorThumbnail('img/beaches/hr-102-o-pag/image.jpg', 150)} 
  alt="Beach thumbnail"
  width={150}
  height={150}
/>
```

#### Environment Variables

Add to your Next.js `.env.local`:

```bash
NEXT_PUBLIC_IMAGOR_URL=http://localhost:8001
NEXT_PUBLIC_IMAGOR_SECRET=your_secret_key_here
```

Update `src/env.ts`:

```typescript
client: {
  NEXT_PUBLIC_CDN_ENDPOINT: z.string().url(),
  NEXT_PUBLIC_API_ENDPOINT: z.string().url().optional(),
  NEXT_PUBLIC_IMAGOR_URL: z.string().url().optional(),
  NEXT_PUBLIC_IMAGOR_SECRET: z.string().optional(),
},

runtimeEnv: {
  // ... existing vars
  NEXT_PUBLIC_IMAGOR_URL: process.env.NEXT_PUBLIC_IMAGOR_URL,
  NEXT_PUBLIC_IMAGOR_SECRET: process.env.NEXT_PUBLIC_IMAGOR_SECRET,
},
```

## Available Presets

| Preset | Width | Height | Options |
|--------|-------|--------|---------|
| `thumbnail` | 150 | 150 | fit-in, smart crop, quality 85 |
| `small` | 400 | 300 | fit-in, quality 85 |
| `medium` | 800 | 600 | fit-in, quality 90 |
| `large` | 1200 | 900 | fit-in, quality 90 |
| `hero` | 1920 | 600 | smart crop, quality 90 |
| `card` | 600 | 400 | smart crop, quality 85 |

## Transformation Options

### Resize Options

- `width`: Target width in pixels
- `height`: Target height in pixels
- `fit_in`: Resize to fit within dimensions (maintains aspect ratio)
- `smart`: Use smart cropping (detects focal points)

### Alignment

- `halign`: Horizontal alignment (`left`, `right`, `center`)
- `valign`: Vertical alignment (`top`, `bottom`, `middle`)

### Filters

Common filters:

- `quality`: JPEG/WebP quality (1-100)
- `blur`: Blur radius
- `sharpen`: Sharpen amount
- `grayscale`: Convert to grayscale
- `brightness`: Adjust brightness (-100 to 100)
- `contrast`: Adjust contrast (-100 to 100)
- `watermark`: Add watermark image

Example:

```php
$url = $imagor->url('image.jpg', [
    'width' => 800,
    'filters' => [
        'quality' => 90,
        'sharpen' => 2,
        'brightness' => 10,
    ],
]);
```

## Performance Optimization

### 1. Auto Format Conversion

Imagor automatically serves WebP or AVIF if the browser supports it (when `IMAGOR_AUTO_WEBP=1`). This can reduce file sizes by 25-35%.

### 2. Result Caching

Processed images are cached to DigitalOcean Spaces at `public/imagor-cache/`. This prevents re-processing the same image.

### 3. CDN Integration

Imagor loads original images from DigitalOcean Spaces and caches results back to Spaces, leveraging the CDN for fast delivery.

### 4. Browser Caching

Set appropriate cache headers in production:

```bash
IMAGOR_CACHE_HEADER_TTL=168h  # 7 days
```

## Security

### URL Signing

In production, always use signed URLs to prevent abuse:

1. Set a strong `IMAGOR_SECRET` in `.env`
2. Use the `ImagorService` which automatically signs URLs
3. Never expose the secret to the frontend

### Unsafe URLs (Development Only)

For development, you can use unsigned URLs:

```php
$url = $imagor->unsafeUrl('image.jpg', ['width' => 800]);
```

**Warning**: Never use unsafe URLs in production!

## Troubleshooting

### Imagor not starting

Check Docker logs:

```bash
./vendor/bin/sail logs imagor
```

### Images not loading

1. Verify DigitalOcean Spaces credentials in `.env`
2. Check that images exist in Spaces
3. Verify CORS settings on Spaces bucket
4. Check Imagor logs for errors

### Slow image processing

1. Ensure libvips is properly installed in the Imagor container
2. Check available memory (libvips is memory-efficient but still needs resources)
3. Consider increasing cache TTL to reduce re-processing

## Production Deployment

### 1. Update Environment Variables

```bash
IMAGOR_SECRET=<strong-random-secret>
IMAGOR_URL=http://imagor:8000  # Internal Docker URL
IMAGOR_PUBLIC_URL=https://images.beachrex.com  # Public CDN URL
IMAGOR_AUTO_WEBP=1
IMAGOR_AUTO_AVIF=1  # Enable AVIF if supported
```

### 2. Configure CDN

Point a subdomain (e.g., `images.beachrex.com`) to your Imagor service or use a CDN in front of it.

### 3. Enable HTTPS

Use a reverse proxy (nginx, Caddy) or load balancer with SSL termination.

### 4. Monitor Performance

- Set up monitoring for Imagor service
- Track cache hit rates
- Monitor memory usage
- Set up alerts for errors

## Resources

- [Imagor GitHub](https://github.com/cshum/imagor)
- [Imagor Documentation](https://github.com/cshum/imagor/blob/master/README.md)
- [libvips Documentation](https://www.libvips.org/API/current/)
- [DigitalOcean Spaces](https://www.digitalocean.com/products/spaces)

