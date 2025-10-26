# Swagger API Documentation Configuration

This document explains how to configure Swagger API documentation URLs for different environments.

## Environment-Based Server URLs

The Swagger documentation now supports multiple server environments:

### Current Configuration

The API documentation includes four server options:

1. **Local Development**: `http://localhost:8000`
2. **Development Server**: `https://dev-api.beachrex.com`
3. **Staging Server**: `https://stage-api.beachrex.com`
4. **Production Server**: `https://api.beachrex.com`

### Environment Variables

#### `.env` Configuration

```bash
# Application URL (used as fallback for Swagger)
APP_URL=http://localhost:8000

# Swagger API Documentation URL (optional - uses APP_URL if not set)
L5_SWAGGER_CONST_HOST=http://localhost:8000
```

#### Environment-Specific URLs

**Local Development:**
```bash
APP_URL=http://localhost:8000
L5_SWAGGER_CONST_HOST=http://localhost:8000
```

**Development:**
```bash
APP_URL=https://dev-api.beachrex.com
L5_SWAGGER_CONST_HOST=https://dev-api.beachrex.com
```

**Staging:**
```bash
APP_URL=https://stage-api.beachrex.com
L5_SWAGGER_CONST_HOST=https://stage-api.beachrex.com
```

**Production:**
```bash
APP_URL=https://api.beachrex.com
L5_SWAGGER_CONST_HOST=https://api.beachrex.com
```

## How It Works

### 1. Configuration Priority

The Swagger server URL is determined in this order:

1. `L5_SWAGGER_CONST_HOST` environment variable
2. `APP_URL` environment variable (fallback)
3. Default: `http://localhost:8000`

### 2. Multiple Server Support

The Swagger UI will show a dropdown with all available servers:

- **Beachrex API Server** (from environment variable)
- **Development Server** (https://dev-api.beachrex.com)
- **Staging Server** (https://stage-api.beachrex.com)
- **Production Server** (https://api.beachrex.com)

Users can switch between servers directly in the Swagger UI.

### 3. Configuration Files

**Main Configuration:** `config/l5-swagger.php`
```php
'constants' => [
    'L5_SWAGGER_CONST_HOST' => env('L5_SWAGGER_CONST_HOST', env('APP_URL', 'http://localhost:8000')),
],
```

**Swagger Annotations:** `app/Swagger/Swagger.php`
```php
/**
 * @OA\Server(
 *     url=L5_SWAGGER_CONST_HOST,
 *     description="Beachrex API Server"
 * )
 * @OA\Server(
 *     url="https://dev-api.beachrex.com",
 *     description="Development Server"
 * )
 * @OA\Server(
 *     url="https://stage-api.beachrex.com",
 *     description="Staging Server"
 * )
 * @OA\Server(
 *     url="https://api.beachrex.com",
 *     description="Production Server"
 * )
 */
```

## Regenerating Documentation

After changing environment variables or server configurations:

```bash
php artisan l5-swagger:generate
```

## Testing Different Environments

### Local Testing
```bash
# Set local environment
APP_URL=http://localhost:8000
L5_SWAGGER_CONST_HOST=http://localhost:8000

# Regenerate docs
php artisan l5-swagger:generate

# Visit: http://localhost:8000/api/documentation
```

### Development Testing
```bash
# Set development environment
APP_URL=https://dev-api.beachrex.com
L5_SWAGGER_CONST_HOST=https://dev-api.beachrex.com

# Regenerate docs
php artisan l5-swagger:generate
```

## Deployment Notes

### Laravel Cloud Deployment

The environment variables will be automatically used during deployment:

1. **Development Environment**: Uses `dev-api.beachrex.com`
2. **Staging Environment**: Uses `stage-api.beachrex.com`
3. **Production Environment**: Uses `api.beachrex.com`
4. **Local Environment**: Uses `localhost:8000`

### Manual Override

You can override the server URL by setting `L5_SWAGGER_CONST_HOST`:

```bash
# Force specific URL regardless of APP_URL
L5_SWAGGER_CONST_HOST=https://custom-api.example.com
```

## Troubleshooting

### Issue: Wrong Server URL in Swagger

**Solution:**
1. Check `.env` file for correct `APP_URL` and `L5_SWAGGER_CONST_HOST`
2. Regenerate documentation: `php artisan l5-swagger:generate`
3. Clear config cache: `php artisan config:clear`

### Issue: Server Dropdown Not Showing

**Solution:**
1. Verify multiple `@OA\Server` annotations in `app/Swagger/Swagger.php`
2. Regenerate documentation
3. Check browser cache

### Issue: API Calls Failing

**Solution:**
1. Ensure selected server URL in Swagger UI matches your actual API
2. Check CORS configuration for the selected server
3. Verify API is accessible from the selected URL

## Benefits

✅ **Environment Flexibility**: Automatically adapts to different environments
✅ **Developer Friendly**: Easy switching between local, dev, and production
✅ **No Hardcoding**: Uses environment variables for configuration
✅ **Multiple Options**: Users can choose their preferred server
✅ **Deployment Ready**: Works seamlessly with Laravel Cloud
