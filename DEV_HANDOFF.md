# Development Handoff / Change Log

This file tracks backend changes that are important for frontend integration and future development.

---

## 2025-11-26 – Spatial API Cache Key Fixes and Error Handling

### 1. Fixed Missing Filter Parameters in Cache Key Generation

**Context**
- The spatial endpoints (`/beaches/within-radius` and `/beaches/bounding-box`) support filtering by `natural_location_condition` and `sporting_activity`.
- These filters were implemented in the query logic but were **missing** from the cache key generation in `BeachFilterService::generateCacheKeySuffix()`.
- This caused incorrect cached results to be returned when these filters were used.

**Implementation**
- **File:** `app/Services/BeachFilterService.php`
  - Added `natural_location_condition` and `sporting_activity` to the `$filterParams` array in `generateCacheKeySuffix()` method (lines 1587-1588).
  - Cache keys now properly include these filters when present in the request.

**Impact**
- Cache keys are now unique for each combination of filters, preventing incorrect cached results.
- Example cache key before: `beaches:within_radius:en:lat=46.0569:lng=14.5058:radius=50.00:country=HR`
- Example cache key after: `beaches:within_radius:en:lat=46.0569:lng=14.5058:radius=50.00:country=HR:natural_location_condition=shaded:sporting_activity=diving`

**Action Required**
- Clear Redis cache after deployment to ensure all cached data uses the new cache key structure.
- Command: `php artisan cache:clear` or `redis-cli FLUSHDB`

---

### 3. Fixed Image Paths with Leading Slashes

**Context**
- Some paths in the database and code were stored/returned with a leading slash (e.g., `/icons/filters/waterbody.svg`, `/img/beaches/...`).
- This is inconsistent with the relative path convention used throughout the application.
- All paths should be relative without leading slashes (e.g., `icons/filters/waterbody.svg`, `img/beaches/...`).

**Database Tables Affected**
- `waterbody.icon` - 4 records fixed
- `beach_type.icon` - 8 records fixed
- `user_type.icon` - 3 records fixed

**Code Files Affected**
- `app/Http/Resources/BeachResource.php` - Removed hardcoded leading slash from `main_image` path (line 64)

**Fixes Applied**

1. **Database icon paths** - Created migration to remove leading slashes:
   - **Migration file:** `database/migrations/2025_11_26_000001_remove_leading_slashes_from_icon_paths.php`
   - Updates `waterbody.icon`, `beach_type.icon`, and `user_type.icon` tables
   - Removes leading `/` from all icon paths
   - Includes rollback functionality in `down()` method

2. **BeachResource main_image** - Changed from:
   ```php
   $mainImage = '/' . ltrim($primaryMedia->file_path, '/');
   ```
   To:
   ```php
   $mainImage = $primaryMedia->file_path;
   ```

**Deployment Instructions**

To apply these fixes to development and production databases:

1. **Development:**
   ```bash
   ./vendor/bin/sail artisan migrate
   ```

2. **Production:**
   ```bash
   php artisan migrate
   ```

3. **Clear cache after deployment:**
   ```bash
   php artisan cache:clear
   ```

The migration will automatically fix all icon paths with leading slashes. If you need to rollback:
```bash
php artisan migrate:rollback --step=1
```

**Verification**
- Comprehensive scan of all database tables confirmed no paths starting with `/` remain.
- Checked 117 table/column combinations across the entire database.
- Verified all API resources return paths without leading slashes.

**Example**
- Before: `/icons/filters/waterbody.svg`, `/img/beaches/hr-6-o-hvar/hr-6-o-hvar-sl3.jpg`
- After: `icons/filters/waterbody.svg`, `img/beaches/hr-6-o-hvar/hr-6-o-hvar-sl3.jpg`

**Impact**
- All paths (media, icons) are now consistent with the relative path convention.
- Frontend can prepend base URL/CDN consistently across all paths.
- API responses for beaches, filters, and badges now return clean relative paths.

