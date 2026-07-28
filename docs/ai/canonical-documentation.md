# Beachrex System - Canonical Documentation

**Version**: 1.0  
**Last Updated**: 2025-12-22  
**Purpose**: Single source of truth for AI agents working on this codebase

---

## 1. Project Overview

### What Problem the System Solves
Beachrex is a multilingual beach discovery platform that enables users to:
- Search and filter beaches by 40+ criteria (location, facilities, activities, accessibility)
- View detailed beach information with photos, videos, 360° tours, weather data
- Navigate geographic hierarchies (Continent → Country → Region/Island → City → Beach)
- Discover beaches via interactive map with spatial queries
- Access content in 19 languages with localized URLs and metadata

### Target Users
- Beach tourists planning vacations
- Local residents discovering nearby beaches
- Travel agencies researching beach destinations
- Users requiring accessibility information for beaches

### Core Value Proposition
- **Comprehensive data**: 2,445+ beaches with detailed attributes
- **Multilingual**: 19 languages (en, si, de, hr, fr, es, it, ua, ru, pl, cs, hu, bg, sk)
- **Real-time weather**: OpenWeatherMap integration with 1-hour cache
- **Spatial search**: PostGIS-powered radius and bounding box queries
- **SEO-optimized**: Localized sitemaps, JSON-LD, hreflang tags

### What the System is NOT
- NOT a booking platform (no reservations or payments)
- NOT a social network (no user-generated content or reviews)
- NOT a real-time collaboration tool
- NOT a mobile-first app (responsive web, but no native mobile apps)

---

## 2. Business Domain Model

### Core Entities

#### Geographic Hierarchy
```
Continent (code: 2-char, e.g., "EU", "AS")
  └─ Country (code: 2-char ISO 3166-1, e.g., "HR", "SI")
      ├─ Region (id: bigint, code: string)
      ├─ Island (id: bigint, code: string)
      └─ City (id: bigint, code: string)
          └─ Beach (id: bigint)
```

**Rules**:
- Continent and Country use `code` as primary identifier (string)
- Region, Island, City, Beach use auto-increment `id` (bigint)
- All entities have `status_id` (1 = active, 2 = inactive)
- Only entities with `status_id = 1` AND active beaches are exposed via API
- Geographic entities have optional `latitude`/`longitude` for weather data

#### Beach Entity
**Table**: `beach`  
**Primary Key**: `id` (bigint, auto-increment)

**Core Fields**:
- `folder_name` (string, required): Organizes images (e.g., "hr-102-o-pag")
- `mobile_upload` (boolean): Determines image path (`img/beaches/` vs `img/beaches_mobile/`)
- `latitude`, `longitude` (decimal 10,7): GPS coordinates
- `location` (PostGIS POINT geometry, SRID 4326): Auto-generated from lat/lng via trigger
- `status_id` (tinyint): 1 = active, 2 = inactive
- `continent_code`, `country_code`, `region_id`, `island_id`, `city_id`: Geographic hierarchy

**Relationships**:
- Belongs to: Continent (code), Country (code), Region (id), Island (id), City (id)
- Has many: Translations, Media, Badges, Articles
- Many-to-many: BeachFacilities, SportingActivities, NaturalLocationConditions, etc.

**Constraints**:
- Beach MUST have `latitude` and `longitude` to appear on map
- Beach MUST have at least one translation (preferably English)
- Beach MUST have `folder_name` for media organization

#### Translation Model
**Pattern**: Separate translation tables for each entity

**Tables**:
- `beach_translation` (beach_id, locale, name, description, slug)
- `country_translation` (country_code, locale, name, full_name, slug, filter_title)
- `region_translation` (region_id, locale, name, slug, filter_title)
- `island_translation` (island_id, locale, name, slug)
- `city_translation` (city_id, locale, name, slug)

**Rules**:
- Unique constraint: `(entity_id, locale)`
- Locale codes: 2-char (en, sl, de, hr, fr, es, it, ua, ru, pl, cs, hu, bg, sk, da, pt, nl, el, sq)
- **IMPORTANT**: Frontend uses `si` for Slovenian, backend uses `sl`
  - Mapping handled in `app/Http/Middleware/SetLocaleFromHeader.php`
- English (`en`) is fallback locale
- Slug MUST be unique per locale per entity type

#### Media Model
**Table**: `media`  
**Polymorphic**: `mediable_type`, `mediable_id`

**Collections** (via `media_collections` table):
- `beach_gallery`: Main beach photos
- `beach_videos`: Video URLs
- `beach_360_sphere`: 360° tour (stored as `folder_name/vtour/tour.html`)

**Fields**:
- `file_path` (string): Relative path (e.g., `public/img/beaches/hr-102-o-pag/image.jpg`)
- `disk` (string): Storage disk (`spaces` = DigitalOcean Spaces)
- `is_primary` (boolean): Primary image for entity
- `order` (integer): Display order
- `status_id` (tinyint): 1 = active

