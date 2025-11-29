# Beach Weather Endpoint - Complete Guide

## Overview

The `/api/v1/beach-details/{identifier}/weather` endpoint provides real-time weather data for beaches with **independent 1-hour caching**, separate from beach details cache.

**Pattern:** Nested under entity details (e.g., `/beach-details/{id}/weather`, `/region-details/{id}/weather`) - follows existing API structure and is easily extensible to other entity types.

---

## 🎯 Why a Nested Weather Endpoint?

### The Problem

Previously, weather data was embedded in beach details response:
- **Beach details cache:** Long TTL (hours/days) - beach data rarely changes
- **Weather data:** Should update frequently (every hour)
- **Conflict:** Embedding weather in beach details meant stale weather data for hours

### The Solution

**Nested endpoint with independent caching:**
- Beach details: `/api/v1/beach-details/{slug}` - cached for hours/days
- Weather data: `/api/v1/beach-details/{slug}/weather` - cached for 1 hour
- Frontend fetches them independently
- **Follows existing API pattern** - consistent with `/beach-details`, `/region-details`, etc.
- **Easily extensible** - same pattern works for regions, cities, islands

---

## 📡 Endpoint Details

### URL
```
GET /api/v1/beach-details/{identifier}/weather
```

### Future Endpoints (Same Pattern)
```
GET /api/v1/region-details/{identifier}/weather
GET /api/v1/city-details/{identifier}/weather
GET /api/v1/island-details/{identifier}/weather
```

### Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `identifier` | string/int | Yes | Beach ID (numeric) or slug (any language) |

### Headers

| Header | Value | Required | Description |
|--------|-------|----------|-------------|
| `Accept-Language` | `en`, `hr`, `de`, etc. | No | Locale for beach slug in response |

---

## 📥 Request Examples

### By Beach Slug
```bash
curl http://localhost/api/v1/beach-details/beach-livade-i-obatnice/weather
```

### By Beach ID
```bash
curl http://localhost/api/v1/beach-details/1/weather
```

### With Locale
```bash
curl -H "Accept-Language: hr" http://localhost/api/v1/beach-details/beach-livade-i-obatnice/weather
```

---

## 📤 Response Examples

### Success Response (200)

```json
{
  "success": true,
  "data": {
    "beach_id": 1,
    "beach_slug": "beach-livade-i-obatnice",
    "coordinates": {
      "latitude": "44.33880000",
      "longitude": "15.10510000"
    },
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
    "sea_temperature": 23.5,
    "cached_at": "2025-11-29T10:30:00+00:00"
  }
}
```

### Beach Not Found (404)

```json
{
  "success": false,
  "message": "Beach not found"
}
```

### Beach Has No Coordinates (400)

```json
{
  "success": false,
  "message": "Beach has no coordinates for weather data",
  "data": {
    "beach_id": 123,
    "beach_slug": "some-beach",
    "weather": null,
    "sea_temperature": null
  }
}
```

### Weather Service Unavailable (503)

```json
{
  "success": false,
  "message": "Weather data temporarily unavailable",
  "data": {
    "beach_id": 1,
    "beach_slug": "beach-livade-i-obatnice",
    "coordinates": {
      "latitude": "44.33880000",
      "longitude": "15.10510000"
    },
    "weather": null,
    "sea_temperature": null
  }
}
```

---

## 🔄 Caching Strategy

### Cache Configuration

- **TTL:** 1 hour (3600 seconds)
- **Cache Key:** `weather:weather:{lat}:{lon}` (rounded to 2 decimals)
- **Scope:** WeatherService level (not endpoint level)

### How It Works

1. Request comes to `/beach-details/1/weather`
2. BeachDetailsController's `weather()` method handles request
3. Finds beach with ID 1 using `findBeachByIdentifier()`
4. Extracts coordinates: `44.33880000, 15.10510000`
5. Calls `ProvidesWeather` trait's `buildWeatherResponse()` method
6. Trait calls `WeatherService::getCurrentWeather(44.33880000, 15.10510000)`
7. WeatherService rounds coordinates: `44.34, 15.11`
8. Checks cache: `weather:weather:44.34:15.11`
9. If cached → returns immediately
10. If not cached → calls OpenWeatherMap API → caches for 1 hour

