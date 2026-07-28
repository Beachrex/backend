# Beachrex AI Agent - Capability to Data Source Mapping

**Version**: 1.0  
**Last Updated**: 2025-12-22  
**Purpose**: Maps each AI agent capability to existing platform data sources

---

## Capability 1: Natural Language Search

### Description
Translate conversational queries into structured filters and return matching beaches.

### Required Data Sources

#### API Endpoints
- **Primary**: `GET /api/v1/beaches` (BeachController::index)
  - Supports 40+ filter parameters
  - Returns paginated beach list with translations, media, geographic entities
  - Cache TTL: Varies by filter combination (typically 8-24 hours)
  
- **Filters Metadata**: `GET /api/v1/beaches/filters` (FilterController::index)
  - Returns all available filter options with translations
  - Cache TTL: 30 days (REFERENCE_DATA_TTL)

- **Reference Data Endpoints** (for filter value lookup):
  - `GET /api/v1/beach-types` - Beach type options
  - `GET /api/v1/waterbodies` - Waterbody options
  - `GET /api/v1/accessibility` - Accessibility options
  - `GET /api/v1/beach-facilities` - Facility options
  - `GET /api/v1/sporting-activities` - Activity options
  - `GET /api/v1/user-types` - User type options (family, couple, solo, etc.)
  - `GET /api/v1/occupancy-rates` - Occupancy level options
  - All cache TTL: 30 days

#### Database Tables (Read-Only)
- **Core**: `beach` (2,445+ records, status_id=1)
- **Translations**: `beach_translation` (locale-specific names, descriptions, slugs)
- **Geographic**: `countries`, `region`, `island`, `city`, `continents`
- **Reference Data**: 
  - `beach_type`, `waterbody`, `accessibility`, `beach_facility`
  - `sporting_activities`, `user_type`, `occupancy_rate`
  - `surface`, `shade`, `parking`, `parking_capacity`, `parking_beach_height`
  - `area_description`, `beach_inclination`, `swimming_depth_of_one_m`
  - `accommodation_around`, `culinary_offer`, `location_infrastructure`
  - `natural_location_condition`, `underwater_characteristic`
- **Pivot Tables** (many-to-many):
  - `beach_facility_beach`, `sporting_activities_beach`, `user_type_beach`
  - `accommodation_around_beach`, `culinary_offer_beach`
  - `location_infrastructure_beach`, `natural_location_condition_beach`
  - `underwater_characteristic_beach`

#### Meilisearch Indexes
- **Index**: `beaches`
  - Searchable: name, description, country_name, region_name, city_name, island_name
  - Filterable: country_code, region_name, city_name, island_name, type
  - Sortable: name
  - Records: Only beaches with status_id=1

### Access Type
- **Read-Only**: All queries are SELECT statements
- **No Writes**: Agent cannot modify beach data or filters

### Latency Tolerance
- **Real-Time**: < 2 seconds expected response time
- **Caching**: Aggressive caching (8-30 days) acceptable for reference data
- **Search**: Meilisearch provides sub-100ms search latency

### Caching Constraints
- Beach list queries: Cache key includes locale + all filter parameters
- Reference data: 30-day cache (CacheService::REFERENCE_DATA_TTL)
- Filter metadata: 30-day cache
- Cache invalidation: Automatic on beach create/update/delete (via Cacheable trait)

### Security Implications
- **Public Data**: All beach data is public (no authentication required)
- **Input Validation**: All filter parameters validated in BeachFilterService
- **SQL Injection**: Protected by Eloquent ORM (no raw queries)
- **Rate Limiting**: Apply per-tier limits (Free: 10/hour, Explorer: 100/hour, Pro: 500/hour)

---

## Capability 2: Personalized Recommendations

### Description
Suggest beaches based on user profile, constraints, and preferences using AI-powered ranking.

### Required Data Sources

#### API Endpoints
- **Primary**: `GET /api/v1/beaches` (with multiple filters)
  - Combine filters: user_type + beach_facility + accessibility + occupancy_rate
  - Example: user_type=family, beach_facility=playground,shallow_water, accessibility=wheelchair
  
- **Most Visited**: `GET /api/v1/beaches/most-visited`
  - Returns top beaches by beach_views counter
  - Supports geographic filtering (continent, country, region, island, city)
  - Cache TTL: 24 hours
  - Max limit: 100 beaches

#### Database Tables (Read-Only)
- **Core**: `beach` (all fields including beach_views counter)
- **Derived Data**:
  - Facility count: COUNT of related beach_facility records
  - Activity count: COUNT of related sporting_activities records
  - Accessibility score: Derived from accessibility pivot table
  - Safety indicators: lifeguard, first_aid facilities

#### Meilisearch Indexes
- Not primary for recommendations (used for initial search only)
- Recommendations use database queries with complex filtering