---

### 2. Added Defensive Checks for Centroid Properties

**Context**
- The spatial endpoints return centroid data (center coordinates and bounding box) calculated from the beaches found.
- When accessing cached data, if the centroid object structure changed or properties were missing, the API would throw "Undefined property: stdClass::$property" errors.
- This was particularly noticeable when accessing endpoints via browser vs curl, as browsers might retrieve stale cached data.

**Implementation**
- **File:** `app/Http/Controllers/Api/V1/PostGISController.php`
  - **Method:** `beachesInBoundingBox()` (lines 663-670)
    - Added `isset()` checks for all centroid properties: `min_lat`, `max_lat`, `min_lng`, `max_lng`, `center_lat`, `center_lng`
    - Falls back to request parameters or null if properties don't exist
  - **Method:** `beachesWithinRadius()` (lines 281-282)
    - Added `isset()` checks for `center_lat` and `center_lng`
    - Falls back to null if properties don't exist

**Example**
- Before: `$data['centroid']->min_lat` → Error if property doesn't exist
- After: `$data['centroid'] && isset($data['centroid']->min_lat) && $data['centroid']->min_lat !== null ? (float) $data['centroid']->min_lat : (float) $request->min_lat`

**Impact**
- API endpoints are now resilient to cached data with different structures
- No more "Undefined property" errors when accessing spatial endpoints
- Graceful fallback to request parameters when centroid data is unavailable

---

## 2025-11-26 – Media URLs, 360 Spheres, and Swagger Updates

### 1. Media URLs: CDN prefix removed

**Context**
- Previously, media URLs (images, videos, etc.) were returned with a full CDN/Spaces URL, e.g. `https://beachrex.fra1.digitaloceanspaces.com/public/...`.
- The requirement is now to return **relative paths only** and let the frontend prepend its own base URL/CDN.

**Implementation**
- **Model:** `app/Models/Media.php`
  - Accessors were updated so that `url` and `thumbnail_url` now return the stored `file_path` directly, *without* any domain/CDN prefix.
  - This affects all API responses that serialize `Media` models.

**Implications for frontend**
- All media-related fields (`url`, `thumbnail_url`, `main_image`, etc.) are now **relative paths** like:
  - `img/beaches/hr-6-o-hvar/hr-6-o-hvar-sl3.jpg`
- Frontend must prepend the appropriate base URL/CDN when building absolute URLs.

---

### 2. 360 Spheres (`spheres_360`) and Virtual Tour URL

**Context**
- In DB, `spheres_360` items store only the panorama image, e.g.:
  - `img/beaches/hr-6-o-hvar/hr-6-o-hvar-Panorama.jpg`
- The actual 360 experience is served from a virtual tour HTML file at:
  - `img/beaches/{folder_name}/vtour/tour.html`
- This `vtour/tour.html` path is **not stored in DB** and may not exist for all beaches.

**Implementation**
- **Resource:** `app/Http/Resources/BeachDetailsResource.php`
  - Method `getMediaCollections()` now passes the collection code for spheres:
    - `spheres_360` → `beach_360_sphere` (second argument to `formatMediaCollection`).
  - Method `formatMediaCollection($mediaItems, ?string $collectionCode = null)`:
    - If `collectionCode === 'beach_360_sphere'` **and** the beach has `folder_name` **and** the collection is not empty, it computes:
      - `vtourUrl = "img/beaches/{$this->folder_name}/vtour/tour.html"`
    - For each media item in `spheres_360`, the response now includes:
      - `tour_url = vtourUrl`
    - For all other collections, `tour_url` is explicitly `null`.

**Important behavior**
- We **do not** check file existence on Spaces for performance reasons.
- `tour_url` is derived purely from `folder_name` and collection type.
- If DB has no `spheres_360` entries, `tour_url` is not set (no spheres block or an empty array).

**Example (Beach Adam)**
- Before:
  - `spheres_360[].url` → `img/beaches/hr-6-o-hvar/hr-6-o-hvar-Panorama.jpg`
