# Development Handoff / Change Log

## 2025-12-01 - Meilisearch Hetzner Deployment Guide

### Overview

Created comprehensive deployment documentation for setting up Meilisearch on a dedicated Hetzner server, since Laravel Cloud doesn't support Meilisearch natively.

### Why Separate Server?

- ✅ **Dedicated Resources** - Search operations don't compete with API/database
- ✅ **Independent Scaling** - Scale search independently from main infrastructure
- ✅ **Better Security** - Isolated network, separate firewall rules
- ✅ **Cost Efficient** - Hetzner pricing (~€8/month) vs managed services (€50+/month)
- ✅ **High Availability** - Main API continues working if search is down

### Documentation Created

1. **Full Setup Guide** - `docs/deployment/MEILISEARCH_HETZNER_SETUP.md`
   - Comprehensive step-by-step guide (300+ lines)
   - Covers server creation, installation, SSL setup, security, monitoring, backups
   - Detailed troubleshooting section
   - Production-ready configuration

2. **Quick Start Guide** - `docs/deployment/MEILISEARCH_QUICK_START.md`
   - Condensed 30-minute setup checklist
   - Essential commands only
   - Quick reference for experienced users

3. **Automated Setup Script** - `docs/deployment/scripts/setup-meilisearch-hetzner.sh`
   - Bash script that automates entire setup
   - Interactive prompts for domain and email
   - Generates secure master key
   - Configures systemd, nginx, SSL, firewall, backups
   - ~5 minutes hands-on time

4. **Deployment Summary** - `MEILISEARCH_DEPLOYMENT_SUMMARY.md`
   - Overview of all documentation
   - Quick reference guide
   - Architecture diagram
   - Cost breakdown
   - Maintenance schedule

### Server Specifications

**Recommended (Production):**
- Type: Hetzner CPX22 (or CPX21 if available)
- vCPU: 2-3 cores
- RAM: 4 GB
- Storage: 80 GB SSD
- Cost: €5-8/month
- Location: Nuremberg, Germany

**Note:** CPX22 (2 vCPU, 4GB RAM, 80GB SSD) is often the lowest available option and works perfectly.

**Current Data Size:**
- ~4,120 documents (beaches, cities, islands, regions, countries)
- ~50-100 MB indexed data

### Setup Process

**Option 1: Automated Script (Recommended)**
```bash
ssh root@YOUR_SERVER_IP
wget https://raw.githubusercontent.com/.../setup-meilisearch-hetzner.sh
chmod +x setup-meilisearch-hetzner.sh
sudo ./setup-meilisearch-hetzner.sh
```

**Option 2: Manual Setup**
Follow `docs/deployment/MEILISEARCH_QUICK_START.md`

### Configuration

**On Hetzner Server:**
- Domain: `search.beachrex.com`
- SSL: Let's Encrypt (auto-renewing)
- Port: 443 (HTTPS only)
- Firewall: UFW (ports 22, 80, 443)
- Service: Systemd (auto-start on boot)

**On Laravel Cloud:**
```env
SCOUT_DRIVER=meilisearch
SCOUT_QUEUE=true
MEILISEARCH_HOST=https://search.beachrex.com
MEILISEARCH_KEY=YOUR_MASTER_KEY_HERE
```

### Post-Deployment

1. Index data: `php artisan search:index`
2. Verify: `curl https://search.beachrex.com/health`
3. Test search: `curl "https://api.beachrex.com/api/v1/search?q=miami"`

### Maintenance

- **Daily:** Automated backups (2 AM)
- **Weekly:** Review logs, check disk space
- **Monthly:** Test backup restoration, update packages

### Security Features