### Cache Sharing

Beaches within ~1km share the same weather cache:
- Beach A at `44.338, 15.105` → Cache key: `weather:weather:44.34:15.11`
- Beach B at `44.342, 15.108` → Same cache key: `weather:weather:44.34:15.11`

**Result:** Efficient API usage, stays within free tier limits.

---

## 🎨 Frontend Integration

### React Query Example

```tsx
// Fetch beach details (long cache)
const { data: beachDetails } = useQuery({
  queryKey: ['beach', slug],
  queryFn: () => fetch(`/api/v1/beach-details/${slug}`).then(r => r.json()),
  staleTime: 24 * 60 * 60 * 1000, // 24 hours
});

// Fetch weather separately (1-hour cache)
const { data: weatherData } = useQuery({
  queryKey: ['beach-weather', slug],
  queryFn: () => fetch(`/api/v1/beach-details/${slug}/weather`).then(r => r.json()),
  staleTime: 60 * 60 * 1000, // 1 hour
  refetchInterval: 60 * 60 * 1000, // Auto-refresh every hour
});

// Use in components
<WeatherWidget weather={weatherData?.data?.weather} />
<SeaTempWidget tempC={weatherData?.data?.sea_temperature} />
```

### Vanilla JavaScript Example

```javascript
async function loadBeachPage(slug) {
  // Load beach details (cached for 24 hours)
  const beachDetails = await fetch(`/api/v1/beach-details/${slug}`)
    .then(r => r.json());
  
  // Load weather (cached for 1 hour)
  const weatherData = await fetch(`/api/v1/beach-details/${slug}/weather`)
    .then(r => r.json());
  
  if (weatherData.success) {
    displayWeather(weatherData.data.weather);
    displaySeaTemp(weatherData.data.sea_temperature);
  } else {
    displayWeatherPlaceholder();
  }
}
```

---

## 🚨 Error Handling

### Frontend Error Handling

```tsx
const { data, error, isLoading } = useQuery({
  queryKey: ['beach-weather', slug],
  queryFn: () => fetch(`/api/v1/beach-details/${slug}/weather`).then(r => {
    if (!r.ok) throw new Error('Weather unavailable');
    return r.json();
  }),
  retry: 2, // Retry twice on failure
  staleTime: 60 * 60 * 1000,
});

if (isLoading) {
  return <WeatherSkeleton />;
}

if (error || !data?.success) {
  return <WeatherPlaceholder message="Weather temporarily unavailable" />;
}

return <WeatherWidget weather={data.data.weather} />;
```

---

## 📊 Response Fields Reference

### Weather Object Fields

| Field | Type | Description | Example |
|-------|------|-------------|---------|
| `temperature` | float | Current temperature (°C) | `23.5` |
| `feels_like` | float | Feels like temperature (°C) | `22.1` |
| `temp_min` | float | Minimum temperature (°C) | `21.0` |
| `temp_max` | float | Maximum temperature (°C) | `25.0` |
| `pressure` | int | Atmospheric pressure (hPa) | `1013` |
| `humidity` | int | Humidity percentage | `65` |
| `condition` | string | Weather condition | `"Clear"` |
| `description` | string | Detailed description | `"clear sky"` |
| `icon` | string | Icon code | `"01d"` |
| `icon_url` | string | Full icon URL | `"https://..."` |
| `wind_speed` | float | Wind speed (m/s) | `3.5` |
| `wind_deg` | int | Wind direction (degrees) | `180` |
| `clouds` | int | Cloudiness (%) | `20` |
| `visibility` | int | Visibility (meters) | `10000` |
| `sunrise` | string | Sunrise time (HH:mm) | `"06:30"` |
| `sunset` | string | Sunset time (HH:mm) | `"20:15"` |
| `location_name` | string | Location name | `"Hvar"` |

