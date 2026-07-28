# Beachrex AI Agent - Capability Alignment with Monetization

**Version**: 1.0  
**Last Updated**: 2025-12-22  
**Status**: Canonical - Aligns technical capabilities with business model  
**Input**: `docs/ai/product/ai-monetization.md` (Canonical Monetization Strategy)

---

## Document Purpose

This document maps AI technical capabilities to monetization strategy, ensuring:
1. **MVP focuses on revenue-generating features** (not nice-to-haves)
2. **Free tier capabilities drive conversion** (not just engagement)
3. **Enterprise features justify premium pricing** (clear ROI)
4. **Technical constraints protect monetization** (prevent revenue leakage)

---

## 1. AI Capabilities Required for B2C

### 1.1 Free Tier Capabilities (Conversion Drivers)

These capabilities are **100% free** and designed to drive users to paid conversion points.

#### Capability 1.1.1: Natural Language Search (CRITICAL)
**Purpose**: Let users discover beaches conversationally (table stakes)

**Technical Requirements**:
- Parse natural language queries into structured filters
- Support 40+ filter combinations (country, surface, facilities, activities, etc.)
- Return up to 20 beaches per query (unlimited queries)
- Multi-language support (19 languages)
- Response time: < 2 seconds

**Data Sources**:
- `GET /api/v1/beaches` (with filters)
- `GET /api/v1/beaches/filters` (filter metadata)
- Meilisearch `beaches` index

**Conversion Hook**:
> "I found 25 beaches matching your needs. Want me to create a 7-day itinerary for $39?"

**Why Free**: Search is a commodity (Google, TripAdvisor). Monetize outcomes, not search.

---

#### Capability 1.1.2: Beach Comparison (Up to 3 Beaches)
**Purpose**: Help users narrow down choices (decision support)

**Technical Requirements**:
- Compare up to 3 beaches side-by-side
- Show facilities, accessibility, weather, occupancy, parking
- Highlight differences (e.g., "Beach A has playground, Beach B doesn't")
- Response time: < 3 seconds

**Data Sources**:
- `GET /api/v1/beach-details/{id}` (3 parallel calls)
- `GET /api/v1/beach-details/{id}/weather` (3 parallel calls)

**Conversion Hook**:
> "You've compared 3 beaches. Want a verified accessibility report for Beach A ($15)?"

**Why Free**: Comparison is decision support, not final decision. Monetize verification/itinerary.

---

#### Capability 1.1.3: Current Weather Data
**Purpose**: Real-time context for beach selection

**Technical Requirements**:
- Fetch current weather from OpenWeatherMap API
- Cache for 1 hour (reduce API costs)
- Show temperature, conditions, wind, humidity
- Response time: < 1 second (cached)

**Data Sources**:
- `GET /api/v1/beach-details/{id}/weather`
- OpenWeatherMap API (external)

**Conversion Hook**:
> "Weather looks great today! Want a 3-day forecast for your trip ($19 itinerary includes this)?"

**Why Free**: Current weather is table stakes. Monetize multi-day forecasts (part of itinerary).

---

#### Capability 1.1.4: Accessibility Filters
**Purpose**: Let users search by accessibility needs (critical for target segment)

**Technical Requirements**:
- Filter by wheelchair access, paved access, accessible toilets, etc.
- Show accessibility attributes in search results
- No verification (database flags only)
- Response time: < 2 seconds

**Data Sources**:
- `GET /api/v1/beaches?accessibility=wheelchair,paved_access`
- `accessibility_beach` pivot table

**Conversion Hook**:
> "This beach reports wheelchair access. Want a verified report with photos ($15)?"

**Why Free**: Accessibility search is free. Monetize human-verified reports (trust + photos).

---

#### Capability 1.1.5: Educational Queries
**Purpose**: Answer "what is X?" questions (SEO, engagement)

**Technical Requirements**:
- Define beach types, badges, facilities, activities
- Provide examples from database
- Support multi-language definitions
- Response time: < 1 second (cached)

**Data Sources**:
- `GET /api/v1/beach-types`, `GET /api/v1/waterbodies`, etc.
- Translation tables for definitions

**Conversion Hook**: None (pure engagement, drives SEO traffic)

**Why Free**: Educational content is SEO driver, not revenue driver.