**URL Generation**:
- **Admin panel**: Returns full DigitalOcean Spaces URL
- **API**: Returns relative path (frontend constructs CDN URLs)

**CDN Strategy** (Frontend):
- ASCII paths: Use Imagor optimization endpoints
- Non-ASCII paths (č, š, ž): Use direct DigitalOcean Spaces URL (Imagor doesn't support UTF-8)
- Three endpoints: `NEXT_PUBLIC_CDN_TEASER`, `NEXT_PUBLIC_CDN_LARGE`, `NEXT_PUBLIC_CDN_ASSETS`

### Business Rules

#### Beach Visibility
A beach is visible in API responses if:
1. `status_id = 1` (active)
2. Has at least one translation
3. Has valid `latitude` and `longitude` (for map features)

#### Geographic Entity Visibility
Countries, Regions, Islands, Cities are visible if:
1. `status_id = 1` (active)
2. Has at least one active beach (`beaches.status_id = 1`)

#### Filter Cascading
When filtering beaches:
- Geographic filters are cumulative (country + region + city)
- Attribute filters are cumulative (beach_type + surface + shade)
- All filters support both `code` (string) and `id` (integer)
- Filters accept comma-separated values (e.g., `country=HR,SI,IT`)

#### Navigation Hierarchy
Beach detail page navigation (prev/next):
1. If beaches exist in same city → navigate within city
2. Else if beaches exist in same island → navigate within island
3. Else if beaches exist in same region → navigate within region
4. Else navigate within country

**Implementation**: `app/Services/PostGISService.php::getNavigationBeaches()`

### Edge Cases

#### Missing Translations
- If translation missing for requested locale → fallback to English (`en`)
- If English missing → fallback to first available translation
- If no translations → entity excluded from API response

#### Special Characters in Filenames
- Filenames with NFD-normalized characters (č, š, ž) → use direct CDN URL
- ASCII-only filenames → use Imagor optimization
- **NEVER** use `encodeURIComponent` or NFD normalization (causes double-encoding)

**Implementation**: `src/lib/utils/beach-images.ts::buildCdnUrl()`

#### Coordinate Rounding for Weather Cache
- Weather cache key rounds coordinates to 2 decimal places
- Beaches within ~1km share same weather cache
- Cache TTL: 1 hour

**Implementation**: `app/Services/WeatherService.php::getCacheKey()`

---

## 3. System Architecture

### Frontend (Next.js 14)

**Location**: `/Users/davidtacer/Documents/Development/Beachrex/Front`

#### Responsibilities
- Server-side rendering (SSR) for SEO-critical pages
- Client-side interactivity (map, filters, search)
- Localized routing and metadata generation
- Image optimization via Imagor CDN
- React Query caching and prefetching

#### Routing Model
**Pattern**: App Router with dynamic segments

**Structure**:
```
/[locale]/                                    # Homepage
/[locale]/beaches/[continent]                 # Continent page
/[locale]/beaches/[continent]/[country]       # Country page
/[locale]/beaches/[continent]/[country]/[...segments]  # Catch-all for region/island/city/beach
/[locale]/beach-map                           # Interactive map
```

**Localized Paths**:
- `beaches` → `plaze` (si), `strande` (de), `plages` (fr), etc.
- Middleware rewrites localized paths to English folder structure
- **File**: `src/middleware.ts`

**Dynamic Segment Resolution**:
1. Check if last segment is beach slug → render beach detail page
2. Else resolve entity type via `/api/v1/resolve-slug/{slug}`
3. Render appropriate listing page (region/island/city)

**File**: `src/app/[locale]/beaches/[continent]/[country]/[...segments]/page.tsx`

#### State Management
- **React Query**: Server state (API data)
- **React Context**: Locale, API client configuration
- **Component State**: UI interactions (modals, filters, map viewport)

**No global state library** (Redux, Zustand, etc.)

#### Data Fetching Strategy

**SSR Pages** (SEO-critical):
- Continent, Country, Beach detail pages
- Fetch data in `generateMetadata()` and page component
- ISR revalidation: 3600 seconds (1 hour)

**Client-side** (interactive):
- Map, filters, search
- React Query with aggressive caching:
  - `staleTime`: 5 minutes (map data)
  - `gcTime`: 10 minutes
  - `refetchOnWindowFocus`: false

**Prefetching**:
- Hover on cards → prefetch destination data
- Debounced 200ms to avoid excessive requests
- **File**: `src/lib/prefetch-utils.ts`

#### SEO and Localization

**Metadata Generation**:
- `generateMetadata()` in each page component
- Includes: title, description, canonical URL, hreflang, OpenGraph, Twitter Card
- **Helper**: `src/lib/utils/metadata-helpers.ts::buildCompleteMetadata()`

**JSON-LD Structured Data**:
- Organization schema (root layout)
- WebSite schema with SearchAction
- TouristAttraction schema (beach pages)
- BreadcrumbList schema (all pages)
- **Helper**: `src/lib/utils/json-ld-helpers.ts`

**Sitemaps**:
- Sitemap index: `/sitemap.xml`
- Static pages: `/sitemap/static.xml` (84 URLs)
- Beaches: `/sitemap/beaches.xml` (~34,230 URLs)
- Countries, Regions, Cities, Islands: Separate sitemaps
- **Files**: `src/app/sitemap.ts`, `src/app/sitemap/*/route.ts`

**Robots.txt**:
- Production: Allow all
- Dev/staging: Disallow all
- **File**: `src/app/robots.ts`

**Translation System**:
- `next-intl` library
- Translation files: `messages/{locale}.json`
- Server components: `getTranslations({ locale, namespace })`
- Client components: `useTranslations(namespace)`
- **RULE**: ALL user-facing text MUST use translations (never hardcode English)

### Backend (Laravel 11 API)

**Location**: `/Users/davidtacer/Documents/Development/Beachrex/Api` (assumed, not in workspace)

#### Controllers vs Services

**Controllers** (`app/Http/Controllers/Api/V1/`):
- Handle HTTP requests/responses
- Validate input
- Call services for business logic
- Return JSON responses with standard format:
  ```json
  {
    "success": true,
    "data": { ... },
    "message": "Optional message"
  }
  ```

**Services** (`app/Services/`):
- `PostGISService`: Spatial queries (radius, bounding box, navigation)
- `BeachFilterService`: Apply 40+ filters to beach queries
- `WeatherService`: Fetch weather from OpenWeatherMap
- `CacheService`: Centralized cache TTL constants
- `MediaService`: Handle file uploads to DigitalOcean Spaces

**Resources** (`app/Http/Resources/`):
- Transform Eloquent models to API responses
- `BeachResource`: List view (minimal fields)
- `BeachDetailsResource`: Detail view (full data with relationships)

#### Caching Strategy

**TTL Constants** (`app/Services/CacheService.php`):
- `TRANSLATION_TTL`: 30 days (2,592,000 seconds)
- `REFERENCE_DATA_TTL`: 30 days (countries, regions, filters)
- `BEACH_COUNT_TTL`: 30 days
- `BEACH_DETAILS_TTL`: 8 hours (28,800 seconds)
- `SHORT_TTL`: 8 hours (Meilisearch)

**Cache Keys**:
- Include locale: `waterbodies:index:{locale}:all`
- Include filters: `beaches:bounding-box:{locale}:{hash}`
- Weather: `weather:weather:{lat}:{lon}` (rounded to 2 decimals)

**Cache Invalidation**:
- Model events (created, updated, deleted) clear related caches
- **Trait**: `app/Traits/Cacheable.php`

**Headers**:
- `X-Cache-Status`: HIT | MISS | BYPASS
- `X-Cache-Key`: Cache key used
- `X-Response-Time`: Response time in ms
- `X-Locale`: Locale used

#### Search Integration (Meilisearch)

**Indexed Models**:
- Beach, Country, Region, Island, City

**Index Names**:
- `beaches`, `countries`, `regions`, `islands`, `cities`

**Searchable Fields**:
- Beach: name, description, country_name, region_name, city_name, island_name
- Country: name
- Region: name, country_name
- Island: name, country_name, region_name
- City: name, country_name, region_name, island_name

**Filterable Attributes**:
- Beach: country_code, region_name, city_name, island_name, type
- Country: type
- Region: country_code, type
- Island: country_code, region_name, type
- City: country_code, region_name, type

**Indexing Rules** (`shouldBeSearchable()`):
- Beach: `status_id = 1`
- Country: Has active beaches
- Region/Island/City: `status_id = 1` AND has active beaches

**Endpoint**: `/api/v1/search?q={query}&country_code={code}&type={type}&limit={limit}`

**Configuration**: `config/scout.php`

#### Weather Integration (OpenWeatherMap)

**API**: OpenWeatherMap Current Weather API  
**Cache**: 1 hour (3600 seconds)  
**Coordinate Rounding**: 2 decimal places (~1km radius shares cache)

**Endpoints**:
- `/api/v1/beach-details/{id}/weather`
- `/api/v1/countries/{code}/weather`
- `/api/v1/regions/{slug}/weather`
- `/api/v1/islands/{slug}/weather`
- `/api/v1/cities/{slug}/weather`

**Response Format**:
```json
{
  "success": true,
  "data": {
    "beach_id": 123,
    "coordinates": { "latitude": 44.34, "longitude": 15.11 },
    "weather": {
      "temperature": 23.5,
      "feels_like": 22.8,
      "condition": "Clear",
      "description": "clear sky",
      "icon_url": "https://openweathermap.org/img/wn/01d@2x.png",
      "wind_speed": 3.5,
      "humidity": 65
    },
    "sea_temperature": 23.5
  }
}
```

**Error Handling**:
- Missing coordinates → 400 Bad Request
- Weather API failure → 503 Service Unavailable
- Returns `null` weather data (frontend shows placeholder)

**Implementation**: `app/Services/WeatherService.php`, `app/Traits/ProvidesWeather.php`

#### Spatial Queries (PostGIS)

**Extension**: PostGIS (PostgreSQL)  
**SRID**: 4326 (WGS 84)  
**Geometry Column**: `beach.location` (POINT)

**Trigger**: Auto-updates `location` from `latitude`/`longitude`  
**Index**: GIST index on `location` column

**Queries**:
1. **Within Radius**: `ST_DWithin()` with meters in SRID 3857 (Web Mercator)
2. **Bounding Box**: `latitude BETWEEN min_lat AND max_lat AND longitude BETWEEN min_lon AND max_lon`
3. **Nearest Beaches**: `ST_Distance()` with `ORDER BY distance LIMIT N`
4. **Navigation**: Find prev/next beach in same city/island/region using spatial distance

**Endpoints**:
- `/api/v1/spatial/beaches/within-radius`
- `/api/v1/spatial/beaches/bounding-box`
- `/api/v1/spatial/beaches/nearest`

**Implementation**: `app/Services/PostGISService.php`

### Admin Panel (Filament)

**URL**: `https://dock.beachrex.com/admin` (production), `http://localhost/admin` (local)  
**Framework**: Filament 3.x

#### Purpose
- Manage beaches, countries, regions, islands, cities
- Upload and organize media (images, videos)
- Manage translations for all entities
- Manage reference data (beach types, facilities, activities)
- Manage badges and articles

#### Allowed Operations
- CRUD for all entities
- Bulk actions (delete, change status)
- Media upload to DigitalOcean Spaces
- Translation management via relation managers
- Global search (Cmd+K / Ctrl+K)

#### Constraints
- Authentication required (Laravel Sanctum)
- No public access
- Media uploads limited to 10MB per file
- Image editor with aspect ratio presets (16:9, 4:3, 1:1)
- Translations managed via separate relation manager tabs

**Resources**: `app/Filament/BrAdmin/Resources/`

---

## 4. Data Flow

### Global Search Lifecycle

1. **User Input**: Types query in search box
2. **Debounce**: 300ms delay before API call
3. **API Request**: `GET /api/v1/search?q={query}&limit=10`
4. **Meilisearch**: Searches across 5 indexes (beaches, countries, regions, islands, cities)
5. **Response**: Grouped results by entity type
6. **Cache**: 8 hours (SHORT_TTL)
7. **Frontend**: Displays results with icons and entity type labels
8. **Navigation**: Click result → navigate to entity detail page

**Files**:
- Frontend: `src/components/Header/GlobalSearch.tsx`
- Backend: `app/Http/Controllers/Api/V1/SearchController.php`

### Beach Listing Lifecycle

1. **User Navigation**: Clicks continent/country/region/island/city
2. **SSR**: Server fetches data via API
3. **API Request**: `GET /api/v1/beaches?country=HR&limit=20`
4. **Filter Application**: `BeachFilterService` applies filters
5. **Query Execution**: Eloquent query with eager loading (translations, media, country)
6. **Resource Transformation**: `BeachResource` formats response
7. **Cache**: 24 hours for most-visited, 30 days for reference data
8. **Response**: JSON with beaches array and metadata
9. **Frontend**: Renders beach cards with lazy-loaded images
10. **Prefetch**: Hover on card → prefetch beach details

**Files**:
- Frontend: `src/components/DynamicSearchBeaches.tsx`
- Backend: `app/Http/Controllers/Api/V1/BeachController.php`

### Beach Detail Lifecycle

1. **User Click**: Clicks beach card or URL
2. **SSR**: Server fetches beach details
3. **API Request**: `GET /api/v1/beach-details/{slug}`
4. **Query**: Fetch beach with all relationships (badges, articles, media, navigation)
5. **Navigation**: PostGIS query for prev/next beaches
6. **Resource**: `BeachDetailsResource` with full data
7. **Cache**: 8 hours (BEACH_DETAILS_TTL)
8. **Metadata**: Generate SEO tags (title, description, JSON-LD, hreflang)
9. **Weather**: Separate API call `GET /api/v1/beach-details/{slug}/weather`
10. **Weather Cache**: 1 hour
11. **Frontend**: Renders beach detail page with gallery, map, weather widget

**Files**:
- Frontend: `src/app/[locale]/beaches/[continent]/[country]/[...segments]/page.tsx`
- Backend: `app/Http/Controllers/Api/V1/BeachDetailsController.php`

### Geo Hierarchy Navigation Lifecycle

1. **User Click**: Clicks breadcrumb or destination card
2. **URL Resolution**: Middleware rewrites localized path to English
3. **Slug Resolution**: `GET /api/v1/resolve-slug/{slug}` determines entity type
4. **Entity Fetch**: Fetch country/region/island/city details
5. **Child Entities**: Fetch regions/islands/cities for that parent
6. **Most Visited**: Fetch top 20 beaches for that entity
7. **SSR**: Render page with all data
8. **Metadata**: Generate localized SEO tags
9. **Frontend**: Display banner, destinations carousel, beach grid

**Files**:
- Frontend: `src/app/[locale]/beaches/[continent]/[country]/page.tsx`
- Backend: `app/Http/Controllers/Api/V1/SlugResolverController.php`

---

## 5. Technical Conventions

### Folder Structure Logic

**Frontend** (`src/`):
```
app/[locale]/           # App Router pages (SSR)
components/             # React components (organized by feature)
hooks/                  # Custom React hooks
lib/
  api-client/           # Auto-generated OpenAPI client
  utils/                # Utility functions
  constants/            # Constants
  mappers/              # Data transformation
i18n/                   # Internationalization config
messages/               # Translation JSON files
types/                  # TypeScript type definitions
styles/                 # Global CSS
```

**Backend** (`app/`):
```
Http/
  Controllers/Api/V1/   # API controllers
  Resources/            # API response transformers
  Middleware/           # Request middleware
Models/                 # Eloquent models
Services/               # Business logic services
Traits/                 # Reusable traits
Filament/BrAdmin/       # Admin panel resources
Swagger/                # OpenAPI annotations
```

### Naming Conventions

**API Endpoints**:
- Plural nouns: `/beaches`, `/countries`, `/regions`
- Kebab-case: `/beach-details`, `/most-visited`
- Versioned: `/api/v1/`

**Database Tables**:
- Singular: `beach`, `country`, `region`, `city`, `island`
- Translation tables: `{entity}_translation`
- Pivot tables: `badge_region`, `beach_facility`

**Models**:
- Singular PascalCase: `Beach`, `Country`, `Region`
- Translation models: `BeachTranslation`, `CountryTranslation`

**Frontend Components**:
- PascalCase: `BeachCard`, `PropertySection`
- Hooks: `use` prefix: `useBeaches`, `useCountryDetails`
- Dynamic imports: `Dynamic` prefix: `DynamicPropertyCard`

**Translation Keys**:
- camelCase: `beachTitle`, `searchPlaceholder`
- Namespaced: `SEO.beachTitle`, `HomePage.heroTitle`

### API Standards

**Request Headers**:
- `Accept-Language`: Locale code (en, si, de, etc.)
- `Authorization`: Bearer token (for protected endpoints)

**Response Format**:
```json
{
  "success": true,
  "data": { ... } | [ ... ],
  "message": "Optional message",
  "meta": {
    "total": 100,
    "per_page": 20,
    "current_page": 1
  }
}
```

**Error Format**:
```json
{
  "success": false,
  "message": "Error message",
  "errors": {
    "field": ["Validation error"]
  }
}
```

**Status Codes**:
- 200: Success
- 201: Created
- 400: Bad Request (missing coordinates, invalid input)
- 401: Unauthorized
- 404: Not Found
- 422: Validation Failed
- 500: Server Error
- 503: Service Unavailable (weather API down)

### Validation Rules

**Beach Creation**:
- `latitude`: required, numeric, between -90 and 90
- `longitude`: required, numeric, between -180 and 180
- `translations`: required, array, min 1
- `translations.*.locale`: required, string, size 2
- `translations.*.name`: required, string, max 255

**Filter Parameters**:
- Accept both `code` (string) and `id` (integer)
- Comma-separated values: `country=HR,SI,IT`
- Boolean filters: `has_swimming_pool=true`

### Error Handling Format

**Backend** (`bootstrap/app.php`):
- `AuthenticationException` → 401 JSON
- `ValidationException` → 422 JSON with errors
- `NotFoundHttpException` → 404 JSON
- `Throwable` → 500 JSON (with error message if debug mode)

**Frontend**:
- React Query handles retries (1 retry with 1s delay)
- Error boundaries for component crashes
- Fallback images for broken image URLs
- Placeholder values for missing weather data

---

## 6. Key Design Decisions

### Decisions that Must NOT Be Changed Lightly

1. **Geographic Hierarchy Model**
   - Continent/Country use `code` as primary key
   - Region/Island/City/Beach use auto-increment `id`
   - **Reason**: Countries have stable ISO codes; other entities don't
   - **Impact**: Changing this breaks all foreign keys and API contracts

2. **Translation Table Pattern**
   - Separate translation tables (not JSON columns)
   - **Reason**: Query performance, indexing, data integrity
   - **Impact**: Changing to JSON breaks admin panel, API resources, search

3. **Media Path Strategy**
   - API returns relative paths, frontend constructs CDN URLs
   - **Reason**: Flexibility to change CDN providers without backend changes
   - **Impact**: Changing this breaks all image URLs across frontend

4. **Locale Mapping (si ↔ sl)**
   - Frontend uses `si`, backend uses `sl` for Slovenian
   - **Reason**: Historical decision, frontend already deployed
   - **Impact**: Changing breaks all Slovenian URLs and translations

5. **PostGIS Trigger for Location Column**
   - Auto-updates `location` from `latitude`/`longitude`
   - **Reason**: Ensures spatial index stays in sync
   - **Impact**: Removing breaks all spatial queries

6. **Cache Invalidation via Model Events**
   - Models clear related caches on create/update/delete
   - **Reason**: Ensures data consistency without manual cache management
   - **Impact**: Removing causes stale data in API responses

7. **ISR Revalidation (1 hour)**
   - Static pages revalidate every 3600 seconds
   - **Reason**: Balance between freshness and build performance
   - **Impact**: Shorter = more builds, longer = stale content

### Trade-offs that Were Accepted

1. **No Real-time Updates**
   - **Trade-off**: Users see stale data for up to 1 hour (ISR) or 5 minutes (React Query)
   - **Accepted because**: Beach data changes infrequently (few times per year)
   - **Alternative rejected**: WebSockets/SSE (complexity not justified)

2. **Weather Cache (1 hour)**
   - **Trade-off**: Weather data can be up to 1 hour old
   - **Accepted because**: Stays within OpenWeatherMap free tier (60 calls/min)
   - **Alternative rejected**: Real-time weather (expensive, unnecessary)

3. **Imagor Doesn't Support UTF-8 Paths**
   - **Trade-off**: Non-ASCII filenames bypass Imagor optimization
   - **Accepted because**: Most filenames are ASCII; direct CDN URLs still work
   - **Alternative rejected**: Rename all files (breaks existing URLs)

4. **No User Authentication on Frontend**
   - **Trade-off**: No personalized features (favorites, history)
   - **Accepted because**: Core value is discovery, not personalization
   - **Alternative rejected**: User accounts (scope creep)

5. **Meilisearch Self-hosted**
   - **Trade-off**: Requires server maintenance, manual indexing
   - **Accepted because**: Full control, cost-effective, fast
   - **Alternative rejected**: Algolia (expensive for 34K+ beaches)

### Explicit Non-goals

1. **NOT a booking platform**: No reservations, payments, or availability calendars
2. **NOT user-generated content**: No reviews, ratings, or comments
3. **NOT a mobile app**: Responsive web only, no native iOS/Android
4. **NOT real-time collaboration**: No live updates, no multi-user editing
5. **NOT a CMS**: Admin panel is for data management, not content publishing
6. **NOT a social network**: No user profiles, followers, or messaging

---

## 7. Development Rules

### How New Features Must Be Added

#### Adding a New Filter

1. **Backend**:
   - Add filter parameter to `BeachFilterService::applyFilters()`
   - Support both `code` and `id` if applicable
   - Add to `$filterParams` array for cache key generation
   - Update Swagger annotations in `BeachController`

2. **Frontend**:
   - Add filter to `FilterOptions` type in `src/types/`
   - Update `LeftSidebarFilters.tsx` to render new filter
   - Add translation keys to all 14 locale files

3. **Testing**:
   - Add test case to `tests/Feature/BeachFilterTest.php`
   - Test with both `code` and `id` values
   - Test with comma-separated values

#### Adding a New Geographic Entity Type

1. **Database**:
   - Create migration for entity table (id, code, status_id, timestamps)
   - Create migration for translation table (entity_id, locale, name, slug)
   - Add foreign keys to `beach` table if needed

2. **Backend**:
   - Create Eloquent model with `Searchable` trait
   - Create translation model
   - Create controller, resource, and routes
   - Add to Meilisearch index settings in `config/scout.php`
   - Run `php artisan search:index {Model}`

3. **Frontend**:
   - Add API client methods (auto-generated from OpenAPI)
   - Create hooks (`useEntities`, `useEntityDetails`)
   - Create page component in `src/app/[locale]/beaches/...`
   - Add to sitemap generation

4. **Admin Panel**:
   - Create Filament resource in `app/Filament/BrAdmin/Resources/`
   - Add translation relation manager
   - Add media relation manager if needed

#### Adding a New Locale

1. **Backend**:
   - Add locale to `app/Http/Middleware/SetLocaleFromHeader.php::$supportedLocales`
   - Add locale mapping if needed (e.g., `si` → `sl`)
   - Seed translations for all entities in new locale

2. **Frontend**:
   - Add locale to `src/app/config/locales.ts`
   - Create `messages/{locale}.json` with all translation keys
   - Add localized route translations to `src/i18n/routing.ts`
   - Add to `languageNames` in `src/app/config/locales.ts`

3. **SEO**:
   - Add to hreflang generation in `src/lib/utils/metadata-helpers.ts`
   - Add to sitemap generation in `src/app/sitemap/*/route.ts`

### Patterns that MUST Be Followed

#### 1. Translation Pattern (Frontend)
```typescript
// ❌ WRONG
<h1>Welcome to Beachrex</h1>

// ✅ CORRECT
const t = useTranslations('HomePage');
<h1>{t('welcomeTitle')}</h1>
```

#### 2. API Error Handling (Frontend)
```typescript
// ❌ WRONG
const { data } = useQuery({ queryKey: ['beaches'], queryFn: fetchBeaches });

// ✅ CORRECT
const { data, error, isLoading } = useQuery({
  queryKey: ['beaches'],
  queryFn: fetchBeaches,
  retry: 1,
  staleTime: 5 * 60 * 1000,
});

if (error) return <ErrorPlaceholder />;
```

#### 3. Cache Invalidation (Backend)
```php
// ❌ WRONG
public function update(Request $request, Beach $beach) {
    $beach->update($request->validated());
    return response()->json(['success' => true]);
}

// ✅ CORRECT
public function update(Request $request, Beach $beach) {
    $beach->update($request->validated());
    // Cache cleared automatically via Cacheable trait
    return response()->json(['success' => true]);
}
```

#### 4. Image URL Construction (Frontend)
```typescript
// ❌ WRONG
<img src={`https://cdn.beachrex.com/${beach.main_image}`} />

// ✅ CORRECT
import { buildCdnUrl } from '@/lib/utils/beach-images';
<Image src={buildCdnUrl(beach.main_image, 'banner')} />
```

#### 5. Metadata Generation (Frontend)
```typescript
// ❌ WRONG
export const metadata = {
  title: 'Beach Details',
};

// ✅ CORRECT
export async function generateMetadata({ params }: PageProps) {
  const t = await getTranslations({ locale: params.locale, namespace: 'SEO' });
  return buildCompleteMetadata({
    title: t('beachTitle', { name: beach.name }),
    description: beach.description,
    locale: params.locale,
    path: `/beaches/${params.continent}/${params.country}/${beach.slug}`,
  });
}
```

### Anti-patterns that Are Forbidden

#### 1. Hardcoded English Text
```typescript
// ❌ FORBIDDEN
<p>Click here to view more beaches</p>

// ✅ REQUIRED
const t = useTranslations('Common');
<p>{t('viewMoreBeaches')}</p>
```

#### 2. Direct Database Queries in Controllers
```php
// ❌ FORBIDDEN
public function index() {
    $beaches = DB::table('beach')->where('status_id', 1)->get();
    return response()->json($beaches);
}

// ✅ REQUIRED
public function index() {
    $beaches = Beach::where('status_id', 1)->with('translations')->get();
    return BeachResource::collection($beaches);
}
```

#### 3. Manual Cache Key Construction
```php
// ❌ FORBIDDEN
$cacheKey = "beaches_{$locale}_{$country}";
$beaches = Cache::remember($cacheKey, 3600, fn() => ...);

// ✅ REQUIRED
$cacheKey = $this->getCacheKey('beaches', ['locale' => $locale, 'country' => $country]);
$beaches = $this->cacheRemember($cacheKey, CacheService::REFERENCE_DATA_TTL, fn() => ...);
```

#### 4. Inline Styles Instead of Tailwind
```typescript
// ❌ FORBIDDEN
<div style={{ padding: '16px', backgroundColor: '#f3f4f6' }}>

// ✅ REQUIRED
<div className="p-4 bg-gray-100">
```

#### 5. Mutating Props in Components
```typescript
// ❌ FORBIDDEN
function BeachCard({ beach }) {
  beach.name = beach.name.toUpperCase(); // Mutates prop!
  return <h2>{beach.name}</h2>;
}

// ✅ REQUIRED
function BeachCard({ beach }) {
  const displayName = beach.name?.toUpperCase() ?? '';
  return <h2>{displayName}</h2>;
}
```

---

## 8. Known Assumptions & Open Questions

### Assumptions

1. **Assumption**: Beach data changes infrequently (few times per year)
   - **Basis**: 30-day cache TTL for reference data
   - **Risk**: If beaches change daily, cache invalidation strategy fails

2. **Assumption**: Users tolerate 1-hour weather staleness
   - **Basis**: 1-hour weather cache TTL
   - **Risk**: Users expect real-time weather

3. **Assumption**: Most beach filenames are ASCII
   - **Basis**: Non-ASCII filenames bypass Imagor optimization
   - **Risk**: If most filenames have special characters, optimization is ineffective

4. **Assumption**: English is always available as fallback
   - **Basis**: Translation fallback logic
   - **Risk**: If English translations missing, fallback fails

5. **Assumption**: Beaches within 1km have similar weather
   - **Basis**: Weather cache rounds coordinates to 2 decimals
   - **Risk**: Microclimates or coastal vs inland differences

### Open Questions

1. **Question**: Should we support user-generated content (reviews, ratings)?
   - **Current**: No user-generated content
   - **Impact**: Would require authentication, moderation, spam prevention

2. **Question**: Should we add real-time availability/crowding data?
   - **Current**: Static beach data only
   - **Impact**: Would require IoT sensors or manual updates

3. **Question**: Should we support booking/reservations?
   - **Current**: Discovery only, no bookings
   - **Impact**: Would require payment processing, inventory management

4. **Question**: Should we add mobile apps (iOS/Android)?
   - **Current**: Responsive web only
   - **Impact**: Would require native development, app store management

5. **Question**: Should we add social features (favorites, sharing)?
   - **Current**: No user accounts or social features
   - **Impact**: Would require authentication, user profiles, privacy policy

6. **Question**: How to handle beaches that move (erosion, land reclamation)?
   - **Current**: Static coordinates
   - **Impact**: Would require coordinate history or periodic updates

7. **Question**: Should we support offline mode?
   - **Current**: Requires internet connection
   - **Impact**: Would require service workers, local storage, sync logic

---

## 9. Glossary

### Business Terms

- **Beach**: A coastal area with sand, pebbles, or rocks where people swim and sunbathe
- **Continent**: One of 7 major landmasses (EU, AS, AF, NA, SA, OC, AN)
- **Country**: A nation with ISO 3166-1 alpha-2 code (e.g., HR, SI, IT)
- **Region**: A geographic subdivision of a country (e.g., Istria, Dalmatia)
- **Island**: A landmass surrounded by water (e.g., Krk, Brač, Hvar)
- **City**: A populated place where beaches are located (e.g., Split, Pula)
- **Badge**: A visual indicator of beach features (e.g., "Family Friendly", "Dog Friendly")
- **Article**: Editorial content about a beach or destination
- **Facility**: Amenity available at a beach (e.g., shower, toilet, parking)
- **Activity**: Sport or recreation available at a beach (e.g., diving, surfing)

### Technical Terms

- **SSR**: Server-Side Rendering (Next.js renders HTML on server)
- **ISR**: Incremental Static Regeneration (Next.js rebuilds pages periodically)
- **CSR**: Client-Side Rendering (React renders in browser)
- **PostGIS**: PostgreSQL extension for spatial/geographic data
- **SRID**: Spatial Reference System Identifier (4326 = WGS 84)
- **Meilisearch**: Open-source search engine (alternative to Elasticsearch/Algolia)
- **Imagor**: Image optimization server (alternative to Vercel Image Optimization)
- **Filament**: Laravel admin panel framework
- **React Query**: Data fetching and caching library (TanStack Query)
- **Zod**: TypeScript schema validation library
- **OpenAPI**: API specification format (formerly Swagger)
- **JSON-LD**: Structured data format for SEO (schema.org)
- **Hreflang**: HTML tag for multilingual SEO
- **NFD**: Unicode Normalization Form D (decomposes characters like č → c + ˇ)

### Acronyms

- **API**: Application Programming Interface
- **CDN**: Content Delivery Network
- **TTL**: Time To Live (cache duration)
- **SEO**: Search Engine Optimization
- **GPS**: Global Positioning System
- **URL**: Uniform Resource Locator
- **JSON**: JavaScript Object Notation
- **HTTP**: Hypertext Transfer Protocol
- **CRUD**: Create, Read, Update, Delete
- **ORM**: Object-Relational Mapping (Eloquent)
- **JWT**: JSON Web Token (authentication)
- **CORS**: Cross-Origin Resource Sharing
- **GIST**: Generalized Search Tree (PostGIS index type)

### File Extensions

- `.tsx`: TypeScript + JSX (React components)
- `.ts`: TypeScript
- `.php`: PHP (Laravel backend)
- `.blade.php`: Laravel Blade templates
- `.json`: JSON data files
- `.md`: Markdown documentation
- `.sql`: SQL database scripts
- `.env`: Environment variables

---

**End of Canonical Documentation**

This document is the single source of truth for AI agents working on the Beachrex codebase. All decisions, patterns, and rules documented here MUST be followed unless explicitly overridden by the user.