### Access Type
- **Read-Only**: SELECT queries with aggregations (COUNT, AVG)
- **Derived**: Agent calculates scores based on multiple attributes

### Latency Tolerance
- **Near Real-Time**: < 3 seconds acceptable (more complex queries)
- **Async Acceptable**: For itinerary generation or multi-beach comparisons

### Caching Constraints
- Recommendation results: Cache per user profile hash (e.g., "family_wheelchair_parking")
- Cache TTL: 8 hours (BEACH_DETAILS_TTL)
- Invalidation: On beach updates affecting recommendation criteria

### Security Implications
- **No User Profiles**: Recommendations based on session data only (ephemeral)
- **No PII**: No personal information stored or logged
- **Privacy**: User preferences not persisted beyond session

---

## Capability 3: Multi-Criteria Comparison

### Description
Compare 2-5 beaches side-by-side across facilities, accessibility, weather, occupancy.

### Required Data Sources

#### API Endpoints
- **Beach Details**: `GET /api/v1/beach-details/{identifier}` (BeachDetailsController::show)
  - Returns full beach data with all relationships
  - Includes: badges, articles, media, navigation (prev/next)
  - Cache TTL: 8 hours (BEACH_DETAILS_TTL)
  
- **Weather**: `GET /api/v1/beach-details/{identifier}/weather`
  - Returns current weather from OpenWeatherMap API
  - Cache TTL: 1 hour
  - Coordinates rounded to 2 decimals for cache sharing

#### Database Tables (Read-Only)
- **Core**: `beach` (all 60+ fields)
- **Relationships**: All pivot tables for facilities, activities, conditions
- **Media**: `media` table (images, videos, 360° tours via polymorphic relation)
- **Badges**: `badge_beach` pivot table
- **Articles**: `article_beach` pivot table

#### External APIs
- **OpenWeatherMap**: Current Weather API
  - Endpoint: `https://api.openweathermap.org/data/2.5/weather`
  - Rate Limit: 60 calls/minute (free tier)
  - Cache: 1 hour to stay within limits

### Access Type
- **Read-Only**: Database and external API reads
- **No Writes**: Comparison results not persisted

### Latency Tolerance
- **Real-Time**: < 2 seconds for 2-beach comparison
- **Acceptable**: < 5 seconds for 5-beach comparison
- **Weather**: Cached data acceptable (up to 1 hour old)

### Caching Constraints
- Beach details: 8-hour cache per beach ID + locale
- Weather: 1-hour cache per rounded coordinates
- Comparison results: Not cached (dynamic based on selected beaches)

### Security Implications
- **API Key**: OpenWeatherMap API key must be protected (server-side only)
- **Rate Limiting**: Enforce per-tier limits to avoid API quota exhaustion
- **Public Data**: All comparison data is public

---

## Capability 4: Context-Aware Suggestions

### Description
Provide recommendations based on user location, current weather, and real-time context.

### Required Data Sources

#### API Endpoints
- **Spatial Queries**: 
  - `GET /api/v1/spatial/beaches/within-radius` (PostGISController::beachesWithinRadius)
    - Parameters: latitude, longitude, radius (meters)
    - Uses PostGIS ST_DWithin for spatial search
    - Returns beaches with distance calculations
  
  - `GET /api/v1/spatial/beaches/nearest` (PostGISController::nearestBeaches)
    - Parameters: latitude, longitude, limit
    - Uses PostGIS ST_Distance with ORDER BY distance
  
  - `GET /api/v1/spatial/beaches/bounding-box` (PostGISController::beachesInBoundingBox)
    - Parameters: min_lat, max_lat, min_lon, max_lon
    - Uses latitude/longitude BETWEEN queries

- **Weather by Location**:
  - `GET /api/v1/cities/{slug}/weather`
  - `GET /api/v1/regions/{slug}/weather`
  - `GET /api/v1/islands/{slug}/weather`
  - `GET /api/v1/countries/{code}/weather`

#### Database Tables (Read-Only)
- **Spatial**: `beach.location` (PostGIS POINT geometry, SRID 4326)
  - Auto-generated from latitude/longitude via database trigger
  - Indexed with GIST spatial index
- **Geographic**: `city`, `region`, `island`, `countries` (with lat/lon for weather)

#### External APIs
- **OpenWeatherMap**: Current Weather API (same as Capability 3)

### Access Type
- **Read-Only**: Spatial queries via PostGIS
- **Derived**: Distance calculations performed by database

### Latency Tolerance
- **Real-Time**: < 2 seconds for spatial queries (critical for "near me" features)
- **Weather**: 1-hour cached data acceptable

### Caching Constraints
- Spatial queries: Cache by rounded coordinates + radius (e.g., "45.81,15.97_5000m")
- Cache TTL: 1 hour (beaches near user change infrequently)
- Weather: 1-hour cache per location