---

### 1.2 Paid Tier Capabilities (Revenue Generators)

These capabilities are **paid** and represent the core B2C monetization.

#### Capability 1.2.1: Trip Itinerary Generation (PRIMARY REVENUE)
**Purpose**: Generate optimized multi-day beach itineraries

**Technical Requirements**:
- Input: Trip duration (3-7-14 days), location, preferences, constraints
- Output: Day-by-day itinerary with beaches, routes, timing, weather
- Optimization: Minimize driving, maximize beach time, weather-aware
- Deliverable: PDF + mobile-friendly web view
- Response time: < 10 seconds (complex calculation)

**Data Sources**:
- `GET /api/v1/beaches` (filtered by location + preferences)
- `GET /api/v1/spatial/beaches/within-radius` (route optimization)
- `GET /api/v1/beach-details/{id}/weather` (multi-day forecast)
- Google Maps API (driving distances, optional)

**Pricing**: $19 (3-day), $39 (7-day), $59 (14-day)

**Technical Constraints**:
- ❌ No free preview (show sample itinerary only)
- ❌ No API access (prevent scraping)
- ✅ Watermarked PDF (prevent sharing)
- ✅ One-time purchase (no unlimited regeneration)

**Why Paid**: High value (saves 5+ hours, avoids $500 wasted day), clear ROI.

---

#### Capability 1.2.2: Verified Accessibility Report
**Purpose**: Human-verified accessibility data with photos

**Technical Requirements**:
- Input: Beach ID
- Output: Accessibility score (1-10), photos of access points, contact info
- Verification: Human reviewer confirms accessibility (not AI hallucination)
- Deliverable: PDF report with photos, guarantee
- Response time: < 1 second (pre-generated reports)

**Data Sources**:
- `verified_accessibility_reports` table (new, to be created)
- Photos stored in DigitalOcean Spaces
- Beach operator contact info from `beach` table

**Pricing**: $15 per beach

**Technical Constraints**:
- ❌ No bulk downloads (prevent reselling)
- ❌ No API access
- ✅ Money-back guarantee (if accessibility misrepresented)
- ✅ One-time purchase per beach (can re-download)

**Why Paid**: Human verification costs money, high trust value for accessibility users.

---

#### Capability 1.2.3: Activity-Specific Beach Guide
**Purpose**: Curated top 10 beaches for specific activity (snorkeling, diving, etc.)

**Technical Requirements**:
- Input: Activity type (snorkeling, diving, surfing, etc.), region
- Output: Top 10 beaches ranked by activity suitability
- Curation: Expert-curated (not just database filters)
- Deliverable: PDF guide with insider tips, equipment rental info
- Response time: < 1 second (pre-generated guides)

**Data Sources**:
- `activity_guides` table (new, to be created)
- `GET /api/v1/beaches?sporting_activities=snorkeling` (base data)
- Expert curation (manual process, not AI-generated)

**Pricing**: $25 per guide

**Technical Constraints**:
- ❌ No free preview (show 3 of 10 beaches only)
- ❌ No API access
- ✅ Valid for 1 year (updated quarterly)
- ✅ One-time purchase (can re-download)

**Why Paid**: Expert curation adds value beyond database search, saves hours of research.

---

## 2. AI Capabilities Required for B2B

### 2.1 Analytics Tier ($5K/year)

#### Capability 2.1.1: Beach Search Analytics Dashboard
**Purpose**: Show DMOs/municipalities which beaches users are searching for

**Technical Requirements**:
- Track search queries (beach names, filters, locations)
- Aggregate by beach, region, country, time period
- Show trends (week-over-week, month-over-month)
- Export to CSV/PDF
- Response time: < 5 seconds (pre-aggregated data)

**Data Sources**:
- `ai_usage` table (logs all search queries)
- `beach_analytics` table (aggregated metrics)

**Technical Constraints**:
- ✅ Up to 50 beaches tracked per customer
- ✅ Monthly reports only (not real-time)
- ❌ No raw query data (privacy, aggregated only)

**Why B2B**: DMOs need data to justify beach facility budgets, distribute tourists.

---

#### Capability 2.1.2: Visitor Intent Analysis
**Purpose**: Understand what users are looking for (families, accessibility, activities)

