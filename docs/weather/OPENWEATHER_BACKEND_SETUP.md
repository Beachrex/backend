# OpenWeatherMap Backend Implementation - Setup Guide

This guide explains the backend implementation for integrating OpenWeatherMap API to provide real-time weather and sea temperature data for beaches.

---

## ✅ What Was Implemented

### 1. WeatherService (`app/Services/WeatherService.php`)
- Fetches current weather data from OpenWeatherMap API
- Caches weather data for 1 hour to minimize API calls
- Provides sea temperature (approximation using air temperature)
- Handles errors gracefully (returns null on failure)

### 2. Configuration (`config/services.php`)
- Added OpenWeatherMap API key configuration
- Uses environment variable `OPENWEATHER_API_KEY`

### 3. BeachDetailsResource (`app/Http/Resources/BeachDetailsResource.php`)
- Updated `getEmbeds()` method to fetch real-time weather data
- Returns structured weather object instead of HTML widget
- Returns numeric sea temperature instead of widget URL

### 4. Swagger Documentation (`app/Swagger/Schemas/BeachDetailsSchema.php`)
- Updated embeds property to document new weather data structure
- Changed `weather` from string to object with detailed properties
- Changed `sea_temperature` from string to float

---

## 🔧 Setup Instructions

### Step 1: Get OpenWeatherMap API Key

1. Visit https://openweathermap.org/api
2. Sign up for a free account
3. Navigate to API keys section
4. Generate a new API key
5. Copy the API key

**Free Tier Limits:**
- 60 calls/minute
- 1,000,000 calls/month
- With 1-hour caching, this is more than sufficient

### Step 2: Add API Key to Environment

Add to your `.env` file:

```env
OPENWEATHER_API_KEY=your_actual_api_key_here
```

### Step 3: Regenerate Swagger Documentation

Run the following command to regenerate the API documentation:

```bash
./vendor/bin/sail artisan l5-swagger:generate
```

Or if not using Sail:

```bash
php artisan l5-swagger:generate
```

This will update the `storage/api-docs/api-docs.json` file with the new weather data structure.

---

## 🧪 Testing

### Test 1: Check WeatherService Directly

```bash
./vendor/bin/sail artisan tinker
```

```php
$service = app(\App\Services\WeatherService::class);

// Test with a beach location (e.g., Hvar, Croatia)
$weather = $service->getCurrentWeather(43.1729, 16.4411);
dd($weather);

// Expected output:
// array:17 [
//   "temperature" => 23.5
//   "feels_like" => 22.1
//   "condition" => "Clear"
//   "description" => "clear sky"
//   "icon" => "01d"
//   "icon_url" => "https://openweathermap.org/img/wn/01d@2x.png"
//   ...
// ]
```

### Test 2: Check API Endpoint

Visit a beach details endpoint:

```bash
curl http://localhost/api/v1/beach-details/your-beach-slug | jq '.data.embeds'
```

Expected response:

```json
{
  "video_url": "https://youtu.be/...",
  "webcam": null,
  "map_url": null,
  "weather": {
    "temperature": 23.5,
    "feels_like": 22.1,
    "temp_min": 21.0,
    "temp_max": 25.0,
    "pressure": 1013,
    "humidity": 65,
    "condition": "Clear",
    "description": "clear sky",
    "icon": "01d",
    "icon_url": "https://openweathermap.org/img/wn/01d@2x.png",
    "wind_speed": 3.5,
    "wind_deg": 180,
    "clouds": 20,
    "visibility": 10000,
    "sunrise": "06:30",
    "sunset": "20:15",
    "location_name": "Hvar"
  },
  "sea_temperature": 23.5
}
```

### Test 3: Verify Caching

```bash
./vendor/bin/sail artisan tinker
```

```php
use Illuminate\Support\Facades\Cache;

// Check if weather data is cached
$cacheKey = "weather:weather:43.17:16.44"; // Rounded coordinates
$cached = Cache::get($cacheKey);
dd($cached);
```

---

## 📊 API Response Changes

### Before (Old Structure)

```json
{
  "embeds": {
    "weather": "<a href=\"https://www.accuweather.com/...\">...</a>",
    "water_temperature": "https://seatemperature.info/widget/15117781684704.png"
  }
}
```

### After (New Structure)

```json
{
  "embeds": {
    "weather": {
      "temperature": 23.5,
      "feels_like": 22.1,
      "condition": "Clear",
      "description": "clear sky",
      "icon": "01d",
      "icon_url": "https://openweathermap.org/img/wn/01d@2x.png",
      "humidity": 65,
      "wind_speed": 3.5,
      "sunrise": "06:30",
      "sunset": "20:15"
    },
    "sea_temperature": 23.5
  }
}
```

---

## 🔄 Caching Strategy