- After:
  - `spheres_360[].url` → same relative image path
  - `spheres_360[].tour_url` → `img/beaches/hr-6-o-hvar/vtour/tour.html`

**Frontend expectation**
- Use `spheres_360[*].tour_url` to open the 360 viewer.
- Use `url` / `thumbnail_url` as preview images.
- If `tour_url` 404s, handle it gracefully on the frontend (backend does not verify existence).

---

### 3. Swagger / OpenAPI Documentation Updates

The Swagger docs were updated to reflect the new behavior for media URLs and 360 spheres.

#### 3.1 Beach details – `MediaItem` and `spheres_360`

**File:** `app/Swagger/Schemas/BeachDetailsSchema.php`

- `spheres_360` description updated:
  - From: "360-degree panoramic images"
  - To:   "360-degree panoramic images and their virtual tours".

- `MediaItem` schema now documents:
  - `url` as a **relative** media path:
    - Example: `img/beaches/hr-6-o-hvar/hr-6-o-hvar-sl3.jpg`
    - Description: "Relative media path without CDN/domain prefix. Frontend should prepend its own base URL or CDN."
  - `thumbnail_url` as a **relative** thumbnail path:
    - Example: `img/beaches/hr-6-o-hvar/hr-6-o-hvar-sl3.jpg`
    - Description: "Relative thumbnail path (same as url for now)."
  - New field `tour_url`:
    - Type: `string`, `nullable: true`
    - Example: `img/beaches/hr-6-o-hvar/vtour/tour.html`
    - Description: "Virtual tour HTML path for 360 spheres (present only for spheres_360 collection)."

- Navigation `main_image` fields in `BeachDetailsSchema` are now described as:
  - Example: `img/beaches/beach-folder/image.jpg`
  - Description: "Main beach image path (relative, without CDN/domain prefix)."

#### 3.2 Generic media schema

**File:** `app/Swagger/Schemas/MediaSchema.php`

- Updated examples and descriptions for media URLs:
  - `file_name`: example `hr-6-o-hvar-sl3.jpg`.
  - `url`: example `img/beaches/hr-6-o-hvar/hr-6-o-hvar-sl3.jpg` with description that it is a **relative** path.
  - `thumbnail_url`: example `img/beaches/hr-6-o-hvar/hr-6-o-hvar-sl3.jpg` with description that it is a **relative** thumbnail path.

#### 3.3 Beach list schema

**File:** `app/Swagger/Schemas/BeachSchema.php`

- `main_image` property updated:
  - Example: `img/beaches/hr-779-k-jdalmacija/hr-779-k-jdalmacija-sl4.jpg`
  - Description: "Primary beach image path (relative, without CDN/domain prefix). Frontend should prepend its own base URL or CDN."

#### 3.4 First page content media schema

**File:** `app/Swagger/Schemas/FirstPageContentMediaSchema.php`

- `url` property updated from full CDN URL to relative path:
  - Example: `img/first-page/hero-bg.jpg`
  - Description: "Relative media path without CDN/domain prefix. Frontend should prepend its own base URL or CDN."

#### 3.5 Cleanup of legacy CDN references

- Searched `app/` for occurrences of:
  - `beachrex.fra1`
  - `cdn.example.com`
  - `CDN URL`
  - `prepend CDN`
- Updated or removed Swagger comments so they no longer state that the backend returns full CDN URLs. All documented examples now match the new **relative-path** behavior.

---

## Notes for Future Updates

- When adding new endpoints that return media, follow the same pattern:
  - Backend returns **relative paths only**.
  - Frontend is responsible for prepending base URL/CDN.
- For any new 360-related features, prefer deriving `tour_url` from the beach folder name where possible, to avoid extra DB columns and network/file existence checks.
- When modifying API responses, remember to update the corresponding Swagger schema in `app/Swagger/Schemas/*` so docs stay in sync with actual behavior.