---

## 🧪 Testing

### Test 1: Valid Beach with Coordinates

```bash
curl http://localhost/api/v1/beach-details/1/weather | jq '.'
```

**Expected:** 200 OK with weather data (if API key valid) or 503 (if API key invalid)

### Test 2: Beach Without Coordinates

```bash
# Assuming beach ID 999 has no coordinates
curl http://localhost/api/v1/beach-details/999/weather | jq '.'
```

**Expected:** 400 Bad Request with message "Beach has no coordinates for weather data"

### Test 3: Non-existent Beach

```bash
curl http://localhost/api/v1/beach-details/999999/weather | jq '.'
```

**Expected:** 404 Not Found with message "Beach not found"

### Test 4: Cache Verification

```bash
# First request (should call API)
time curl http://localhost/api/v1/beach-details/1/weather

# Second request (should be cached, faster)
time curl http://localhost/api/v1/beach-details/1/weather
```

**Expected:** Second request significantly faster

---

## 🔧 Configuration

### Required Environment Variables

```env
OPENWEATHER_API_KEY=your_api_key_here
```

### Get API Key

1. Visit https://openweathermap.org/api
2. Sign up for free account
3. Generate API key
4. Add to `.env` file

**Note:** New API keys can take 1-2 hours to activate.

---

## 📈 Performance Metrics

### Expected Response Times

| Scenario | Response Time |
|----------|---------------|
| Cached weather data | < 50ms |
| Fresh API call | 200-500ms |
| API timeout | 10 seconds (then returns 503) |

### API Usage Estimates

With 1-hour caching and coordinate rounding:
- **1000 beaches** → ~100 unique weather locations (10km² grid)
- **1000 requests/hour** → ~100 API calls/hour (rest served from cache)
- **Free tier limit:** 60 calls/minute, 1M calls/month
- **Conclusion:** Well within free tier limits

---

## 🎯 Best Practices

### 1. Always Handle Errors

```tsx
// ✅ Good
const weather = weatherData?.data?.weather;
if (weather) {
  displayWeather(weather);
} else {
  displayPlaceholder();
}

// ❌ Bad
const weather = weatherData.data.weather; // Can crash if null
```

### 2. Use Appropriate Cache Times

```tsx
// ✅ Good - 1 hour for weather
staleTime: 60 * 60 * 1000

// ❌ Bad - too short (excessive API calls)
staleTime: 5 * 60 * 1000 // 5 minutes

// ❌ Bad - too long (stale data)
staleTime: 24 * 60 * 60 * 1000 // 24 hours
```

### 3. Show Loading States

```tsx
// ✅ Good
if (isLoading) return <WeatherSkeleton />;
if (error) return <WeatherError />;
return <WeatherWidget data={weather} />;

// ❌ Bad
return <WeatherWidget data={weather} />; // Blank screen while loading
```

---

## 🆘 Troubleshooting

### Weather always returns 503

**Cause:** Invalid or missing API key

**Solution:**
1. Check `.env` has `OPENWEATHER_API_KEY`
2. Verify API key is valid at https://openweathermap.org
3. Wait 1-2 hours if key was just created
4. Check Laravel logs: `tail -f storage/logs/laravel.log`

### Weather data is stale

**Cause:** Cache not expiring

**Solution:**
1. Clear cache: `php artisan cache:clear`
2. Verify cache TTL is 3600 seconds in WeatherService
3. Check system time is correct

### Different beaches show same weather

**Expected behavior!** Beaches within ~1km share weather cache (coordinate rounding to 2 decimals).

---

## 📚 Related Documentation

- **Backend Setup:** `OPENWEATHER_BACKEND_SETUP.md`
- **Frontend Implementation:** `Front/OPENWEATHER_FRONTEND_IMPLEMENTATION.md`
- **Overall Summary:** `OPENWEATHER_IMPLEMENTATION_SUMMARY.md`
- **OpenWeatherMap API:** https://openweathermap.org/api