### Cache Configuration

- **TTL:** 1 hour (3600 seconds)
- **Cache Key Format:** `weather:weather:{lat}:{lon}`
- **Coordinate Rounding:** 2 decimal places (~1km radius)

### How It Works

1. When a beach details request comes in, the system checks if weather data is cached
2. If cached, returns immediately (no API call)
3. If not cached, calls OpenWeatherMap API and caches the result for 1 hour
4. Nearby beaches (within ~1km) share the same cached weather data

### Benefits

- ✅ Reduces API calls to OpenWeatherMap
- ✅ Faster response times
- ✅ Stays within free tier limits
- ✅ Efficient use of resources

### Example

```php
// Beach at 43.1729, 16.4411 → Cache key: weather:weather:43.17:16.44
// Beach at 43.1745, 16.4398 → Same cache key (within 1km)
```

---

## 🚨 Error Handling

The WeatherService handles errors gracefully:

### Missing API Key
```php
// Logs warning and returns null
Log::warning('OpenWeatherMap API key not configured');
return null;
```

### API Timeout (10 seconds)
```php
// Returns null after timeout
Http::timeout(10)->get(...);
```

### API Error
```php
// Logs error and returns null
Log::error('OpenWeatherMap API error', [
    'status' => $response->status(),
    'body' => $response->body(),
]);
return null;
```

### Network Failure
```php
// Catches exception and returns null
catch (\Exception $e) {
    Log::error('Failed to fetch weather data', [
        'error' => $e->getMessage(),
    ]);
    return null;
}
```

When weather data is null, the frontend should handle it gracefully by showing placeholder values.

---

## 📝 Important Notes

### Sea Temperature Accuracy

⚠️ **Important:** OpenWeatherMap's free tier doesn't provide dedicated sea surface temperature. The `sea_temperature` field uses the general air temperature, which is a reasonable approximation for coastal areas but not 100% accurate.

For production with more accurate sea temperature data, consider:

1. **NOAA API** (free, US-focused)
2. **Stormglass.io** (paid, global coverage)
3. **World Weather Online Marine API** (paid)
4. **Store historical data** in database and update periodically

### Weather Icon Codes

OpenWeatherMap provides weather icons at:
```
https://openweathermap.org/img/wn/{icon}@2x.png
```

Common icon codes:
- `01d` - Clear sky (day)
- `01n` - Clear sky (night)
- `02d` - Few clouds (day)
- `09d` - Shower rain
- `10d` - Rain
- `11d` - Thunderstorm
- `13d` - Snow
- `50d` - Mist

Full list: https://openweathermap.org/weather-conditions

---

## 🎯 Next Steps

### 1. Frontend Implementation

After backend is deployed and Swagger is regenerated:

1. Regenerate TypeScript types in frontend:
   ```bash
   cd Front
   npm run generate-api-client
   ```

2. Update frontend components to use new weather data structure

### 2. Monitor API Usage

Check OpenWeatherMap dashboard to monitor API usage:
- https://home.openweathermap.org/api_keys

### 3. Consider Enhancements

Future improvements:
- 5-day weather forecast
- Weather alerts/warnings
- More accurate sea temperature from specialized API
- Historical weather data for trip planning

---

## ✅ Checklist

- [x] Created `WeatherService.php`
- [x] Updated `config/services.php`
- [x] Updated `BeachDetailsResource.php`
- [x] Updated `BeachDetailsSchema.php` (Swagger)
- [ ] Add `OPENWEATHER_API_KEY` to `.env`
- [ ] Regenerate Swagger documentation
- [ ] Test API endpoint
- [ ] Deploy to staging
- [ ] Update frontend to use new structure
- [ ] Deploy to production

---

## 🆘 Troubleshooting

### Weather data is always null

1. Check if API key is set in `.env`:
   ```bash
   grep OPENWEATHER_API_KEY .env
   ```

2. Check Laravel logs:
   ```bash
   tail -f storage/logs/laravel.log
   ```

3. Test API key directly:
   ```bash
   curl "https://api.openweathermap.org/data/2.5/weather?lat=43.17&lon=16.44&appid=YOUR_API_KEY&units=metric"
   ```

### API calls are slow

- Weather data is cached for 1 hour
- First request will be slower (API call)
- Subsequent requests should be fast (cached)

### Too many API calls

- Check cache is working properly
- Verify coordinate rounding (2 decimal places)
- Monitor OpenWeatherMap dashboard

---

## 📚 Resources

- OpenWeatherMap API Docs: https://openweathermap.org/api
- Weather Conditions: https://openweathermap.org/weather-conditions
- Icon List: https://openweathermap.org/weather-conditions#Icon-list
- Laravel HTTP Client: https://laravel.com/docs/http-client
- Laravel Cache: https://laravel.com/docs/cache

