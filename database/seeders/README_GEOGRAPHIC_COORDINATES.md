# Geographic Entity Coordinates Seeder

## Overview

This seeder populates latitude and longitude coordinates for cities, regions, islands, and countries by calculating the centroid (average coordinates) of all active beaches within each geographic entity.

## Prerequisites

1. Run the migration to add coordinate fields:
   ```bash
   ./vendor/bin/sail artisan migrate
   # or on server:
   php artisan migrate
   ```

2. Ensure your `beach` table has valid latitude and longitude data for beaches with `status_id = 1` (active beaches).

## Usage

### Local Development (with Sail)

```bash
./vendor/bin/sail artisan db:seed --class=GeographicEntityCoordinatesSeeder
```

### Production Server

```bash
php artisan db:seed --class=GeographicEntityCoordinatesSeeder
```

## What It Does

The seeder calculates coordinates for each geographic entity based on the average (centroid) of all active beaches within that entity:

### 1. **Cities**
- Calculates average coordinates from all beaches where `beach.city_id = city.id`
- Only includes beaches with `status_id = 1`
- Rounds coordinates to 7 decimal places (~1.1cm precision)

### 2. **Islands**
- Calculates average coordinates from all beaches where `beach.island_id = island.id`
- Only includes beaches with `status_id = 1`
- Rounds coordinates to 7 decimal places

### 3. **Regions**
- Calculates average coordinates from all beaches in cities within each region
- Joins through `city` table: `beach.city_id -> city.id -> city.region_id`
- Only includes beaches with `status_id = 1`
- Rounds coordinates to 7 decimal places

### 4. **Countries**
- Calculates average coordinates from all beaches where `beach.country_code = countries.code`
- Only includes beaches with `status_id = 1`
- Rounds coordinates to 7 decimal places

## Output

The seeder provides progress information:
```
Starting to populate geographic entity coordinates...
Populating city coordinates...
Updated 45 cities with coordinates
Populating island coordinates...
Updated 23 islands with coordinates
Populating region coordinates...
Updated 12 regions with coordinates
Populating country coordinates...
Updated 8 countries with coordinates
Geographic entity coordinates populated successfully!
```

## Re-running the Seeder

The seeder can be run multiple times safely. It will:
- Recalculate coordinates based on current beach data
- **Skip entities where coordinates are already up-to-date** (same calculated value)
- Only update entities where coordinates have changed or are missing
- Provide feedback on how many were updated vs skipped

Example output when re-running:
```
Starting to populate geographic entity coordinates...
Populating city coordinates...
Updated 3 cities with coordinates (skipped 42 already up-to-date)
Populating island coordinates...
Updated 0 islands with coordinates (skipped 23 already up-to-date)
Populating region coordinates...
Updated 1 regions with coordinates (skipped 11 already up-to-date)
Populating country coordinates...
Updated 0 countries with coordinates (skipped 8 already up-to-date)
Geographic entity coordinates populated successfully!
```

## Verification

After running the seeder, you can verify the results:

```sql
-- Check cities with coordinates
SELECT id, code, latitude, longitude 
FROM city 
WHERE latitude IS NOT NULL 
ORDER BY id;

-- Check regions with coordinates
SELECT id, code, latitude, longitude 
FROM region 
WHERE latitude IS NOT NULL 
ORDER BY id;

-- Check islands with coordinates
SELECT id, code, latitude, longitude 
FROM island 
WHERE latitude IS NOT NULL 
ORDER BY id;

-- Check countries with coordinates
SELECT id, code, latitude, longitude 
FROM countries 
WHERE latitude IS NOT NULL 
ORDER BY code;
```

## Testing Weather Endpoints

After populating coordinates, test the weather endpoints:

```bash
# City weather
curl -H "Accept-Language: en" http://localhost/api/v1/cities/split/weather

# Region weather
curl -H "Accept-Language: en" http://localhost/api/v1/regions/dalmatia/weather

# Island weather
curl -H "Accept-Language: en" http://localhost/api/v1/islands/hvar/weather

# Country weather
curl -H "Accept-Language: en" http://localhost/api/v1/countries/HR/weather
```

## Notes

- **Centroid Calculation**: The centroid is a simple average of all beach coordinates. For entities with beaches spread across large areas, this gives a representative central point.
- **Active Beaches Only**: Only beaches with `status_id = 1` are included to ensure coordinates represent currently active/published beaches.
- **Precision**: Coordinates are rounded to 7 decimal places, which provides ~1.1cm precision - more than sufficient for weather data.
- **Null Handling**: Entities without any beaches (or beaches without coordinates) will have `NULL` coordinates and will return a 400 error when weather is requested.

## Deployment Checklist

When deploying to production server:

1. ✅ Run migration: `php artisan migrate`
2. ✅ Run seeder: `php artisan db:seed --class=GeographicEntityCoordinatesSeeder`
3. ✅ Clear cache: `php artisan cache:clear`
4. ✅ Regenerate Swagger docs: `php artisan l5-swagger:generate`
5. ✅ Test weather endpoints for each entity type

