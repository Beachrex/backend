# Adding Weather Endpoints to Other Entity Types

## Overview

The weather functionality is designed to be easily extended to other entity types (regions, cities, islands, countries) using the `ProvidesWeather` trait.

---

## 🎯 Architecture

### Reusable Components

1. **`WeatherService`** - Fetches weather from OpenWeatherMap (already created)
2. **`ProvidesWeather` trait** - Provides weather methods to controllers (already created)
3. **Pattern:** Nested endpoints under entity details (e.g., `/region-details/{id}/weather`)

### What You Need

- Entity must have `latitude` and `longitude` fields
- Entity must have a details controller (e.g., `RegionDetailsController`)
- Entity must have translations with `slug` field

---

## 📝 Implementation Steps

### Example: Adding Weather to Regions

#### Step 1: Update RegionDetailsController

```php
<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Traits\CacheAware;
use App\Traits\ProvidesWeather; // Add this
use Illuminate\Http\JsonResponse; // Add this

class RegionDetailsController extends Controller
{
    use CacheAware, ProvidesWeather; // Add ProvidesWeather trait

    // ... existing methods ...

    /**
     * @OA\Get(
     *     path="/api/v1/region-details/{identifier}/weather",
     *     operationId="getRegionWeather",
     *     summary="Get current weather for a region",
     *     description="Returns real-time weather data from OpenWeatherMap for a specific region. Weather data is cached for 1 hour.",
     *     tags={"Region Details"},
     *     @OA\Parameter(
     *         name="identifier",
     *         in="path",
     *         required=true,
     *         description="Region ID or slug",
     *         @OA\Schema(
     *             oneOf={
     *                 @OA\Schema(type="integer", example=1),
     *                 @OA\Schema(type="string", example="dalmatia")
     *             }
     *         )
     *     ),
     *     @OA\Parameter(ref="#/components/parameters/AcceptLanguage"),
     *     @OA\Response(
     *         response=200,
     *         description="Weather data retrieved successfully",
     *         @OA\JsonContent(ref="#/components/schemas/BeachWeatherResponse")
     *     ),
     *     @OA\Response(response=404, description="Region not found"),
     *     @OA\Response(response=400, description="Region has no coordinates"),
     *     @OA\Response(response=503, description="Weather service unavailable")
     * )
     */
    public function weather($identifier): JsonResponse
    {
        // Find region by ID or slug (use your existing method)
        $region = $this->findRegionByIdentifier($identifier);

        if (!$region) {
            return response()->json([
                'success' => false,
                'message' => 'Region not found'
            ], 404);
        }

        // Get region slug in current locale (use your existing method)
        $slug = $this->getRegionSlug($region);

        // Use ProvidesWeather trait to build response
        return $this->buildWeatherResponse($region, 'region', $slug);
    }

    // Helper method to get region slug (if not already exists)
    private function getRegionSlug($region): ?string
    {
        $locale = app()->getLocale();
        
        $translation = $region->translations->firstWhere('locale', $locale);
        if ($translation) {
            return $translation->slug;
        }

        // Fallback to English
        $translationEn = $region->translations->firstWhere('locale', 'en');
        return $translationEn?->slug;
    }
}
```

#### Step 2: Add Route

In `routes/api.php`:

```php
// Region Details routes
Route::get('/region-details/{identifier}', [RegionDetailsController::class, 'show']);
Route::get('/region-details/{identifier}/weather', [RegionDetailsController::class, 'weather']);
```

#### Step 3: Test

```bash
# Test by slug
curl http://localhost/api/v1/region-details/dalmatia/weather | jq '.'

# Test by ID
curl http://localhost/api/v1/region-details/1/weather | jq '.'
```

---

## 🔄 Same Pattern for Other Entities

### Cities

```php
// CityDetailsController.php
use ProvidesWeather;

public function weather($identifier): JsonResponse
{
    $city = $this->findCityByIdentifier($identifier);
    if (!$city) {
        return response()->json(['success' => false, 'message' => 'City not found'], 404);
    }
    
    return $this->buildWeatherResponse($city, 'city', $this->getCitySlug($city));
}
```

```php
// routes/api.php
Route::get('/city-details/{identifier}/weather', [CityDetailsController::class, 'weather']);
```

### Islands