**Technical Requirements**:
- Classify queries by intent (family, accessibility, activity, etc.)
- Show distribution (60% families, 20% accessibility, 10% snorkeling, etc.)
- Geographic breakdown (where users are searching from)
- Response time: < 5 seconds

**Data Sources**:
- `ai_usage` table (query classification)
- `beach_analytics` table (aggregated intent data)

**Technical Constraints**:
- ✅ Aggregated data only (no individual user tracking)
- ✅ GDPR-compliant (anonymized)

**Why B2B**: DMOs need to understand visitor demand to plan facilities.

---

### 2.2 Promotion Tier ($15K/year)

#### Capability 2.2.1: Promoted Beach Listings
**Purpose**: Boost specific beaches in AI recommendations

**Technical Requirements**:
- Allow B2B customer to select beaches to promote
- Boost promoted beaches in AI recommendations (top 3 results)
- Track impressions and clicks (analytics)
- Response time: Real-time (no delay)

**Data Sources**:
- `promoted_beaches` table (B2B customer selections)
- AI recommendation algorithm (weighted scoring)

**Technical Constraints**:
- ✅ Up to 200 beaches promoted per customer
- ✅ Clearly labeled as "Promoted" (transparency)
- ❌ No guarantee of #1 ranking (relevance still matters)

**Why B2B**: DMOs pay to promote lesser-known beaches, reduce overcrowding.

---

#### Capability 2.2.2: Custom Beach Data
**Purpose**: Allow B2B customers to add/update beach data

**Technical Requirements**:
- B2B customer can add private beaches (not in public database)
- B2B customer can update facilities, photos, descriptions
- Changes visible only in their white-label instance (or globally, if approved)
- Response time: < 1 second (cached)

**Data Sources**:
- `beach` table (with `owner_id` field for private beaches)
- `beach_updates` table (pending changes for approval)

**Technical Constraints**:
- ✅ Private beaches visible only to B2B customer (not public)
- ✅ Public beach updates require admin approval
- ❌ No bulk imports (prevent spam)

**Why B2B**: Hotels want to add their private beaches, DMOs want to update facilities.

---

### 2.3 White-Label Tier ($30K/year)

#### Capability 2.3.1: Branded AI Agent
**Purpose**: Embed AI agent on B2B customer's website with their branding

**Technical Requirements**:
- Custom logo, colors, domain (e.g., beaches.croatia.hr)
- Custom system prompts (brand voice, specific recommendations)
- Isolated session storage (no cross-contamination)
- Response time: Same as main platform (< 2 seconds)

**Data Sources**:
- `tenants` table (B2B customer branding config)
- Same beach data as main platform (or custom subset)

**Technical Constraints**:
- ✅ Unlimited beaches tracked
- ✅ Custom branding (logo, colors, domain)
- ✅ API access (REST + GraphQL)
- ❌ No on-premise deployment (cloud-hosted only)

**Why B2B**: Tourism boards want branded tool on their website, not "Powered by Beachrex".

---

#### Capability 2.3.2: API Access (REST + GraphQL)
**Purpose**: Allow B2B customers to integrate beach data into their platforms

**Technical Requirements**:
- REST API: All beach endpoints + AI recommendations
- GraphQL API: Flexible queries for custom integrations
- Rate limiting: 10,000 requests/day (configurable)
- Response time: < 500ms (cached)

**Data Sources**:
- Same as main platform (`/api/v1/beaches`, etc.)
- AI recommendation endpoint (`/api/v1/ai/recommend`)

**Technical Constraints**:
- ✅ API key authentication (per B2B customer)
- ✅ Usage tracking (billing, analytics)
- ❌ No bulk downloads (prevent reselling)

**Why B2B**: Travel agencies want to integrate beach data into booking platforms.

---

### 2.4 Enterprise Tier ($50K-$200K/year)

#### Capability 2.4.1: Custom AI Prompts (Brand Voice)
**Purpose**: Customize AI agent's personality and recommendations

**Technical Requirements**:
- B2B customer defines system prompts (tone, style, recommendations)
- Example: "Emphasize sustainability and eco-friendly beaches"
- A/B testing support (test different prompts)
- Response time: Same as main platform

**Data Sources**:
- `tenant_prompts` table (custom system instructions)
- Same beach data as main platform

