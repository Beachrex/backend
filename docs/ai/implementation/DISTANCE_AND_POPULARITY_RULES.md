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

---

## 3. Summary

**Status**: ✅ COMPLETE

**Changes**:
- ✅ Distance only computed when location provided
- ✅ AI asks for location when needed
- ✅ Popularity queries use `beach_views` signal transparently
- ✅ AI does NOT claim verified ratings/reviews
- ✅ 3 new tests added (14 assertions)
- ✅ All 41 tests passing (216 assertions)
- ✅ Documentation updated

**Files Modified** (5):
1. `app/Services/AI/ContextBuilderService.php`
2. `app/Http/Controllers/AI/ChatController.php`
3. `tests/Feature/AI/ChatControllerTest.php`
4. `docs/ai/architecture/api-contracts.md`
5. `docs/ai/architecture/prompt-structure.md`