```php
// IslandDetailsController.php
use ProvidesWeather;

public function weather($identifier): JsonResponse
{
    $island = $this->findIslandByIdentifier($identifier);
    if (!$island) {
        return response()->json(['success' => false, 'message' => 'Island not found'], 404);
    }
    
    return $this->buildWeatherResponse($island, 'island', $this->getIslandSlug($island));
}
```

```php
// routes/api.php
Route::get('/island-details/{identifier}/weather', [IslandDetailsController::class, 'weather']);
```

---

## 📊 Response Format

All entity weather endpoints return the same format:

```json
{
  "success": true,
  "data": {
    "{entity_type}_id": 1,
    "{entity_type}_slug": "entity-slug",
    "coordinates": {
      "latitude": "43.1729",
      "longitude": "16.4411"
    },
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
    "sea_temperature": 23.5,
    "cached_at": "2025-11-29T10:17:50+00:00"
  }
}
```

**Example for region:**
- `region_id`, `region_slug`

**Example for city:**
- `city_id`, `city_slug`

---

## 🎨 Frontend Integration

### React Query Example

```tsx
// Reusable hook for any entity type
function useEntityWeather(entityType: 'beach' | 'region' | 'city' | 'island', slug: string) {
  return useQuery({
    queryKey: [`${entityType}-weather`, slug],
    queryFn: () => 
      fetch(`/api/v1/${entityType}-details/${slug}/weather`)
        .then(r => r.json()),
    staleTime: 60 * 60 * 1000, // 1 hour
    refetchInterval: 60 * 60 * 1000,
  });
}

// Usage
const { data: beachWeather } = useEntityWeather('beach', 'paradise-beach');
const { data: regionWeather } = useEntityWeather('region', 'dalmatia');
const { data: cityWeather } = useEntityWeather('city', 'split');
```

---

## ✅ Checklist for Adding Weather to New Entity

- [ ] Entity has `latitude` and `longitude` fields in database
- [ ] Entity has translations with `slug` field
- [ ] Entity has a details controller (e.g., `RegionDetailsController`)
- [ ] Add `use ProvidesWeather;` to controller
- [ ] Add `use Illuminate\Http\JsonResponse;` import
- [ ] Implement `weather($identifier)` method
- [ ] Implement helper method to get entity slug (if not exists)
- [ ] Add route: `Route::get('/{entity}-details/{identifier}/weather', ...)`
- [ ] Add Swagger documentation (copy from BeachDetailsController)
- [ ] Test endpoint with slug and ID
- [ ] Regenerate Swagger: `./vendor/bin/sail artisan l5-swagger:generate`
- [ ] Update frontend to fetch weather data

---

## 🔧 Troubleshooting

### Entity has no coordinates

**Error:** 400 Bad Request - "Entity has no coordinates for weather data"

**Solution:** Ensure entity has `latitude` and `longitude` fields populated in database.

### Weather always null

**Cause:** Invalid OpenWeatherMap API key

**Solution:** Check `.env` has valid `OPENWEATHER_API_KEY`

### Different entities show same weather

**Expected behavior!** Entities within ~1km share weather cache (coordinate rounding to 2 decimals).

---

## 📚 Related Files

- **`app/Traits/ProvidesWeather.php`** - Reusable trait
- **`app/Services/WeatherService.php`** - Weather API service
- **`app/Http/Controllers/Api/V1/BeachDetailsController.php`** - Reference implementation
- **`WEATHER_ENDPOINT_GUIDE.md`** - Complete endpoint documentation

---

## 💡 Best Practices

1. **Always use the trait** - Don't duplicate weather logic
2. **Follow the pattern** - Nested under entity details
3. **Consistent naming** - Use entity type in response (`beach_id`, `region_id`, etc.)
4. **Error handling** - Return proper HTTP status codes (404, 400, 503)
5. **Swagger docs** - Document all endpoints
6. **Test both slug and ID** - Ensure both work

---

## 🚀 Future Enhancements

Possible future additions to weather functionality:

1. **Weather forecast** - 5-day forecast endpoint
2. **Weather alerts** - Severe weather warnings
3. **Historical weather** - Past weather data
4. **Weather statistics** - Average temperatures, rainfall, etc.
5. **Multiple locations** - Batch weather requests
6. **Weather icons** - Custom icon set matching app design

All of these can be added to `WeatherService` and exposed through the same endpoint pattern!