### Security Implications
- **User Location**: Never store user GPS coordinates (use for query only)
- **Privacy**: Location data not logged or persisted
- **Geofencing**: No restrictions on querying any location globally

---

## Capability 5: Educational Queries

### Description
Answer questions about beach types, regions, activities, and badges using database facts.

### Required Data Sources

#### API Endpoints
- **Geographic Entities**:
  - `GET /api/v1/continents` - List all continents
  - `GET /api/v1/continents/{code}` - Continent details
  - `GET /api/v1/countries` - List countries (with beach counts)
  - `GET /api/v1/countries/{code}` - Country details with badges, articles
  - `GET /api/v1/regions` - List regions
  - `GET /api/v1/regions/{slug}` - Region details
  - `GET /api/v1/islands` - List islands
  - `GET /api/v1/islands/{slug}` - Island details
  - `GET /api/v1/cities` - List cities
  - `GET /api/v1/cities/{slug}` - City details

- **Reference Data** (all with translations):
  - `GET /api/v1/beach-types` - Beach type definitions
  - `GET /api/v1/waterbodies` - Waterbody types
  - `GET /api/v1/sporting-activities` - Activity definitions
  - `GET /api/v1/beach-facilities` - Facility definitions
  - `GET /api/v1/accessibility` - Accessibility options

#### Database Tables (Read-Only)
- **All Translation Tables**: Provide localized definitions
  - `beach_type_translation`, `waterbody_translation`
  - `sporting_activities_translation`, `beach_facility_translation`
  - `accessibility_translation`, `user_type_translation`
  - `badge_translation`, `article_translation`

- **Aggregations**: Agent can aggregate data for statistics
  - Example: "How many beaches in Dalmatia?" → COUNT beaches WHERE region_id=X
  - Example: "What % of beaches have lifeguards?" → COUNT with facility / total

#### Meilisearch Indexes
- **All Indexes**: beaches, countries, regions, islands, cities
  - Used for "show me examples of X" queries

### Access Type
- **Read-Only**: SELECT queries with aggregations
- **Derived**: Statistics calculated on-the-fly (not pre-computed)

### Latency Tolerance
- **Real-Time**: < 2 seconds for simple definitions
- **Acceptable**: < 5 seconds for complex aggregations

### Caching Constraints
- Reference data: 30-day cache (definitions rarely change)
- Geographic entities: 30-day cache
- Aggregations: 24-hour cache (beach counts, statistics)

### Security Implications
- **Public Data**: All educational content is public
- **No Sensitive Info**: No business logic or internal metrics exposed

---

## Capability 6: Accessibility & Special Needs

### Description
Find beaches meeting specific accessibility requirements (wheelchair, lifeguard, shade, etc.).

### Required Data Sources

#### API Endpoints
- **Primary**: `GET /api/v1/beaches` (with accessibility filters)
  - Filter parameters: accessibility, parking, parking_beach_height, beach_facility
  - Example: accessibility=wheelchair,paved_access&beach_facility=accessible_toilet,lifeguard

- **Accessibility Options**: `GET /api/v1/accessibility`
  - Returns all accessibility types with translations
  - Examples: wheelchair, paved_access, beach_wheelchair, accessible_toilet

#### Database Tables (Read-Only)
- **Core**: `beach` (fields: parking, parking_beach_height_id, has_swimming_pool, boat_only)
- **Pivot**: `accessibility_beach` (many-to-many relationship)
- **Reference**: `accessibility`, `parking_beach_height`, `beach_facility`

#### Meilisearch Indexes
- Not primary (accessibility filters not in Meilisearch)
- Use database queries for precise filtering

### Access Type
- **Read-Only**: SELECT with JOIN on pivot tables

### Latency Tolerance
- **Real-Time**: < 2 seconds (critical for users with disabilities)

### Caching Constraints
- Accessibility queries: Cache by filter combination
- Cache TTL: 8 hours
- Invalidation: On beach accessibility updates

### Security Implications
- **Sensitive Use Case**: Accuracy critical for users with disabilities
- **Liability**: Agent must never guarantee accessibility (use "reported as accessible")
- **Disclaimer**: Always recommend verifying with beach operator

---

## Capability 7: Multi-Language Support

### Description
Respond in user's preferred language (19 locales) with localized beach data.

### Required Data Sources

#### API Endpoints
- **All Endpoints**: Accept `Accept-Language` header
  - Middleware: `app/Http/Middleware/SetLocaleFromHeader.php`
  - Locale mapping: `si` (frontend) ↔ `sl` (backend)

