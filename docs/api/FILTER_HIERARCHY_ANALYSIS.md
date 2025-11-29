# Filter Hierarchy Analysis - Dependent Filters

## Current Database Structure

### ✅ FULLY MAPPED - Can be implemented immediately:

#### 1. **Country → Regions**
- **Relationship**: `Country.hasMany(Region)` via `country_code`
- **Status**: ✅ Fully mapped
- **Query**: `Region::where('country_code', $countryCode)->active()`
- **Example**: Select "Croatia" → Get all regions in Croatia

#### 2. **Country → Cities**
- **Relationship**: `City.belongsTo(Country)` via `country_code`
- **Status**: ✅ Fully mapped
- **Query**: `City::where('country_code', $countryCode)->active()`
- **Example**: Select "Croatia" → Get all cities in Croatia

#### 3. **Region → Cities**
- **Relationship**: `City.belongsTo(Region)` via `region_id`
- **Status**: ✅ Fully mapped
- **Query**: `City::where('region_id', $regionId)->active()`
- **Example**: Select "Dalmatia" → Get all cities in Dalmatia

#### 4. **Island → Beaches**
- **Relationship**: `Beach.belongsTo(Island)` via `island_id`
- **Status**: ✅ Fully mapped
- **Query**: `Beach::where('island_id', $islandId)->where('status_id', 1)`
- **Example**: Select "Brac" → Get all beaches on Brac

---

### ⚠️ PARTIALLY MAPPED - Needs work:

#### 5. **Country → Islands**
- **Current Status**: ❌ NOT MAPPED
- **Problem**: Island table has NO `country_id` or `country_code` field
- **Solution Options**:
  - **Option A**: Add `country_code` to island table (migration needed)
  - **Option B**: Query through beaches: `Beach::where('country_code', $countryCode)->distinct('island_id')`
  - **Option C**: Create pivot table `country_island` (more complex)

#### 6. **Region → Islands**
- **Current Status**: ❌ NOT MAPPED
- **Problem**: Island table has NO `region_id` field
- **Solution Options**:
  - **Option A**: Add `region_id` to island table (migration needed)
  - **Option B**: Query through beaches: `Beach::where('region_id', $regionId)->distinct('island_id')`
  - **Option C**: Create pivot table `region_island` (more complex)

#### 7. **Island → Regions**
- **Current Status**: ❌ NOT MAPPED
- **Problem**: Island table has NO `region_id` field
- **Solution**: Same as above

#### 8. **Island → Cities**
- **Current Status**: ❌ NOT MAPPED
- **Problem**: City table has NO `island_id` field
- **Solution Options**:
  - **Option A**: Add `island_id` to city table (migration needed)
  - **Option B**: Query through beaches: `Beach::where('island_id', $islandId)->distinct('city_id')`

---

## Recommended Implementation Strategy

### Phase 1: Implement Fully Mapped Filters (EASY - No DB changes)
1. **Country → Regions** ✅
2. **Country → Cities** ✅
3. **Region → Cities** ✅
4. **Island → Beaches** ✅

### Phase 2: Add Missing Foreign Keys (MEDIUM - DB migration)
Add these columns to enable direct relationships:
- `island.country_code` (nullable)
- `island.region_id` (nullable)
- `city.island_id` (nullable)

### Phase 3: Query Through Beaches (ALTERNATIVE - No DB changes)
If you don't want to modify tables, query through beaches:
```php
// Get islands in a country
$islandIds = Beach::where('country_code', $countryCode)
    ->where('status_id', 1)
    ->distinct('island_id')
    ->pluck('island_id');
$islands = Island::whereIn('id', $islandIds)->active();

// Get cities on an island
$cityIds = Beach::where('island_id', $islandId)
    ->where('status_id', 1)
    ->distinct('city_id')
    ->pluck('city_id');
$cities = City::whereIn('id', $cityIds)->active();
```

---

## Current Beach Model Foreign Keys

Beach table has direct access to:
- `country_code` → Country
- `region_id` → Region
- `city_id` → City
- `island_id` → Island

This means you can always query beaches by any of these, but the reverse relationships (Island→Country, Island→Region, etc.) are missing.

---

## Recommendation

**Best approach**: Implement Phase 1 immediately (no DB changes), then decide on Phase 2 or 3 based on your needs.

Would you like me to:
1. Create the dependent filter API endpoint?
2. Add the missing foreign keys to island and city tables?
3. Implement query-through-beaches approach?

