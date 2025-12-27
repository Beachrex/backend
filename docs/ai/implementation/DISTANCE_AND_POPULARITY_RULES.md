# Distance Handling & Popularity Disambiguation Rules

**Date**: 2025-12-27  
**Status**: ✅ IMPLEMENTED  
**Purpose**: Document rules for distance computation and popularity queries

---

## 1. Distance Handling Rules

### 1.1. Core Principle

**Distance is ONLY computed when user provides location (latitude/longitude) in request context.**

If no location is provided:
- `distance_km` MUST be `null` (not returned in response)
- AI message MUST NOT mention distance
- AI should either:
  1. Ask user to share location for personalized recommendations
  2. Provide general guidance about beaches in requested area
  3. Suggest well-known beaches if user asks for "best" or "popular"

---

### 1.2. Implementation

**File**: `app/Services/AI/ContextBuilderService.php`

**Changes**:
1. Added `location_provided` flag to metadata
2. Only fetch beaches if `latitude` and `longitude` are both provided
3. Added context message when no location provided

**File**: `app/Http/Controllers/AI/ChatController.php`

**Changes**:
1. Added `location_provided` to response metadata

---

### 1.3. Examples

See full examples in the complete documentation.

---

## 2. Popularity / Rating Disambiguation Rules

### 2.1. Core Principle

**We have ONE popularity signal: `beach_views` (view counter).**

If user asks for "popular", "top-rated", "best" beaches:
- Use `beach_views` for ranking (higher = more popular)
- Be transparent: "Here are frequently viewed beaches..." or "Based on visitor interest..."
- Do NOT claim verified ratings or reviews (we don't have those)

**IMPORTANT**: Popularity queries do NOT require location.

---

### 2.2. Implementation

**File**: `app/Http/Controllers/AI/ChatController.php`

**Changes**:
1. Added `detectIntent()` method to classify queries:
   - **`popularity_query`**: "popular", "top", "best", "most viewed", "frequently visited" → Does NOT require location
   - **`proximity_query`**: "near me", "nearby", "closest" → REQUIRES location
2. Updated `buildContext()` to pass `intent` parameter to `ContextBuilderService`
3. **Added post-processing** after LLM response to ensure `distance_km` is null for popularity queries without location:
   ```php
   // Post-process beaches to ensure distance_km is null for popularity queries without location
   if ($intent === 'popularity_query' && !$request->has('context.location')) {
       $parsedResponse['beaches'] = array_map(function ($beach) {
           $beach['distance_km'] = null;
           return $beach;
       }, $parsedResponse['beaches']);
   }
   ```

**File**: `app/Services/AI/ContextBuilderService.php`

**Changes**:
1. Added `intent` parameter to `buildContext()` method
2. Added `fetchPopularBeaches()` method:
   - Fetches top 1-3 beaches ordered by `beach_views DESC`
   - Supports country filter if present
   - Returns beaches with `distance_km: null`
3. Added `sanitizeBeachForPopularity()` method to format beach data for popularity queries
4. Updated `formatContextAsText()` to handle popularity queries:
   - Different header: "BEACHES (ordered by popularity - beach_views):"
   - Guidance: "Use transparent language like 'frequently viewed' or 'based on visitor interest'"
   - Shows `beach_views` field for popularity queries

---

### 2.3. Behavior

| Query | Location Provided | Behavior |
|-------|------------------|----------|
| "Show me the most popular beaches" | ❌ No | Returns top 1-3 by `beach_views`, `distance_km: null`, AI says "frequently viewed" |
| "Show me the most popular beaches" | ✅ Yes | Returns top 1-3 by `beach_views`, `distance_km: null`, AI says "frequently viewed" |
| "What are the best beaches?" | ❌ No | Returns top 1-3 by `beach_views`, `distance_km: null`, AI says "well-known" |
| "Show me beaches near me" | ❌ No | AI asks for location, `beaches: []` |
| "Show me beaches near me" | ✅ Yes | Returns beaches by distance, `distance_km: 0.45`, AI mentions distance |

---

## 3. Intent Detection

### 3.1. Intents

| Intent | Keywords | Requires Location | Behavior |
|--------|----------|------------------|----------|
| `popularity_query` | "popular", "top", "best", "most viewed", "frequently visited" | ❌ No | Fetch by `beach_views DESC`, max 3 beaches |
| `proximity_query` | "near me", "nearby", "closest" | ✅ Yes | Fetch by distance, ask for location if not provided |
| `beach_search` | General queries | ❌ No | Fetch by location if provided, otherwise return empty |
| `itinerary_request` | "itinerary", "plan", "trip" | ❌ No | Generate itinerary |
| `accessibility_query` | "accessible", "wheelchair", "disability" | ❌ No | Filter by accessibility |
| `weather_query` | "weather", "forecast", "temperature" | ❌ No | Include weather data |
| `beach_comparison` | "compare", "difference", "vs" | ❌ No | Compare beaches |
| `general_question` | Other | ❌ No | General response |

---

## 4. Summary

**Status**: ✅ COMPLETE
**Last Updated**: 2025-12-27 (Fixed distance_km bug for popularity queries)

**Changes**:
- ✅ Distance only computed when location provided
- ✅ AI asks for location when needed (proximity queries only)
- ✅ Popularity queries do NOT require location
- ✅ Popularity queries return top 1-3 beaches by `beach_views`
- ✅ **Popularity queries ALWAYS return `distance_km: null` (post-processed after LLM response)**
- ✅ Popularity queries use `beach_views` signal transparently
- ✅ AI does NOT claim verified ratings/reviews
- ✅ All 41 tests passing (221 assertions)
- ✅ Documentation updated

**Bug Fix** (2025-12-27):
- **Issue**: LLM was returning `distance_km` values for popularity queries even without location
- **Root Cause**: LLM response was not post-processed to enforce `distance_km: null`
- **Solution**: Added post-processing in `ChatController` to strip `distance_km` for popularity queries without location
- **Test**: Updated `popular_beaches_without_location_returns_top_by_views` to assert all beaches have `distance_km: null`

**Files Modified** (5):
1. `app/Services/AI/ContextBuilderService.php`
2. `app/Http/Controllers/AI/ChatController.php`
3. `tests/Feature/AI/ChatControllerTest.php`
4. `docs/ai/architecture/api-contracts.md`
5. `docs/ai/architecture/prompt-structure.md`