**Technical Constraints**:
- ✅ Unlimited prompt customization
- ✅ A/B testing (compare prompt performance)
- ❌ No offensive/misleading prompts (admin approval required)

**Why Enterprise**: National tourism boards want to emphasize specific values (sustainability, culture).

---

#### Capability 2.4.2: Private Data Sources
**Purpose**: B2B customer uses only their beaches (not full database)

**Technical Requirements**:
- B2B customer selects subset of beaches (e.g., only Croatia, only 5-star)
- AI agent searches only this subset
- Isolated Meilisearch index (no cross-contamination)
- Response time: Same as main platform

**Data Sources**:
- `beaches_{tenant_id}` Meilisearch index (isolated)
- `beach` table filtered by `owner_id` or `tenant_id`

**Technical Constraints**:
- ✅ Fully isolated data (no access to other customers' data)
- ✅ Custom indexing rules (e.g., only beaches with 4+ star rating)
- ❌ No access to full database (unless explicitly granted)

**Why Enterprise**: Hotel chains want AI to recommend only beaches near their properties.

---

#### Capability 2.4.3: SLA Guarantees
**Purpose**: Guarantee uptime and performance for enterprise customers

**Technical Requirements**:
- 99.9% uptime SLA (max 43 minutes downtime/month)
- < 1 second response time (99th percentile)
- Dedicated infrastructure (no multi-tenancy)
- 4-hour support response time

**Data Sources**: Same as main platform (dedicated servers)

**Technical Constraints**:
- ✅ Dedicated servers (isolated from shared infrastructure)
- ✅ Monitoring and alerting (24/7)
- ✅ Penalties for SLA violations (10-50% monthly credit)

**Why Enterprise**: National tourism boards need guaranteed uptime (critical infrastructure).

---

## 3. MVP Capabilities (Phase 1)

### Must-Have for Launch

#### B2C MVP:
1. ✅ **Natural Language Search** (free, unlimited)
2. ✅ **Beach Comparison** (free, up to 3 beaches)
3. ✅ **Current Weather** (free)
4. ✅ **Accessibility Filters** (free)
5. ✅ **Trip Itinerary Generation** (paid, $19-$59) - **PRIMARY REVENUE**

#### B2B MVP:
1. ✅ **Beach Search Analytics** (Analytics tier, $5K/year)
2. ✅ **Promoted Beach Listings** (Promotion tier, $15K/year)

### Can Wait for Phase 2

#### B2C Phase 2:
- ⏳ Verified Accessibility Reports (requires human verification process)
- ⏳ Activity-Specific Guides (requires expert curation)

#### B2B Phase 2:
- ⏳ White-Label AI Agent (requires multi-tenancy infrastructure)
- ⏳ API Access (requires rate limiting, billing integration)
- ⏳ Custom AI Prompts (requires prompt management system)
- ⏳ Private Data Sources (requires isolated Meilisearch indexes)

---

## 4. Enterprise-Only Capabilities

These capabilities are **NEVER available** to free or paid B2C users.

### 4.1 Custom AI Prompts
- **Why Enterprise-Only**: Requires manual review, A/B testing infrastructure
- **Technical Gate**: `tenant_prompts` table, only accessible via admin panel

### 4.2 Private Data Sources
- **Why Enterprise-Only**: Requires isolated Meilisearch indexes, high infrastructure cost
- **Technical Gate**: `beaches_{tenant_id}` index, only created for Enterprise customers

### 4.3 SLA Guarantees
- **Why Enterprise-Only**: Requires dedicated infrastructure, 24/7 monitoring
- **Technical Gate**: Dedicated servers, separate deployment

### 4.4 On-Premise Deployment
- **Why Enterprise-Only**: High setup cost, ongoing maintenance
- **Technical Gate**: Custom deployment, requires $5K+ setup fee

### 4.5 Audit Logs (90-day retention)
- **Why Enterprise-Only**: Storage cost, compliance requirements
- **Technical Gate**: `ai_audit_log` table, 90-day retention only for Enterprise

---

## 5. Technical Constraints That Protect Monetization

### 5.1 Prevent Free Tier Abuse

#### Constraint 5.1.1: Rate Limiting (Abuse Prevention)
**Rule**: 100 queries per hour per IP (not per user)

**Rationale**: Prevent scraping, not legitimate use

**Implementation**:
```php
// Redis sliding window
$key = "rate_limit:ip:{$ip}:hour";
$count = Redis::incr($key);
Redis::expire($key, 3600);

if ($count > 100) {
    throw new RateLimitException("Too many requests");
}
```

**Why**: Protects infrastructure, prevents data scraping, doesn't block legitimate users.

---

#### Constraint 5.1.2: No Bulk Downloads
**Rule**: No API endpoint to download all beaches or all itineraries

**Rationale**: Prevent competitors from cloning database

**Implementation**:
- ❌ No `GET /api/v1/beaches?limit=10000` (max limit: 100)
- ❌ No `GET /api/v1/itineraries` (itineraries are one-time purchases)

**Why**: Protects proprietary data, prevents reselling.

---

### 5.2 Prevent Paid Tier Leakage

#### Constraint 5.2.1: Watermarked PDFs
**Rule**: All paid PDFs (itineraries, reports, guides) have watermark

**Rationale**: Prevent sharing/reselling

**Implementation**:
- Watermark: "Generated for {email} on {date}"
- Embedded in PDF (not removable)

**Why**: Discourages sharing, enables tracking if leaked.

---

#### Constraint 5.2.2: One-Time Purchase Validation
**Rule**: Itineraries are one-time purchases (no unlimited regeneration)

**Rationale**: Prevent users from generating 100 itineraries for $39

**Implementation**:
```php
// Check if user already purchased itinerary for this trip
$existing = Purchase::where('user_id', $userId)
    ->where('product_type', 'itinerary')
    ->where('trip_params_hash', $tripHash)
    ->first();

if ($existing) {
    return $existing; // Return existing itinerary
}
```

**Why**: Prevents abuse, protects revenue.

---

### 5.3 Prevent B2B Tier Leakage

#### Constraint 5.3.1: Tenant Isolation
**Rule**: B2B customers cannot access other customers' data

**Rationale**: Privacy, competitive advantage

**Implementation**:
```php
// All queries scoped by tenant_id
$beaches = Beach::where('tenant_id', $tenantId)->get();
```

**Why**: Prevents data leakage, protects B2B customers.

---

#### Constraint 5.3.2: API Key Scoping
**Rule**: API keys are scoped to specific capabilities (read-only, write, admin)

**Rationale**: Prevent unauthorized access

**Implementation**:
```php
// API key has scopes: ['beaches:read', 'analytics:read']
if (!$apiKey->hasScope('beaches:write')) {
    throw new UnauthorizedException();
}
```

**Why**: Prevents B2B customers from modifying data they shouldn't.

---

## 6. Capability Prioritization Matrix

| Capability | B2C Revenue | B2B Revenue | MVP Priority | Complexity | Decision |
|------------|-------------|-------------|--------------|------------|----------|
| Natural Language Search | Free (driver) | Free (driver) | **P0** | Medium | ✅ MVP |
| Beach Comparison | Free (driver) | Free (driver) | **P0** | Low | ✅ MVP |
| Current Weather | Free (driver) | Free (driver) | **P0** | Low | ✅ MVP |
| Trip Itinerary | **$1.08M/year** | N/A | **P0** | High | ✅ MVP |
| Beach Analytics | N/A | **$100K/year** | **P0** | Medium | ✅ MVP |
| Promoted Listings | N/A | **$150K/year** | **P0** | Medium | ✅ MVP |
| Verified Accessibility | $0.45M/year | N/A | P1 | High | ⏳ Phase 2 |
| Activity Guides | $0.27M/year | N/A | P1 | High | ⏳ Phase 2 |
| White-Label AI | N/A | $150K/year | P1 | High | ⏳ Phase 2 |
| API Access | N/A | Included | P1 | Medium | ⏳ Phase 2 |
| Custom Prompts | N/A | $200K/year | P2 | Medium | ⏳ Phase 3 |
| Private Data | N/A | Included | P2 | High | ⏳ Phase 3 |

**Total MVP Revenue Potential**: $1.43M/year (B2C: $1.08M, B2B: $0.35M)

---

**End of Capability Alignment Document**

This document ensures AI development is aligned with monetization strategy. All capabilities are prioritized by revenue potential and MVP necessity. Technical constraints protect revenue from leakage.