#### Database Tables (Read-Only)
- **All Translation Tables**:
  - `beach_translation` (name, description, slug per locale)
  - `country_translation` (name, full_name, slug, filter_title)
  - `region_translation`, `island_translation`, `city_translation`
  - `continent_translation`
  - All reference data translation tables (30+ tables)

#### Meilisearch Indexes
- **Limitation**: Meilisearch indexes English only (en locale)
- **Workaround**: Search in English, return results in requested locale via translations

### Access Type
- **Read-Only**: SELECT from translation tables with locale filter

### Latency Tolerance
- **Real-Time**: < 2 seconds (translation lookup adds minimal overhead)

### Caching Constraints
- Cache keys include locale: `beaches:index:{locale}:{filters}`
- Separate cache per locale (19 locales × cache entries)

### Security Implications
- **No User Input**: Locale from header only (validated against supported locales)
- **Fallback**: English (en) if requested locale unavailable
- **XSS**: All translations escaped in API responses

---

## Capability 8: Global Search

### Description
Search across all entities (beaches, countries, regions, islands, cities) with single query.

### Required Data Sources

#### API Endpoints
- **Primary**: `GET /api/v1/search` (SearchController::search)
  - Parameters: q (query), country_code, types (filter by entity type), limit
  - Searches across 5 Meilisearch indexes simultaneously
  - Cache TTL: 8 hours (SHORT_TTL)

#### Meilisearch Indexes (All 5)
- **beaches**: 2,445+ records (status_id=1)
- **countries**: ~50 records (with active beaches)
- **regions**: ~200 records (status_id=1, with active beaches)
- **islands**: ~150 records (status_id=1, with active beaches)
- **cities**: ~500 records (status_id=1, with active beaches)

#### Database Tables (Read-Only)
- Not directly queried (Meilisearch handles search)
- Database used for indexing only (via Laravel Scout)

### Access Type
- **Read-Only**: Meilisearch queries only
- **No Writes**: Search results not persisted

### Latency Tolerance
- **Real-Time**: < 500ms expected (Meilisearch is fast)
- **Acceptable**: < 1 second for complex queries

### Caching Constraints
- Search results: Cache by query + filters + locale
- Cache TTL: 8 hours
- Invalidation: On entity updates (automatic via Scout)

### Security Implications
- **Public Data**: All search results are public
- **Input Sanitization**: Query string sanitized to prevent injection
- **Rate Limiting**: Enforce per-tier limits (Free: 10/hour, Pro: 500/hour)

---

## Data Source Summary

### API Endpoints (Total: 50+)
- **Beaches**: 5 endpoints (index, show, filters, most-visited, map-bounds)
- **Beach Details**: 2 endpoints (show, weather)
- **Geographic Entities**: 15 endpoints (continents, countries, regions, islands, cities + weather)
- **Spatial Queries**: 6 endpoints (within-radius, nearest, bounding-box, density-grid, statistics, distance)
- **Reference Data**: 20+ endpoints (beach-types, facilities, activities, accessibility, etc.)
- **Search**: 1 endpoint (global search)
- **Slug Resolver**: 1 endpoint (determine entity type from slug)

### Database Tables (Total: 80+)
- **Core Entities**: 6 tables (beach, countries, region, island, city, continents)
- **Translation Tables**: 30+ tables (one per translatable entity)
- **Reference Data**: 20+ tables (beach_type, waterbody, accessibility, etc.)
- **Pivot Tables**: 15+ tables (many-to-many relationships)
- **Media**: 3 tables (media, media_type, media_collection)
- **Other**: status, users, badges, articles

### Meilisearch Indexes (Total: 5)
- beaches, countries, regions, islands, cities
- Total indexed records: ~3,500+
- Update frequency: Real-time (via Laravel Scout)

### External APIs (Total: 1)
- **OpenWeatherMap**: Current Weather API
  - Rate Limit: 60 calls/minute (free tier)
  - Cache: 1 hour to stay within limits

### Cache Strategy
- **Reference Data**: 30 days (rarely changes)
- **Beach Details**: 8 hours (moderate change frequency)
- **Weather**: 1 hour (external API, rate limits)
- **Search Results**: 8 hours (Meilisearch, moderate change)
- **Spatial Queries**: 1 hour (location-based, dynamic)

### Security Posture
- **100% Read-Only**: Agent has ZERO write permissions
- **Public Data**: All data is public (no authentication required for reads)
- **Rate Limiting**: Enforced per pricing tier
- **Input Validation**: All parameters validated via Laravel Request classes
- **SQL Injection**: Protected by Eloquent ORM (no raw queries)
- **XSS**: All output escaped in API responses
- **API Keys**: OpenWeatherMap key server-side only (never exposed to agent or client)

---

**End of Capability Map**

This document maps all 8 AI agent capabilities to existing platform data sources. All data sources listed are currently implemented and production-ready. No new data sources are required for agent implementation.