- ✅ Firewall configured (UFW)
- ✅ SSL certificate (Let's Encrypt)
- ✅ Master key authentication
- ✅ Localhost-only binding
- ✅ Nginx reverse proxy
- ✅ Non-root service user
- ✅ Strict file permissions

### Cost

- Hetzner CPX22: €5-8/month
- Total yearly: ~€60-96
- Bandwidth: Included (20TB)

**Comparison:**
- Algolia: ~€50-200/month
- Elasticsearch Cloud: ~€50-100/month
- **Hetzner + Meilisearch: €5-8/month** ✅

### Important Setup Notes

1. **Stay as root during installation** - Don't create or switch to non-root user until after Meilisearch is installed
2. **Cloudflare DNS** - Set Proxy status to "DNS only" (gray cloud), not "Proxied" (orange cloud)
3. **Nginx configuration** - Start with HTTP-only config, then let Certbot add SSL automatically to avoid certificate errors
4. **Firewall order** - Allow SSH (port 22) BEFORE enabling UFW to avoid lockout

### Local Development Configuration

The local Meilisearch service has been **removed from docker-compose.yml**. Both local development and production now use the remote Hetzner Meilisearch server.

**Benefits:**
- ✅ Consistency between development and production
- ✅ Shared indexed data across all developers
- ✅ Less local resources needed
- ✅ Faster setup for new developers

**Configuration:**
```env
# Local .env
SCOUT_DRIVER=meilisearch
MEILISEARCH_HOST=https://search.beachrex.com
MEILISEARCH_KEY=YOUR_MASTER_KEY
SCOUT_QUEUE=false  # Immediate indexing for development
```

See `docs/deployment/MEILISEARCH_LOCAL_SETUP.md` for detailed configuration.

---

## 2025-12-01 - Country endpoints: support for code or slug

- Updated `CountryController@show` to accept either a 2–3 letter ISO country code (e.g. `it`, `IT`) or a localized slug (e.g. `italy`, `slovenia`).
  - Detection: `/^[A-Za-z]{2,3}$/` is treated as a code (normalized to upper case); everything else is treated as a slug (normalized to lower case).
  - Slug lookups use `country_translation.slug` with locale fallback: first the current `app()->getLocale()`, then English (`en`).
  - Response continues to be shaped by `CountryResource`, including the localized `slug` field.
  - Caching key now encodes identifier type and normalized value: `country:show:{locale}:{code|slug}:{normalized}` to avoid collisions.
- Updated `CountryController@weather` to accept the same `codeOrSlug` identifier and reuse the same code/slug + locale-fallback lookup pattern before building the `WeatherConfigurationResponse`.
- API routes were updated so both endpoints now use `{codeOrSlug}` instead of `{code}`:
  - `GET /api/v1/countries/{codeOrSlug}`
  - `GET /api/v1/countries/{codeOrSlug}/weather`
- Swagger/OpenAPI annotations for both endpoints were updated:
  - Path templates now use `{codeOrSlug}`.
  - Path parameter description explicitly mentions "code or localized slug" and includes examples for both forms.
  - Existing response schema (including `slug`, navigation block, and weather shape) remains unchanged.

**Examples**

- `GET /api/v1/countries/it` → lookup by ISO code (Italian country, any locale)
- `GET /api/v1/countries/italy` → lookup by localized slug; with `Accept-Language: en`, uses `italy` in `en` translation; with `Accept-Language: de`, prefers `de` translation if available, otherwise falls back to `en`.
- `GET /api/v1/countries/italy/weather` → same identifier handling as above, returns the country-centroid weather configuration.

Notes:
- Existing integrations that use codes (e.g. `/api/v1/countries/it`) keep working without changes.
- For new frontend work, prefer using slugs in URLs for SEO-friendly paths, aligned with regions/cities behavior (`/countries/italy`, `/regions/istria`, etc.).


This file tracks backend changes that are important for frontend integration and future development.

## 📚 Documentation

All technical documentation is organized in the **[docs/](./docs/README.md)** folder:

- **[docs/weather/](./docs/weather/README.md)** - OpenWeatherMap integration (weather endpoints)
- **[docs/api/](./docs/api/)** - API endpoint documentation
- **[docs/admin/](./docs/admin/)** - Admin panel documentation
- **[docs/features/](./docs/features/)** - Feature-specific guides (Imagor, translations, etc.)
- **[docs/deployment/](./docs/deployment/)** - Deployment and server configuration

👉 **Start here:** [docs/README.md](./docs/README.md)

---

## 2025-11-30 – Meilisearch Global Search Integration

### 1. Overview

**Context**
- Integrated Meilisearch for powerful global search across beaches, cities, islands, regions, and countries
- Provides instant search with typo tolerance, faceted filtering, and grouped results
- Similar to Laravel Filament admin search experience
- Self-hosted solution for full control and cost efficiency

**Benefits**
- ✅ Instant search results (<50ms response time)
- ✅ Typo-tolerant search (handles misspellings)
- ✅ Grouped results by entity type (beaches, cities, islands, regions, countries)
- ✅ Filterable by country, entity type
- ✅ Cached results for optimal performance
- ✅ Only indexes active entities (status_id = 1 and has active beaches)
- ✅ Fully documented in Swagger/OpenAPI

---

### 2. API Endpoint

**Endpoint:** `GET /api/v1/search`

**Headers:**
- `Accept-Language` (string, optional) - Locale for localized slugs (e.g., `en`, `si`, `de`, `hr`). Defaults to `en` if not provided or translation not available.

**Query Parameters:**
- `q` (string, required) - Search query string (min: 1, max: 255 characters)
- `limit` (integer, optional) - Maximum results per entity type (default: 5, max: 20)
- `country_code` (string, optional) - Filter by country code (e.g., `HR`, `US`)
- `types` (string, optional) - Comma-separated entity types to search (e.g., `beach,city,island,region,country`)

**Localization:**
- All slugs in the response are localized based on the `Accept-Language` header
- Includes all necessary slugs for URL building: `continent_slug`, `country_slug`, `region_slug`, `city_slug`, `island_slug`
- Falls back to English (`en`) if translation not available for requested locale
- Cache keys include locale to ensure correct localized results

**Examples:**
```bash
# Search for "miami" across all entity types with English slugs
curl -H "Accept-Language: en" "http://localhost/api/v1/search?q=miami"

# Search with Slovenian slugs
curl -H "Accept-Language: si" "http://localhost/api/v1/search?q=miami"

# Search for "split" with max 10 results per type
GET /api/v1/search?q=split&limit=10

# Search for beaches and cities only in Croatia
GET /api/v1/search?q=beach&types=beach,city&country_code=HR

# Search for "hvar" in all types
GET /api/v1/search?q=hvar
```

**Response Format:**
```json
{
  "success": true,
  "data": {
    "beaches": [
      {
        "id": 123,
        "name": "Miami Beach",
        "slug": "miami-beach",
        "country_code": "US",
        "country_name": "United States",
        "region_name": "Florida",
        "city_name": "Miami",
        "island_name": "",
        "main_image": "miami-beach.jpg",
        "type": "beach"
      }
    ],
    "cities": [
      {
        "id": 45,
        "code": "miami",
        "name": "Miami",
        "slug": "miami",
        "country_code": "US",
        "country_name": "United States",
        "region_name": "Florida",
        "island_name": "",
        "main_image": "miami.jpg",
        "type": "city"
      }
    ],
    "islands": [],
    "regions": [],
    "countries": []
  },
  "meta": {
    "query": "miami",
    "total_results": 7,
    "results_by_type": {
      "beaches": 5,
      "cities": 2,
      "islands": 0,
      "regions": 0,
      "countries": 0
    }
  },
  "message": "Search completed successfully"
}
```

---

### 3. Backend Implementation

**Dependencies Installed:**
- `laravel/scout` (v10.22.1) - Laravel's official search package
- `meilisearch/meilisearch-php` (v1.16.1) - Meilisearch PHP client
- `http-interop/http-factory-guzzle` (1.2.0) - HTTP factory for Meilisearch

**Models Updated:**
All searchable models now include the `Laravel\Scout\Searchable` trait:
- `app/Models/Beach.php`
- `app/Models/City.php`
- `app/Models/Island.php`
- `app/Models/Region.php`
- `app/Models/Country.php`

Each model implements:
- `toSearchableArray()` - Defines which fields to index
- `searchableAs()` - Defines the index name
- `shouldBeSearchable()` - Determines if entity should be indexed (only active entities with beaches)

**Controller Created:**
- `app/Http/Controllers/Api/V1/SearchController.php` - Handles global search requests with full Swagger/OpenAPI documentation

**Route Added:**
- `GET /api/v1/search` - Global search endpoint

**Artisan Command Created:**
- `app/Console/Commands/IndexSearchableModels.php` - Command to index all searchable models

**Swagger/OpenAPI Documentation:**
- Full API documentation with request/response examples
- Tag: "Search" - Global search API endpoints
- All parameters, responses, and error codes documented
- TypeScript interfaces can be auto-generated from Swagger schema

---

### 4. Configuration

**Environment Variables (.env):**
```env
SCOUT_DRIVER=meilisearch
SCOUT_PREFIX=
SCOUT_QUEUE=false  # Set to true for production with queue workers

MEILISEARCH_HOST=http://meilisearch:7700
MEILISEARCH_KEY=masterKey  # Change to secure key in production
MEILISEARCH_NO_ANALYTICS=true
MEILISEARCH_ENV=development  # Use 'production' on server
FORWARD_MEILISEARCH_PORT=7700
```

**Meilisearch Index Settings (config/scout.php):**
```php
'index-settings' => [
    'beaches' => [
        'filterableAttributes' => ['country_code', 'region_name', 'city_name', 'island_name', 'type'],
        'sortableAttributes' => ['name'],
        'searchableAttributes' => ['name', 'description', 'country_name', 'region_name', 'city_name', 'island_name'],
    ],
    'cities' => [
        'filterableAttributes' => ['country_code', 'region_name', 'type'],
        'sortableAttributes' => ['name'],
        'searchableAttributes' => ['name', 'country_name', 'region_name'],
    ],
    // ... similar for islands, regions, countries
]
```

---

### 5. Deployment & Setup

**Docker Compose Configuration:**

Meilisearch service is already added to `docker-compose.yml`:
```yaml
services:
  meilisearch:
    image: 'getmeili/meilisearch:v1.11'
    ports:
      - '${FORWARD_MEILISEARCH_PORT:-7700}:7700'
    environment:
      MEILI_MASTER_KEY: '${MEILISEARCH_KEY:-masterKey}'
      MEILI_NO_ANALYTICS: '${MEILISEARCH_NO_ANALYTICS:-true}'
      MEILI_ENV: '${MEILISEARCH_ENV:-development}'
    volumes:
      - 'sail-meilisearch:/meili_data'
    networks:
      - sail
    healthcheck:
      test:
        - CMD
        - wget
        - '--no-verbose'
        - '--spider'
        - 'http://localhost:7700/health'
      retries: 3
      timeout: 5s

volumes:
  sail-meilisearch:
    driver: local
```

**Environment Variables (.env):**
```env
# Scout Search Configuration
SCOUT_DRIVER=meilisearch
SCOUT_QUEUE=false  # Set to true for production with queue workers

# Meilisearch Configuration
MEILISEARCH_HOST=http://meilisearch:7700
MEILISEARCH_KEY=masterKey  # Change to secure key in production
MEILISEARCH_NO_ANALYTICS=true
MEILISEARCH_ENV=development  # Use 'production' on server
FORWARD_MEILISEARCH_PORT=7700
```

**Step 1: Start Meilisearch**
```bash
./vendor/bin/sail up -d meilisearch
```

**Step 2: Index All Models (First Time Only)**
```bash
# Index all models at once
./vendor/bin/sail artisan search:index

# Or index specific model
./vendor/bin/sail artisan search:index Beach

# Flush and re-index (useful after schema changes)
./vendor/bin/sail artisan search:index --flush
```

**Step 3: Verify Indexing**
Visit Meilisearch dashboard at `http://localhost:7700` to verify indexes are created.

**⚠️ Important Notes:**
- **Automatic Indexing:** After initial setup, models automatically update the search index when created/updated/deleted
- **No Manual Re-indexing Needed:** When you add/edit beaches in Filament admin, they're automatically indexed
- **Only Re-index When:** Changing `toSearchableArray()` method or index settings in `config/scout.php`
- **Production:** Set `SCOUT_QUEUE=true` and run queue workers for better performance

---

### 6. Frontend Integration

**TypeScript Interface (Auto-generate from Swagger):**
```typescript
interface SearchResult {
  id: number;
  name: string;
  slug: string;  // Localized based on Accept-Language header
  country_code: string;
  country_name: string;
  region_name?: string;
  city_name?: string;
  island_name?: string;
  main_image: string;
  type: 'beach' | 'city' | 'island' | 'region' | 'country';
  // URL building slugs (all localized)
  continent_slug: string;
  country_slug: string;
  region_slug?: string | null;
  city_slug?: string | null;
  island_slug?: string | null;
}

interface SearchResponse {
  success: boolean;
  data: {
    beaches: SearchResult[];
    cities: SearchResult[];
    islands: SearchResult[];
    regions: SearchResult[];
    countries: SearchResult[];
  };
  meta: {
    query: string;
    total_results: number;
    results_by_type: {
      beaches: number;
      cities: number;
      islands: number;
      regions: number;
      countries: number;
    };
  };
  message: string;
}
```

**Example Frontend Usage:**
```typescript
// Search function
async function globalSearch(query: string, limit: number = 5): Promise<SearchResponse> {
  const response = await fetch(
    `${process.env.NEXT_PUBLIC_API_ENDPOINT}/api/v1/search?q=${encodeURIComponent(query)}&limit=${limit}`
  );
  return response.json();
}

// Usage in component
const results = await globalSearch('miami', 5);

// Display grouped results
results.data.beaches.forEach(beach => {
  console.log(`Beach: ${beach.name} in ${beach.city_name}, ${beach.country_name}`);
});

results.data.cities.forEach(city => {
  console.log(`City: ${city.name}, ${city.country_name}`);
});
```

---

### 7. Cache Strategy

- Search results are cached for **5 minutes** (SHORT_TTL)
- Cache key includes query, limit, country_code, and types
- Cache is automatically invalidated when models are updated (Scout handles this)

---

### 8. Performance Notes

- Meilisearch typically responds in <50ms
- Results are cached for 5 minutes to reduce load
- Only active entities (status_id = 1) are indexed
- Geographic entities only indexed if they have active beaches
- Automatic re-indexing when models are created/updated/deleted

---

### 9. Testing

**Test Search Endpoint:**
```bash
# Test basic search
curl "http://localhost/api/v1/search?q=miami"

# Test with filters
curl "http://localhost/api/v1/search?q=beach&country_code=HR&limit=10"

# Test specific types
curl "http://localhost/api/v1/search?q=split&types=beach,city"
```

---

### 10. Maintenance

**Re-index All Models:**
```bash
./vendor/bin/sail artisan search:index --flush
```

**Re-index Specific Model:**
```bash
./vendor/bin/sail artisan search:index Beach --flush
```

**Check Scout Queue (if using queues):**
```bash
./vendor/bin/sail artisan queue:work
```

---

## 2025-11-30 – Most Visited Beaches Endpoint

### 1. Overview

**Context**
- Added new endpoint to fetch most visited beaches based on `beach_views` counter
- Supports filtering by continent, country, region, city, or island
- Returns up to 20 beaches by default (configurable, max 100)
- Cached for 24 hours for optimal performance

**Benefits**
- ✅ Frontend can display "Most Visited" sections for any geographic entity
- ✅ Reusable across continent, country, region, city, and island pages
- ✅ Efficient caching reduces database load
- ✅ Fully documented in Swagger/OpenAPI

---

### 2. API Endpoint

**Endpoint:** `GET /api/v1/beaches/most-visited`

**Query Parameters:**
- `continent_code` (string, optional) - Filter by continent code (e.g., `EU`, `AS`)
- `country_code` (string, optional) - Filter by country code (e.g., `HR`, `IT`, `SI`)
- `region_code` (string, optional) - Filter by region code (e.g., `dalmatia`, `istria`)
- `city_code` (string, optional) - Filter by city code (e.g., `split`, `dubrovnik`)
- `island_code` (string, optional) - Filter by island code (e.g., `hvar`, `krk`)
- `limit` (integer, optional) - Number of beaches to return (default: 20, max: 100)

**Headers:**
- `Accept-Language` (string, optional) - Locale for translations (e.g., `en`, `de`, `hr`, `sl`, `it`)

**Examples:**
```bash
# Get top 20 most visited beaches globally
GET /api/v1/beaches/most-visited

# Get top 10 most visited beaches in Croatia
GET /api/v1/beaches/most-visited?country_code=HR&limit=10

# Get top 5 most visited beaches in Dalmatia region
GET /api/v1/beaches/most-visited?region_code=dalmatia&limit=5

# Get top 20 most visited beaches on Hvar island
GET /api/v1/beaches/most-visited?island_code=hvar

# Get top 15 most visited beaches in Split city
GET /api/v1/beaches/most-visited?city_code=split&limit=15

# Get top 20 most visited beaches in Europe
GET /api/v1/beaches/most-visited?continent_code=EU
```

**Response Format:**
```json
{
  "success": true,
  "data": [
    {
      "id": 123,
      "name": "Paradise Beach",
      "slug": "paradise-beach",
      "latitude": "43.5081",
      "longitude": "16.4402",
      "country_code": "HR",
      "country_name": "Croatia",
      "country_slug": "croatia",
      "continent_code": "EU",
      "continent_slug": "europe",
      "region_slug": "dalmatia-split",
      "island_slug": "hvar",
      "city_slug": "hvar",
      "main_image": "img/beaches/paradise/main.jpg"
    }
  ],
  "message": "Most visited beaches retrieved successfully",
  "meta": {
    "total": 20,
    "limit": 20
  }
}
```

---

### 3. Implementation Details

**File:** `app/Http/Controllers/Api/V1/BeachController.php`

**Method:** `mostVisited(Request $request)`

**Key Features:**
- Orders beaches by `beach_views` field (descending)
- Only returns active beaches (`status_id = 1`)
- Loads all necessary relationships (translations, geographic entities, media)
- Generates cache key based on filters and locale
- Cache TTL: 24 hours (86400 seconds)

**Cache Key Format:**
```
beaches:most_visited:{locale}:{filters}
```

**Examples:**
- `beaches:most_visited:en:all` - All beaches, English
- `beaches:most_visited:en:country:HR_limit:10` - Top 10 Croatian beaches, English
- `beaches:most_visited:hr:region:dalmatia_limit:5` - Top 5 Dalmatia beaches, Croatian
- `beaches:most_visited:en:island:hvar_limit:20` - Top 20 Hvar beaches, English

---

### 4. Route Configuration

**File:** `routes/api.php`

**Route Added:**
```php
Route::get('/beaches/most-visited', [BeachController::class, 'mostVisited']);
```

**Important:** This route must be defined BEFORE the `beaches/{id}` route to prevent route conflicts.

---

### 5. Swagger Documentation

**Fully documented in OpenAPI/Swagger:**
- Operation ID: `getMostVisitedBeaches`
- Tag: `Beaches`
- All query parameters documented with examples
- Response schema references `BeachSchema`
- Includes meta information (total, limit)

**Regenerate Swagger docs:**
```bash
./vendor/bin/sail artisan l5-swagger:generate
```

---

### 6. Frontend Integration

**Use Cases:**
1. **Homepage:** Display "Most Visited Beaches" globally
2. **Country Pages:** Show most visited beaches in that country
3. **Region Pages:** Show most visited beaches in that region
4. **City Pages:** Show most visited beaches in that city
5. **Island Pages:** Show most visited beaches on that island

**Example Frontend Code:**
```typescript
// Get most visited beaches in Croatia
const response = await fetch('/api/v1/beaches/most-visited?country_code=HR&limit=10', {
  headers: {
    'Accept-Language': 'en'
  }
});
const data = await response.json();
const beaches = data.data; // Array of beach objects
```

---

### 7. Testing

**Test the endpoint:**
```bash
# Test globally
curl http://localhost/api/v1/beaches/most-visited

# Test with filters
curl "http://localhost/api/v1/beaches/most-visited?country_code=HR&limit=5"

# Test with locale
curl -H "Accept-Language: hr" "http://localhost/api/v1/beaches/most-visited?region_code=dalmatia"
```

---

## 2025-11-29 – Islands API Region Filtering & Test Database Fix

### 1. Overview

**Context**
- Added `region_code` filtering to Islands API (index, show, and weather endpoints)
- Updated Island model with `scopeByRegion()` method supporting both region ID and code
- Fixed test database setup issues with data-seeding migrations
- Updated all Swagger/OpenAPI documentation
- Ensured proper Redis cache key segmentation for all filter combinations

**Benefits**
- ✅ Islands can now be filtered by region (e.g., `?region_code=kvarner`)
- ✅ Filters can be combined (e.g., `?country_code=HR&region_code=kvarner`)
- ✅ Cache keys properly segmented to prevent collisions
- ✅ All tests passing (7/7 Island API tests)
- ✅ Consistent filtering across all geographic entity APIs

---

### 2. API Changes

#### 2.1 Islands API - New Region Filtering

**Endpoints Updated:**
1. `GET /api/v1/islands` - List all islands
2. `GET /api/v1/islands/{slug}` - Get single island
3. `GET /api/v1/islands/{slug}/weather` - Get island weather

**New Query Parameter:**
- `region_code` (string, optional) - Filter islands by region code (e.g., `istria`, `kvarner`, `dalmatia-split`)

**Examples:**
```bash
# Get all islands in Kvarner region
GET /api/v1/islands?region_code=kvarner

# Get all islands in Croatia's Kvarner region
GET /api/v1/islands?country_code=HR&region_code=kvarner

# Get Krk island details (only if in Kvarner region)
GET /api/v1/islands/krk?region_code=kvarner

# Get weather for Krk island (validate it's in Kvarner)
GET /api/v1/islands/krk/weather?region_code=kvarner
```

**Filter Behavior:**
- All filters are optional and can be combined
- When `region_code` is provided on `show` endpoint, returns 404 if island doesn't match
- Filters work with both region ID (numeric) and region code (string)

---

### 3. Model Changes

#### 3.1 Island Model - New Scope

**File:** `app/Models/Island.php`

**New Method:**
```php
/**
 * Scope to filter islands by region (accepts region ID or code)
 *
 * @param \Illuminate\Database\Eloquent\Builder $query
 * @param int|string $regionIdOrCode Region ID (numeric) or region code (string, e.g., istria, kvarner)
 * @return \Illuminate\Database\Eloquent\Builder
 *
 * @example Island::byRegion('kvarner')->get()  // Get all islands in Kvarner region
 * @example Island::byRegion(7)->get()          // Get all islands in region ID 7
 */
public function scopeByRegion($query, $regionIdOrCode)
{
    if (is_numeric($regionIdOrCode)) {
        return $query->where('region_id', $regionIdOrCode);
    }
    return $query->whereHas('region', function ($q) use ($regionIdOrCode) {
        $q->where('code', $regionIdOrCode);
    });
}
```

---

### 4. Cache Key Updates

**Cache Key Format:**
```
islands:index:{locale}:country:{code}:region:{code}
island:show:{locale}:{slug}:{country_code}:{region_code}
island:weather:{locale}:{slug}:{country_code}:{region_code}
```

**Examples:**
- `islands:index:en:all` - All islands, English
- `islands:index:en:country:HR` - Croatian islands, English
- `islands:index:en:country:HR:region:kvarner` - Kvarner islands, English
- `island:show:en:krk:HR:kvarner` - Krk island with filters

---

### 5. Data Seeding Architecture Fix

**Issue:**
- Initially created migrations for data operations (assigning badges/articles, cleaning data)
- **Problem:** Migrations run BEFORE seeders, so:
  - Badge/article assignments would fail (foreign key violations)
  - Data cleanup operations would run on empty tables
- This would cause failures on fresh production database setup

**Solution:**
Converted all data-manipulation migrations to proper seeders that run AFTER data is seeded:

**Files Created:**
- `database/seeders/RegionBadgeArticleSeeder.php` - Assigns badges and articles to regions
- `database/seeders/CityBadgeArticleSeeder.php` - Assigns badges and articles to cities
- `database/seeders/DataCleanupSeeder.php` - Performs data quality fixes (runs LAST):
  - Removes leading slashes from icon paths (waterbody, beach_type, user_type)
  - Fixes escaped quotes in beach translations (name, description, slug)
  - Removes double spaces from beach translations

**Files Deleted:**
- `database/migrations/2025_11_29_212833_assign_badges_and_articles_to_regions.php` (converted to seeder)
- `database/migrations/2025_11_29_220741_assign_badges_and_articles_to_cities.php` (converted to seeder)
- `database/migrations/2025_11_26_000001_remove_leading_slashes_from_icon_paths.php` (converted to seeder)
- `database/migrations/2025_11_26_000002_fix_escaped_quotes_in_beach_translations.php` (converted to seeder)
- `database/migrations/2025_11_26_000003_remove_double_spaces_from_beach_translations.php` (converted to seeder)

**DatabaseSeeder Updated:**
Added new seeders in correct order:
```php
IslandSeeder::class,
ArticleSeeder::class,
BadgeSeeder::class,
RegionBadgeArticleSeeder::class,  // Assign badges/articles to regions (after BadgeSeeder/ArticleSeeder)
CityBadgeArticleSeeder::class,    // Assign badges/articles to cities (after BadgeSeeder/ArticleSeeder)
GenderSeeder::class,
// ... other seeders
GeographicEntityMainImageSeeder::class,
DataCleanupSeeder::class,  // Data cleanup operations (run LAST after all data is seeded)
```

**Impact:**
- ✅ Seeders run in correct order (data created first, then manipulated)
- ✅ Works correctly on fresh production database setup
- ✅ All tests passing
- ✅ Data quality fixes applied after seeding

---

### 6. Swagger Documentation Updates

**Files Updated:**
- `app/Http/Controllers/Api/V1/IslandController.php` - Added `region_code` parameter documentation
- `app/Swagger/Schemas/IslandSchema.php` - Added `country_code` and `region_id` properties

**Parameter Documentation:**
```php
@OA\Parameter(
    name="region_code",
    in="query",
    description="Filter islands by region code (e.g., istria, kvarner, dalmatia-split). Can be combined with country_code.",
    required=false,
    @OA\Schema(type="string", example="kvarner")
)
```

---

### 7. Test Updates

**File:** `tests/Feature/IslandApiTest.php`

**Changes:**
- Added `country_code` to island test data to support filtering tests
- All 7 tests passing:
  - ✅ can get all islands
  - ✅ returns all islands with beaches
  - ✅ can get single island by slug
  - ✅ returns 404 for nonexistent island
  - ✅ islands respect locale
  - ✅ islands only return those with active beaches
  - ✅ island includes beach count

---

## 2025-11-29 – Geographic Entity Main Image Seeder & API Consistency

### 1. Overview

**Context**
- Created database seeder to automatically populate `main_image` for all geographic entities
- Images are sourced from the most viewed beach in each geographic segment
- Standardized all geographic entity APIs to use `main_image` field consistently
- Removed `image_path` from Country API responses for consistency
- Updated all Swagger/OpenAPI documentation

**Benefits**
- ✅ Automatic image population based on actual beach data
- ✅ Images represent the most popular beach in each geographic area
- ✅ Consistent API field naming across all geographic entities
- ✅ No manual image upload required for initial data
- ✅ Can be re-run to update images based on latest beach view counts

---

### 2. Database Seeder

#### 2.1 GeographicEntityMainImageSeeder

**File:** `database/seeders/GeographicEntityMainImageSeeder.php`

**Purpose:** Populates `main_image` field for all geographic entities (continents, countries, regions, islands, cities) by selecting the primary image from the most viewed beach in each segment.

**Selection Logic:**
1. Finds the beach with highest `beach_views` for each geographic entity
2. Only considers beaches with `status_id = 1` (active beaches)
3. Gets the primary image from beach's media gallery:
   - Filters: `media_type_id = 1` (images), `media_collection_id = 1` (beach_gallery), `status_id = 1`
   - Prioritizes: `is_primary = 1` (true)
   - Fallback: First image with `is_primary = 0` ordered by `order ASC, created_at ASC`
4. Updates entity's `main_image` field with the image path
5. Skips entities that don't have beaches or don't have valid images

**Database Fields Updated:**
- `continents.main_image`
- `countries.image_path` (aliased as `main_image` in API)
- `region.main_image`
- `island.main_image`
- `city.main_image`

**How to Run:**
```bash
# Development
./vendor/bin/sail artisan db:seed --class=GeographicEntityMainImageSeeder

# Production
php artisan db:seed --class=GeographicEntityMainImageSeeder
```

**Results (as of 2025-11-29):**
- ✅ 1 continent populated (EU - Europe)
- ✅ 13 countries populated
- ✅ 73 regions populated
- ✅ 29 islands populated
- ✅ 1050 cities populated

**Image Path Format:**
- Web uploads: `img/beaches/{folder_name}/{filename}`
- Mobile uploads: `img/beaches_mobile/{folder_name}/{filename}`
- Path determined by `beach.mobile_upload` boolean field

**Safe to Re-run:**
- Yes, can be run multiple times to update images based on latest view counts
- Will overwrite existing `main_image` values

---

### 3. API Changes - Consistent `main_image` Field

#### 3.1 Country API Standardization

**Previous Behavior:**
- Country API returned both `image_path` and `main_image` (duplicate data)

**New Behavior:**
- Country API now returns only `main_image` (consistent with all other entities)
- Database still uses `image_path` column, but it's mapped to `main_image` in API responses

**Files Updated:**
- `app/Http/Resources/CountryResource.php` - Removed `image_path`, kept only `main_image`
- `app/Swagger/Schemas/CountrySchema.php` - Updated to show only `main_image`
- `app/Http/Controllers/Api/V1/CountryController.php` - Updated Swagger annotations

#### 3.2 All Geographic Entity APIs

All geographic entity list and detail endpoints now consistently return `main_image`:

**Endpoints:**
- `GET /api/v1/continents` - Returns `main_image`
- `GET /api/v1/continents/{code}` - Returns `main_image`
- `GET /api/v1/countries` - Returns `main_image` (no longer `image_path`)
- `GET /api/v1/countries/{code}` - Returns `main_image` (no longer `image_path`)
- `GET /api/v1/regions` - Returns `main_image`
- `GET /api/v1/regions/{slug}` - Returns `main_image`
- `GET /api/v1/islands` - Returns `main_image`
- `GET /api/v1/islands/{slug}` - Returns `main_image`
- `GET /api/v1/cities` - Returns `main_image`
- `GET /api/v1/cities/{slug}` - Returns `main_image`

**Files Updated:**
- `app/Http/Controllers/Api/V1/ContinentController.php` - Added `main_image` to select queries
- `app/Http/Controllers/Api/V1/RegionController.php` - Added `main_image` to select queries
- `app/Http/Controllers/Api/V1/IslandController.php` - Added `main_image` to select queries
- `app/Http/Controllers/Api/V1/CityController.php` - Added `main_image` to select queries
- `app/Http/Resources/ContinentResource.php` - Added `main_image` to response
- `app/Http/Resources/RegionResource.php` - Added `main_image` to response
- `app/Http/Resources/IslandResource.php` - Added `main_image` to response
- `app/Http/Resources/CityResource.php` - Added `main_image` to response

---

### 4. Swagger/OpenAPI Documentation Updates

All Swagger schemas and controller annotations updated to reflect `main_image` field:

**Schema Files Updated:**
- `app/Swagger/Schemas/ContinentSchema.php` - Added `main_image` property
- `app/Swagger/Schemas/CountrySchema.php` - Updated `main_image` description (removed `image_path` reference)
- `app/Swagger/Schemas/RegionSchema.php` - Added `main_image` property
- `app/Swagger/Schemas/IslandSchema.php` - Added `main_image` property
- `app/Swagger/Schemas/CitySchema.php` - Added `main_image` property

**Controller Annotations Updated:**
- `app/Http/Controllers/Api/V1/ContinentController.php` - Updated index and show endpoint schemas
- `app/Http/Controllers/Api/V1/CountryController.php` - Updated examples to match actual data format

**Property Definition:**
```php
@OA\Property(
    property="main_image",
    type="string",
    nullable=true,
    description="Relative path to main image from most viewed beach",
    example="img/beaches/hr-13-k-istra/hr-13-k-istra-sl2.jpg"
)
```

---

### 5. API Response Examples

#### 5.1 Continent Response
```json
{
  "code": "EU",
  "name": "Europe",
  "slug": "europe",
  "main_image": "img/beaches/hr-13-k-istra/hr-13-k-istra-sl2.jpg",
  "beaches_count": 2876
}
```

#### 5.2 Country Response
```json
{
  "code": "HR",
  "name": "Croatia",
  "full_name": "Croatia",
  "slug": "croatia",
  "iso3": "HRV",
  "continent_code": "EU",
  "main_image": "img/beaches/hr-13-k-istra/hr-13-k-istra-sl2.jpg",
  "beaches_count": 1167
}
```

#### 5.3 Region/Island/City Response
```json
{
  "id": 2,
  "code": "dalmatia-dubrovnik",
  "name": "Dalmatia - Dubrovnik",
  "slug": "dalmatia-dubrovnik",
  "country_code": "HR",
  "main_image": "img/beaches/hr-766-k-jdalmacija/hr-766-k-jdalmacija-sl1.jpg",
  "beaches_count": 166
}
```

---

### 6. Frontend Integration Notes

**Image URL Construction:**
- API returns relative paths (e.g., `img/beaches/hr-13-k-istra/hr-13-k-istra-sl2.jpg`)
- Frontend should prepend CDN domain: `https://beachrex.fra1.digitaloceanspaces.com/public/`
- Full URL: `https://beachrex.fra1.digitaloceanspaces.com/public/img/beaches/hr-13-k-istra/hr-13-k-istra-sl2.jpg`

**Imagor Integration:**
- Can use Imagor for image transformation
- Prepend Imagor domain instead of CDN domain
- Example: `http://localhost:8001/unsafe/fit-in/400x300/filters:quality(85)/img/beaches/hr-13-k-istra/hr-13-k-istra-sl2.jpg`

**Null Handling:**
- `main_image` is nullable
- Some entities may not have images if they don't have beaches or beaches don't have images
- Frontend should handle null values gracefully with placeholder images

**TypeScript Types:**
- All geographic entity types should include `main_image?: string | null`
- Can be auto-generated from updated Swagger/OpenAPI schema

---

## 2025-11-29 – Geographic Entity Navigation and Image Upload

### 1. Overview

**Context**
- Added `main_image` field to all geographic entities (regions, islands, cities, continents)
- Implemented S3/DigitalOcean Spaces image upload in admin panel for all geographic entities
- Added navigation (previous/next) to all geographic entity detail endpoints
- Navigation only includes entities that have beaches with `status_id = 1`
- All navigation structures now consistent across all entity types

**Benefits**
- ✅ Consistent image handling across all geographic entities
- ✅ S3-based image storage with Filament dropzone upload
- ✅ Navigation allows browsing through entities with beaches
- ✅ Full Swagger/OpenAPI documentation for all navigation structures
- ✅ Frontend can generate TypeScript types from OpenAPI schema

---

### 2. Database Changes

#### 2.1 New Fields Added

**Migration:** `database/migrations/2025_11_29_120000_add_main_image_to_geographic_entities.php`

Added `main_image` field to:
- `city` table - VARCHAR(255), nullable
- `region` table - VARCHAR(255), nullable
- `island` table - VARCHAR(255), nullable
- `continents` table - VARCHAR(255), nullable

**Note:** Countries already had `image_path` field, which is aliased to `main_image` in API responses for consistency.

**Model Updates:**
- `app/Models/City.php` - Added `main_image` to fillable
- `app/Models/Region.php` - Added `main_image` to fillable
- `app/Models/Island.php` - Added `main_image` to fillable
- `app/Models/Continent.php` - Added `main_image` to fillable

---

### 3. Admin Panel - Image Upload

All geographic entity admin resources now have S3-based image upload with Filament FileUpload component:

**Files Updated:**
- `app/Filament/BrAdmin/Resources/CountryResource.php` - Updated to use S3 (`disk('spaces')`)
- `app/Filament/BrAdmin/Resources/RegionResource.php` - Added image upload field
- `app/Filament/BrAdmin/Resources/IslandResource.php` - Added image upload field
- `app/Filament/BrAdmin/Resources/CityResource.php` - Added image upload field
- `app/Filament/BrAdmin/Resources/ContinentResource.php` - Added image upload field

**Configuration:**
- Disk: `spaces` (DigitalOcean Spaces)
- Directories:
  - Countries: `public/img/countries/`
  - Regions: `public/img/regions/`
  - Islands: `public/img/islands/`
  - Cities: `public/img/cities/`
  - Continents: `public/img/continents/`
- Image editor with aspect ratios: null, 16:9, 4:3, 1:1
- Max file size: 10MB
- Visibility: public
- Features: downloadable, openable, previewable

---

### 4. Navigation Implementation

#### 4.1 Navigation Logic

All geographic entity detail endpoints now include navigation (previous/next) in their responses:

**Endpoints with Navigation:**
- `GET /api/v1/continents/{code}` - Navigate between continents with beaches
- `GET /api/v1/countries/{code}` - Navigate between countries with beaches in same continent
- `GET /api/v1/regions/{slug}` - Navigate between regions with beaches in same country
- `GET /api/v1/islands/{slug}` - Navigate between islands with beaches in same region/country
- `GET /api/v1/cities/{slug}` - Navigate between cities with beaches in same island/region/country

**Navigation Filtering:**
All navigation queries use `EXISTS` subqueries to ensure only entities with active beaches (`status_id = 1`) are included:

- **Countries**: Only countries that have beaches with `status_id = 1`
- **Regions**: Only regions that have beaches with `status_id = 1`
- **Islands**: Only islands that have beaches with `status_id = 1`
- **Cities**: Only cities that have beaches with `status_id = 1`
- **Continents**: Only continents that have countries with beaches with `status_id = 1`

**Ordering:**
- Countries: Ordered by `code` within same continent
- Regions: Ordered by `id` within same country
- Islands: Ordered by `id` within same region (or country if no region)
- Cities: Ordered by `id` within same island/region/country
- Continents: Ordered by `code`

#### 4.2 Navigation Response Structure

All navigation responses follow the same structure with entity-specific fields and parent location slugs:

**Country Navigation:**
```json
{
  "navigation": {
    "previous": {
      "code": "HR",
      "name": "Croatia",
      "slug": "croatia",
      "latitude": "45.1",
      "longitude": "15.2",
      "main_image": "img/countries/croatia.jpg",
      "location": {
        "continent_slug": "europe"
      }
    },
    "next": { /* same structure */ }
  }
}
```

**Region Navigation:**
```json
{
  "navigation": {
    "previous": {
      "id": 1,
      "name": "Istria",
      "slug": "istria",
      "latitude": "45.1",
      "longitude": "13.9",
      "main_image": "img/regions/istria.jpg",
      "location": {
        "continent_slug": "europe",
        "country_slug": "croatia"
      }
    },
    "next": { /* same structure */ }
  }
}
```

**Island Navigation:**
```json
{
  "navigation": {
    "previous": {
      "id": 1,
      "name": "Krk",
      "slug": "krk",
      "latitude": "45.0",
      "longitude": "14.6",
      "main_image": "img/islands/krk.jpg",
      "location": {
        "continent_slug": "europe",
        "country_slug": "croatia",
        "region_slug": "kvarner"
      }
    },
    "next": { /* same structure */ }
  }
}
```

**City Navigation:**
```json
{
  "navigation": {
    "previous": {
      "id": 1,
      "name": "Split",
      "slug": "split",
      "latitude": "43.5",
      "longitude": "16.4",
      "main_image": "img/cities/split.jpg",
      "location": {
        "continent_slug": "europe",
        "country_slug": "croatia",
        "region_slug": "dalmatia",
        "island_slug": null
      }
    },
    "next": { /* same structure */ }
  }
}
```

**Continent Navigation:**
```json
{
  "navigation": {
    "previous": {
      "code": "AS",
      "name": "Asia",
      "slug": "asia",
      "main_image": "img/continents/asia.jpg",
      "location": {}
    },
    "next": { /* same structure */ }
  }
}
```

---

### 5. Controller Updates

All geographic entity controllers updated with navigation methods:

**Files Modified:**
- `app/Http/Controllers/Api/V1/ContinentController.php`
  - Added `getNavigationContinents()` method
  - Navigation filters by continents with beaches
  - SQL queries include `main_image` field

- `app/Http/Controllers/Api/V1/CountryController.php`
  - Updated `getNavigationCountries()` method
  - Navigation filters by countries with beaches in same continent
  - SQL queries include `main_image` field (aliased from `image_path`)

- `app/Http/Controllers/Api/V1/RegionController.php`
  - Added `getNavigationRegions()` method
  - Navigation filters by regions with beaches in same country
  - SQL queries include `main_image` field

- `app/Http/Controllers/Api/V1/IslandController.php`
  - Added `getNavigationIslands()` method
  - Navigation filters by islands with beaches in same region/country
  - SQL queries include `main_image` field

- `app/Http/Controllers/Api/V1/CityController.php`
  - Added `getNavigationCities()` method
  - Navigation filters by cities with beaches in same island/region/country
  - SQL queries include `main_image` field

---

### 6. Swagger/OpenAPI Documentation

All geographic entity show endpoints now have complete navigation documentation:

**Files Updated:**
- `app/Http/Controllers/Api/V1/ContinentController.php` - Added navigation schema to show endpoint
- `app/Http/Controllers/Api/V1/CountryController.php` - Updated navigation schema with `main_image`
- `app/Http/Controllers/Api/V1/RegionController.php` - Added navigation schema to show endpoint
- `app/Http/Controllers/Api/V1/IslandController.php` - Added navigation schema to show endpoint
- `app/Http/Controllers/Api/V1/CityController.php` - Added navigation schema to show endpoint

**Schema Updates:**
- `app/Swagger/Schemas/CountrySchema.php` - Changed `image_path` to `main_image`

Each navigation schema includes:
- Complete structure with all fields (id/code, name, slug, latitude, longitude, main_image)
- Location object with parent slugs
- Nullable properties where appropriate
- Examples for all fields

---

### 7. Deployment Instructions

#### 7.1 Development Environment

```bash
# Run migration
./vendor/bin/sail artisan migrate

# Clear cache
./vendor/bin/sail artisan cache:clear

# Regenerate Swagger docs
./vendor/bin/sail artisan l5-swagger:generate
```

#### 7.2 Production Server

```bash
# Run migration
php artisan migrate

# Clear cache
php artisan cache:clear

# Regenerate Swagger docs
php artisan l5-swagger:generate
```

---

### 8. Frontend Integration

#### 8.1 TypeScript Type Generation

The frontend can generate TypeScript types for all navigation structures from the OpenAPI schema:

```bash
# Generate types from Swagger/OpenAPI
npx openapi-typescript https://api.beachrex.com/api/documentation/json -o src/lib/api-client/types.gen.ts
```

This will include updated types for:
- `ContinentResponse` with navigation
- `CountryResponse` with navigation
- `RegionResponse` with navigation
- `IslandResponse` with navigation
- `CityResponse` with navigation

#### 8.2 Example Usage

```typescript
import { apiClient } from '@/lib/api-client';

// Get country with navigation
const country = await apiClient.GET('/api/v1/countries/{code}', {
  params: {
    path: { code: 'HR' }
  },
  headers: {
    'Accept-Language': 'en'
  }
});

// Access navigation
const previousCountry = country.data?.navigation?.previous;
const nextCountry = country.data?.navigation?.next;

// Get region with navigation
const region = await apiClient.GET('/api/v1/regions/{slug}', {
  params: {
    path: { slug: 'dalmatia' },
    query: { country_code: 'HR' }
  },
  headers: {
    'Accept-Language': 'en'
  }
});
```

#### 8.3 Image URL Construction

All `main_image` fields are relative paths. Frontend should prepend CDN/base URL:

```typescript
// For geographic entity images
const imagorEndpoint = process.env.NEXT_PUBLIC_IMAGOR_ENDPOINT; // "https://s3.beachrex.com"
const imageUrl = `${imagorEndpoint}/unsafe/fit-in/800x600/filters:quality(90)/${entity.main_image}`;
// Result: https://s3.beachrex.com/unsafe/fit-in/800x600/filters:quality(90)/img/countries/croatia.jpg
```

---

### 9. Testing

#### 9.1 Test Navigation Endpoints

```bash
# Continent navigation
curl -H "Accept-Language: en" https://api.beachrex.com/api/v1/continents/EU

# Country navigation
curl -H "Accept-Language: en" https://api.beachrex.com/api/v1/countries/HR

# Region navigation
curl -H "Accept-Language: en" https://api.beachrex.com/api/v1/regions/dalmatia?country_code=HR

# Island navigation
curl -H "Accept-Language: en" https://api.beachrex.com/api/v1/islands/hvar

# City navigation
curl -H "Accept-Language: en" https://api.beachrex.com/api/v1/cities/split?country_code=HR
```

#### 9.2 Verify Navigation Filtering

All navigation should only include entities with beaches:

```sql
-- Verify countries in navigation have beaches
SELECT c.code, c.name, COUNT(b.id) as beach_count
FROM countries c
LEFT JOIN beach b ON b.country_code = c.code AND b.status_id = 1
GROUP BY c.code, c.name
HAVING COUNT(b.id) > 0
ORDER BY c.code;

-- Verify regions in navigation have beaches
SELECT r.id, r.code, COUNT(b.id) as beach_count
FROM region r
LEFT JOIN beach b ON b.region_id = r.id AND b.status_id = 1
GROUP BY r.id, r.code
HAVING COUNT(b.id) > 0
ORDER BY r.id;
```

---

### 10. Notes

- **Image Field Naming**: Countries use `image_path` in database but return `main_image` in API for consistency
- **Navigation Filtering**: All navigation only includes entities with active beaches (`status_id = 1`)
- **Null Values**: Navigation can be null if no previous/next entity exists
- **Location Object**: Contains only parent slugs (no child slugs)
- **Caching**: Navigation data is cached with entity details using `CacheService::REFERENCE_DATA_TTL`

---

## 2025-11-29 – Weather Endpoints for Geographic Entities

### 1. Overview

**Context**
- Extended weather functionality from beaches to all geographic entities (cities, regions, islands, countries)
- Added latitude/longitude fields to geographic entity tables
- Created seeder to populate coordinates from beach centroids
- All entities now support weather API endpoints following the same pattern as beaches

**Benefits**
- ✅ Consistent weather data across all geographic levels
- ✅ Weather endpoints for cities, regions, islands, and countries
- ✅ Coordinates calculated automatically from beach centroids
- ✅ Full Swagger/OpenAPI documentation for all new endpoints
- ✅ Frontend can generate TypeScript types from OpenAPI schema

---

### 2. Database Changes

#### 2.1 New Fields Added

**Migration:** `database/migrations/2025_11_29_000000_add_coordinates_to_geographic_entities.php`

Added `latitude` and `longitude` fields to:
- `city` table - DECIMAL(10,7), nullable, with index
- `region` table - DECIMAL(10,7), nullable, with index
- `island` table - DECIMAL(10,7), nullable, with index
- `countries` table - DECIMAL(10,7), nullable, with index

**Model Updates:**
- `app/Models/City.php` - Added `latitude`, `longitude` to fillable
- `app/Models/Region.php` - Added `latitude`, `longitude` to fillable
- `app/Models/Island.php` - Added `latitude`, `longitude` to fillable
- `app/Models/Country.php` - Added `latitude`, `longitude` to fillable

#### 2.2 Coordinate Population

**Seeder:** `database/seeders/GeographicEntityCoordinatesSeeder.php`

Populates coordinates by calculating centroid (average) from beaches:
- **Cities**: Average of all beaches where `beach.city_id = city.id`
- **Islands**: Average of all beaches where `beach.island_id = island.id`
- **Regions**: Average of all beaches in cities within the region (joins through city table)
- **Countries**: Average of all beaches where `beach.country_code = countries.code`

**Features:**
- Only uses active beaches (`status_id = 1`)
- Rounds coordinates to 7 decimal places (~1.1cm precision)
- Skips entities where coordinates are already up-to-date
- Provides detailed feedback on updated vs skipped entities
- Safe to run multiple times

**Documentation:** `database/seeders/README_GEOGRAPHIC_COORDINATES.md`

---

### 3. New API Endpoints

All endpoints follow the same pattern as beach weather endpoint, using the `ProvidesWeather` trait.

#### 3.1 City Weather

**Endpoint:** `GET /api/v1/cities/{slug}/weather`

**Parameters:**
- `slug` (path, required) - City slug (e.g., "split", "dubrovnik")
- `country_code` (query, optional) - Filter by country code to disambiguate cities with same name
- `Accept-Language` (header) - Locale for response

**Response:**
```json
{
  "success": true,
  "data": {
    "city_id": 1,
    "city_slug": "split",
    "coordinates": {
      "latitude": "43.5081000",
      "longitude": "16.4402000"
    },
    "weather": {
      "temperature": 9.9,
      "feels_like": 8.7,
      "condition": "Clouds",
      "description": "broken clouds",
      "icon": "04d",
      "icon_url": "https://openweathermap.org/img/wn/04d@2x.png",
      "wind_speed": 2.52,
      "humidity": 58,
      ...
    },
    "sea_temperature": 9.9,
    "cached_at": "2025-11-29T10:20:47+00:00"
  }
}
```

#### 3.2 Region Weather

**Endpoint:** `GET /api/v1/regions/{slug}/weather`

**Parameters:**
- `slug` (path, required) - Region slug (e.g., "dalmatia", "istria")
- `country_code` (query, optional) - Filter by country code
- `Accept-Language` (header) - Locale for response

**Response:** Same structure as city weather, with `region_id` and `region_slug`

#### 3.3 Island Weather

**Endpoint:** `GET /api/v1/islands/{slug}/weather`

**Parameters:**
- `slug` (path, required) - Island slug (e.g., "hvar", "krk", "brac")
- `Accept-Language` (header) - Locale for response

**Response:** Same structure as city weather, with `island_id` and `island_slug`

#### 3.4 Country Weather

**Endpoint:** `GET /api/v1/countries/{code}/weather`

**Parameters:**
- `code` (path, required) - Country code (e.g., "HR", "SI", "IT")
- `Accept-Language` (header) - Locale for response

**Response:** Same structure as city weather, with `country_id` and `country_slug`

---

### 4. Swagger/OpenAPI Schema Updates

#### 4.1 Entity Schemas Updated

Added `latitude` and `longitude` fields to all geographic entity schemas:

**Files Updated:**
- `app/Swagger/Schemas/CitySchema.php`
- `app/Swagger/Schemas/RegionSchema.php`
- `app/Swagger/Schemas/IslandSchema.php`
- `app/Swagger/Schemas/CountrySchema.php`

**New Properties:**
```php
@OA\Property(
    property="latitude",
    type="number",
    format="double",
    nullable=true,
    description="Entity latitude (centroid of beaches)",
    example=43.5081
),
@OA\Property(
    property="longitude",
    type="number",
    format="double",
    nullable=true,
    description="Entity longitude (centroid of beaches)",
    example=16.4402
)
```

#### 4.2 Weather Endpoint Documentation

All four controllers have full OpenAPI annotations:
- `app/Http/Controllers/Api/V1/CityController.php` - `weather()` method
- `app/Http/Controllers/Api/V1/RegionController.php` - `weather()` method
- `app/Http/Controllers/Api/V1/IslandController.php` - `weather()` method
- `app/Http/Controllers/Api/V1/CountryController.php` - `weather()` method

Each includes:
- Complete parameter documentation
- Response schemas with weather data structure
- Error responses (404, 400, 503)
- Examples for all fields

---

### 5. Implementation Details

#### 5.1 Controllers Updated

All controllers now use the `ProvidesWeather` trait:

```php
use App\Traits\ProvidesWeather;

class CityController extends Controller
{
    use CacheAware, ProvidesWeather;

    public function weather(string $slug, Request $request): JsonResponse
    {
        // Find entity by slug
        // Get entity slug in current locale
        // Use ProvidesWeather trait to build response
        return $this->buildWeatherResponse($entity, 'city', $entitySlug);
    }
}
```

#### 5.2 Routes Added

**File:** `routes/api.php`

```php
// City weather
Route::get('/cities/{slug}/weather', [CityController::class, 'weather']);

// Region weather
Route::get('/regions/{slug}/weather', [RegionController::class, 'weather']);

// Island weather
Route::get('/islands/{slug}/weather', [IslandController::class, 'weather']);

// Country weather
Route::get('/countries/{code}/weather', [CountryController::class, 'weather']);
```

---

### 6. Deployment Instructions

#### 6.1 Development Environment

```bash
# Run migration
./vendor/bin/sail artisan migrate

# Populate coordinates from beaches
./vendor/bin/sail artisan db:seed --class=GeographicEntityCoordinatesSeeder

# Clear cache
./vendor/bin/sail artisan cache:clear

# Regenerate Swagger docs
./vendor/bin/sail artisan l5-swagger:generate
```

#### 6.2 Production Server

```bash
# Run migration
php artisan migrate

# Populate coordinates from beaches
php artisan db:seed --class=GeographicEntityCoordinatesSeeder

# Clear cache
php artisan cache:clear

# Regenerate Swagger docs (if needed)
php artisan l5-swagger:generate
```

---

### 7. Frontend Integration

#### 7.1 TypeScript Type Generation

The frontend can now generate TypeScript types for all weather endpoints from the OpenAPI schema:

```bash
# Generate types from Swagger/OpenAPI
npx openapi-typescript https://api.beachrex.com/api/documentation/json -o src/lib/api-client/types.gen.ts
```

This will include types for:
- `CityWeatherResponse`
- `RegionWeatherResponse`
- `IslandWeatherResponse`
- `CountryWeatherResponse`
- Updated `City`, `Region`, `Island`, `Country` schemas with `latitude` and `longitude` fields

#### 7.2 Example Usage

```typescript
import { apiClient } from '@/lib/api-client';

// Get city weather
const cityWeather = await apiClient.GET('/api/v1/cities/{slug}/weather', {
  params: {
    path: { slug: 'split' },
    query: { country_code: 'HR' }
  },
  headers: {
    'Accept-Language': 'en'
  }
});

// Get region weather
const regionWeather = await apiClient.GET('/api/v1/regions/{slug}/weather', {
  params: {
    path: { slug: 'dalmatia' }
  },
  headers: {
    'Accept-Language': 'en'
  }
});

// Get island weather
const islandWeather = await apiClient.GET('/api/v1/islands/{slug}/weather', {
  params: {
    path: { slug: 'hvar' }
  },
  headers: {
    'Accept-Language': 'en'
  }
});

// Get country weather
const countryWeather = await apiClient.GET('/api/v1/countries/{code}/weather', {
  params: {
    path: { code: 'HR' }
  },
  headers: {
    'Accept-Language': 'en'
  }
});
```

---

### 8. Testing

#### 8.1 Test Endpoints

```bash
# City weather
curl -H "Accept-Language: en" https://api.beachrex.com/api/v1/cities/split/weather

# Region weather
curl -H "Accept-Language: en" https://api.beachrex.com/api/v1/regions/dalmatia/weather

# Island weather
curl -H "Accept-Language: en" https://api.beachrex.com/api/v1/islands/hvar/weather

# Country weather
curl -H "Accept-Language: en" https://api.beachrex.com/api/v1/countries/HR/weather
```

#### 8.2 Verify Coordinates

```sql
-- Check cities with coordinates
SELECT id, code, latitude, longitude
FROM city
WHERE latitude IS NOT NULL
ORDER BY id LIMIT 10;

-- Check regions with coordinates
SELECT id, code, latitude, longitude
FROM region
WHERE latitude IS NOT NULL
ORDER BY id;

-- Check islands with coordinates
SELECT id, code, latitude, longitude
FROM island
WHERE latitude IS NOT NULL
ORDER BY id LIMIT 10;

-- Check countries with coordinates
SELECT id, code, latitude, longitude
FROM countries
WHERE latitude IS NOT NULL
ORDER BY code;
```

---

### 9. Error Handling

All weather endpoints return consistent error responses:

**404 - Entity Not Found:**
```json
{
  "success": false,
  "message": "City not found"
}
```

**400 - No Coordinates:**
```json
{
  "success": false,
  "message": "City has no coordinates for weather data"
}
```

**503 - Weather Service Unavailable:**
```json
{
  "success": false,
  "message": "Weather data temporarily unavailable"
}
```

---

### 10. Caching

Weather data is cached for 1 hour (same as beach weather):
- Cache key includes entity type, ID, and coordinates
- Coordinates rounded to 2 decimals for cache key consistency
- Cache cleared automatically after 1 hour
- Manual cache clear: `php artisan cache:clear`

---

### 11. Notes

- **Centroid Calculation**: Coordinates represent the geographic center of all active beaches within each entity
- **Null Coordinates**: Entities without beaches (or beaches without coordinates) will have NULL coordinates and return 400 error
- **Coordinate Precision**: 7 decimal places (~1.1cm precision) - more than sufficient for weather data
- **Active Beaches Only**: Only beaches with `status_id = 1` are included in centroid calculation
- **Re-running Seeder**: Safe to run multiple times - skips entities where coordinates haven't changed

---

## 2025-11-28 – Image Handling Simplified (Imagor Removed from Backend)

### 1. Overview

**Context**
- **Removed all Imagor-related code from Laravel backend**
- Frontend now handles all image transformations directly via Imagor subdomain (`s3.beachrex.com`)
- Backend only returns relative image paths (`main_image`)
- Significantly reduced API response sizes and memory usage

**Benefits**
- ✅ Simpler backend architecture (no Imagor service dependencies)
- ✅ Smaller API responses (removed `description` and `main_image_imagor` fields from list endpoints)
- ✅ Reduced memory usage when caching (fixed memory exhaustion errors)
- ✅ Frontend has full control over image transformations
- ✅ Easier to maintain and deploy

---

### 2. API Response Changes

#### 2.1 List Endpoints (Beaches, Spatial APIs)

**Affected Endpoints:**
- `GET /api/v1/beaches`
- `GET /api/v1/beaches/{id}`
- `GET /api/v1/spatial/beaches/within-radius`
- `GET /api/v1/spatial/beaches/bounding-box`

**Fields Removed:**
- ❌ `description` - Removed from list endpoints to reduce memory usage when caching
- ❌ `main_image_imagor` - Removed, frontend constructs Imagor URLs directly

**Current Response:**
```json
{
  "id": 1,
  "name": "Beach Livade i Obatnice",
  "slug": "beach-livade-i-obatnice",
  "latitude": "44.5996000",
  "longitude": "14.9876000",
  "country_code": "HR",
  "main_image": "img/beaches/hr-69-o-pag/hr-69-o-pag-sl7.jpg"
}
```

**Frontend Usage:**
```typescript
// Frontend constructs Imagor URLs
const imagorEndpoint = process.env.NEXT_PUBLIC_IMAGOR_ENDPOINT; // "https://s3.beachrex.com"
const imagorTransform = process.env.NEXT_PUBLIC_IMAGOR_TRANSFORM; // "unsafe/fit-in/400x300/filters:quality(85)/"
const imageUrl = `${imagorEndpoint}/${imagorTransform}${beach.main_image}`;
// Result: https://s3.beachrex.com/unsafe/fit-in/400x300/filters:quality(85)/img/beaches/hr-69-o-pag/hr-69-o-pag-sl7.jpg
```

---

#### 2.2 Detail Endpoints (Beach Details)

**Affected Endpoints:**
- `GET /api/v1/beach-details/{identifier}`

**Fields Removed:**
- ❌ `imagor` object - Removed from media items, frontend constructs Imagor URLs directly

**Current Response:**
```json
{
  "media": {
    "gallery": [
      {
        "id": 2,
        "file_name": "hr-69-o-pag-sl1.jpg",
        "url": "img/beaches/hr-69-o-pag/hr-69-o-pag-sl1.jpg",
        "mime_type": "image/jpeg",
        "size": 245678,
        "width": 1920,
        "height": 1080
      }
    ]
  }
}
```

**Frontend Usage:**
```typescript
// Frontend constructs different sizes as needed
const imagorEndpoint = process.env.NEXT_PUBLIC_IMAGOR_ENDPOINT; // "https://s3.beachrex.com"

// Thumbnail
const thumbnailUrl = `${imagorEndpoint}/unsafe/fit-in/150x150/smart/filters:quality(85)/${media.url}`;

// Small
const smallUrl = `${imagorEndpoint}/unsafe/fit-in/400x300/filters:quality(85)/${media.url}`;

// Medium
const mediumUrl = `${imagorEndpoint}/unsafe/fit-in/800x600/filters:quality(90)/${media.url}`;

// Large
const largeUrl = `${imagorEndpoint}/unsafe/fit-in/1200x900/filters:quality(90)/${media.url}`;

// Hero/Banner
const heroUrl = `${imagorEndpoint}/unsafe/1920x600/smart/filters:quality(90)/${media.url}`;
```

---

### 3. Implementation Details

#### 3.1 Backend Files Deleted

**Services:**
- ❌ `app/Services/ImagorService.php` - Deleted (no longer needed)

**Controllers:**
- ❌ `app/Http/Controllers/Api/ImagorController.php` - Deleted (no longer needed)

**Configuration:**
- ❌ `config/imagor.php` - Deleted (no longer needed)

#### 3.2 Backend Files Modified

**Models:**
- `app/Models/Media.php`
  - ❌ Removed all Imagor methods: `imagorUrl()`, `imagorThumbnail()`, `imagorSmall()`, `imagorMedium()`, `imagorLarge()`, `imagorHero()`

**Resources:**
- `app/Http/Resources/BeachResource.php`
  - ❌ Removed `main_image_imagor` field
  - ❌ Removed `description` field (to reduce memory usage)
- `app/Http/Resources/BeachDetailsResource.php`
  - ❌ Removed `imagor` object from media items
- `app/Http/Resources/MediaResource.php`
  - ❌ Removed `imagor` property

**Services:**
- `app/Services/PostGISService.php`
  - ❌ Removed `main_image_imagor` field generation

**Routes:**
- `routes/api.php`
  - ❌ Removed all Imagor routes (`/api/v1/imagor/*`)

**Swagger:**
- `app/Swagger/Schemas/BeachSchema.php`
  - ✅ Updated to reflect `main_image` only (no `main_image_imagor`)
- `app/Swagger/Schemas/BeachDetailsSchema.php`
  - ✅ Removed `imagor` object from media schema

---

### 4. Imagor URL Format (Frontend Reference)

**Unsigned URLs:**
```
https://s3.beachrex.com/unsafe/{transformations}/{image-path}
```

**Transformation Examples:**
- `fit-in/400x300` - Resize to fit within 400x300 (maintains aspect ratio)
- `400x300` - Exact dimensions (may crop)
- `smart` - Smart crop (detects important areas)
- `filters:quality(85)` - JPEG quality 85%
- `filters:format(webp)` - Convert to WebP format

**Example URLs:**
```
# Thumbnail
https://s3.beachrex.com/unsafe/fit-in/150x150/smart/filters:quality(85)/img/beaches/hr-69-o-pag/hr-69-o-pag-sl1.jpg

# Small
https://s3.beachrex.com/unsafe/fit-in/400x300/filters:quality(85)/img/beaches/hr-69-o-pag/hr-69-o-pag-sl1.jpg

# Medium
https://s3.beachrex.com/unsafe/fit-in/800x600/filters:quality(90)/img/beaches/hr-69-o-pag/hr-69-o-pag-sl1.jpg

# Large
https://s3.beachrex.com/unsafe/fit-in/1200x900/filters:quality(90)/img/beaches/hr-69-o-pag/hr-69-o-pag-sl1.jpg

# Hero/Banner
https://s3.beachrex.com/unsafe/1920x600/smart/filters:quality(90)/img/beaches/hr-69-o-pag/hr-69-o-pag-sl1.jpg
```

---

### 5. Environment Configuration

**Laravel API `.env`** (NO Imagor configuration needed):
```env
# ❌ IMAGOR_URL - Removed (not needed)
# ❌ IMAGOR_SECRET - Removed (not needed)
# ❌ IMAGOR_UNSAFE - Removed (not needed)
```

**Frontend `.env`** (Next.js on Vercel):
```env
# Imagor endpoint (subdomain pointing to Imagor service)
NEXT_PUBLIC_IMAGOR_ENDPOINT=https://s3.beachrex.com

# Optional: Default transformation for list views
NEXT_PUBLIC_IMAGOR_TRANSFORM=unsafe/fit-in/400x300/filters:quality(85)/
```

**Imagor Service** (DigitalOcean App Platform):
Imagor runs as a separate service at `https://beachrex-imagor-xadoz.ondigitalocean.app` with subdomain `s3.beachrex.com` pointing to it.

Configuration (on DigitalOcean App Platform):
- `PORT=8080`
- `IMAGOR_UNSAFE=1` (unsigned URLs enabled)
- `IMAGOR_AUTO_WEBP=1` (automatic WebP conversion)
- `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY` (for DigitalOcean Spaces)
- `S3_ENDPOINT=fra1.digitaloceanspaces.com`
- `S3_LOADER_BUCKET=beachrex`
- `S3_RESULT_STORAGE_BUCKET=beachrex`

---

### 6. Swagger Documentation Updates

**Updated Schemas:**
- `app/Swagger/Schemas/BeachSchema.php`
  - ✅ Updated `main_image` description to explain frontend constructs Imagor URLs
  - ❌ Removed `main_image_imagor` property
- `app/Swagger/Schemas/BeachDetailsSchema.php`
  - ❌ Removed `imagor` object from MediaItem schema

---

### 7. Performance Impact

**Before (with Imagor URLs in API):**
- List endpoint with 100 beaches: ~54KB (with `main_image_imagor`)
- Memory exhaustion errors when caching 5000+ beaches with descriptions

**After (Imagor removed, description removed):**
- List endpoint with 100 beaches: ~45KB (smaller, no `main_image_imagor`, no `description`)
- ✅ No more memory exhaustion errors
- ✅ Caching works reliably with 5000+ beaches

---

### 8. Frontend Integration Guide

**Step 1: Set Environment Variables**
```env
# Imagor endpoint (subdomain pointing to Imagor service)
NEXT_PUBLIC_IMAGOR_ENDPOINT=https://s3.beachrex.com

# Optional: Default transformation for list views
NEXT_PUBLIC_IMAGOR_TRANSFORM=unsafe/fit-in/400x300/filters:quality(85)/
```

**Step 2: Construct Imagor URLs in Frontend**
```typescript
// For list views (beaches, spatial results)
const imagorEndpoint = process.env.NEXT_PUBLIC_IMAGOR_ENDPOINT;
const imagorTransform = process.env.NEXT_PUBLIC_IMAGOR_TRANSFORM;
const imageUrl = `${imagorEndpoint}/${imagorTransform}${beach.main_image}`;
// Result: https://s3.beachrex.com/unsafe/fit-in/400x300/filters:quality(85)/img/beaches/hr-69-o-pag/hr-69-o-pag-sl7.jpg

// For detail views (beach details) - construct different sizes
const thumbnailUrl = `${imagorEndpoint}/unsafe/fit-in/150x150/smart/filters:quality(85)/${media.url}`;
const smallUrl = `${imagorEndpoint}/unsafe/fit-in/400x300/filters:quality(85)/${media.url}`;
const mediumUrl = `${imagorEndpoint}/unsafe/fit-in/800x600/filters:quality(90)/${media.url}`;
const largeUrl = `${imagorEndpoint}/unsafe/fit-in/1200x900/filters:quality(90)/${media.url}`;
const heroUrl = `${imagorEndpoint}/unsafe/1920x600/smart/filters:quality(90)/${media.url}`;
```

**Step 3: Create Reusable Helper Function**
```typescript
// utils/imagor.ts
export const getImagorUrl = (
  imagePath: string,
  size: 'thumbnail' | 'small' | 'medium' | 'large' | 'hero' = 'small'
): string => {
  const endpoint = process.env.NEXT_PUBLIC_IMAGOR_ENDPOINT;

  const transformations = {
    thumbnail: 'unsafe/fit-in/150x150/smart/filters:quality(85)',
    small: 'unsafe/fit-in/400x300/filters:quality(85)',
    medium: 'unsafe/fit-in/800x600/filters:quality(90)',
    large: 'unsafe/fit-in/1200x900/filters:quality(90)',
    hero: 'unsafe/1920x600/smart/filters:quality(90)',
  };

  return `${endpoint}/${transformations[size]}/${imagePath}`;
};

// Usage
const imageUrl = getImagorUrl(beach.main_image, 'small');
```

---

### 9. Testing

**Test Direct Imagor URLs:**
```bash
# Direct Imagor URL (via subdomain)
curl "https://s3.beachrex.com/unsafe/fit-in/400x300/filters:quality(85)/img/beaches/hr-69-o-pag/hr-69-o-pag-sl7.jpg"
```

**Test API Responses:**
```bash
# List endpoint - verify main_image is present (no main_image_imagor)
curl "https://dev-api.beachrex.com/api/v1/beaches?limit=10" | jq '.data[0] | {id, name, main_image}'

# Detail endpoint - verify media has url (no imagor object)
curl "https://dev-api.beachrex.com/api/v1/beach-details/beach-livade-i-obatnice" | jq '.data.media.gallery[0] | {id, file_name, url}'

# Spatial endpoint - verify main_image is present
curl "https://dev-api.beachrex.com/api/v1/spatial/beaches/within-radius?latitude=44.5&longitude=14.5&radius=50&limit=3" | jq '.data[0] | {id, name, main_image}'
```

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

---

### 4. Fixed Escaped Quotes in Beach Translations

**Context**
- Beach translation data (names, descriptions, slugs) contained literal backslash characters escaping apostrophes.
- Example: Database had `Beach d\'en Perris` instead of `Beach d'en Perris`.
- This caused the API to return double-escaped strings like `Beach d\\'en Perris`.
- The issue affected 366 records across name and description fields.

**Root Cause**
- Data was incorrectly inserted with SQL-escaped strings stored as literal values.
- The backslash-apostrophe sequence (`\'`) was stored in the database instead of just the apostrophe (`'`).

**Database Tables Affected**
- `waterbody.icon` - 4 records fixed
- `beach_type.icon` - 8 records fixed
- `user_type.icon` - 3 records fixed

**Code Files Affected**
- `app/Http/Resources/BeachResource.php` - Removed hardcoded leading slash from `main_image` path (line 64)
- `app/Services/PostGISService.php` - Removed hardcoded leading slash from spatial API responses (line 480)

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

3. **PostGISService formatBeachResults** - Changed from:
   ```php
   // Ensure main_image starts with a forward slash
   $mainImage = null;
   if ($beach->main_image_path) {
       $mainImage = '/' . ltrim($beach->main_image_path, '/');
   }
   ```
   To:
   ```php
   // Return main_image path as-is (relative path without leading slash)
   $mainImage = $beach->main_image_path ?? null;
   ```
   This affects all spatial API endpoints:
   - `/api/v1/spatial/beaches/within-radius`
   - `/api/v1/spatial/beaches/bounding-box`
   - `/api/v1/spatial/beaches/nearest`

**Database Tables Affected**
- `beach_translation.name` - 105 records fixed
- `beach_translation.description` - 261 records fixed
- `beach_translation.slug` - 0 records (none had backslashes)

**Fix Applied**

Created migration: `database/migrations/2025_11_26_000002_fix_escaped_quotes_in_beach_translations.php`

The migration uses PostgreSQL's `CHR()` function and `REPLACE()` to fix the data:
```sql
UPDATE beach_translation
SET name = REPLACE(name, CHR(92) || CHR(39), CHR(39))
WHERE POSITION(CHR(92) IN name) > 0
```

This replaces `\'` (backslash + apostrophe) with just `'` (apostrophe).

**Deployment Instructions**

To apply all data quality fixes to development and production databases:

1. **Development:**
   ```bash
   ./vendor/bin/sail artisan migrate
   ./vendor/bin/sail artisan cache:clear
   ```

2. **Production:**
   ```bash
   php artisan migrate
   php artisan cache:clear
   ```

**Migrations to Run (in order):**
1. `2025_11_26_000001_remove_leading_slashes_from_icon_paths.php` - Fixes icon paths
2. `2025_11_26_000002_fix_escaped_quotes_in_beach_translations.php` - Fixes escaped quotes
3. `2025_11_26_000003_remove_double_spaces_from_beach_translations.php` - Removes double spaces

**Impact**

After running both migrations:
- All icon paths will be relative without leading slashes
- All beach translation names and descriptions will have proper apostrophes instead of escaped backslashes
- API responses will return clean, properly formatted text

---

### 5. Removed Double Spaces from Beach Translations

**Context**
- Some beach translation names and descriptions contained multiple consecutive spaces.
- Example: `Beach  Sveti Duh` (with double space) instead of `Beach Sveti Duh`.
- This affected 13 names and 58 descriptions.

**Database Tables Affected**
- `beach_translation.name` - 13 records fixed
- `beach_translation.description` - 58 records fixed
- `beach_translation.slug` - 0 records (none had double spaces)

**Fix Applied**

Created migration: `database/migrations/2025_11_26_000003_remove_double_spaces_from_beach_translations.php`

The migration uses PostgreSQL's `REGEXP_REPLACE()` to replace multiple consecutive spaces with a single space:
```sql
UPDATE beach_translation
SET name = REGEXP_REPLACE(name, ' {2,}', ' ', 'g')
WHERE name ~ ' {2,}'
```

This replaces any sequence of 2 or more spaces with a single space.

**Examples**

Before → After:
- `Beach  Sveti Duh` → `Beach Sveti Duh`
- `Beach  Aiguablava` → `Beach Aiguablava`
- `Beach Sa  Rocca Tunda` → `Beach Sa Rocca Tunda`
- `Platja  Albaranes` → `Platja Albaranes`

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

