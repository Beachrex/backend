# Beachrex AI Agent - Pricing & Packaging

**Version**: 1.0  
**Last Updated**: 2025-12-22  
**Status**: Production Pricing Model

---

## Pricing Tiers Overview

| Tier | Price | Target Customer | Primary Use Case |
|------|-------|----------------|------------------|
| **Free** | $0/month | Casual users, trial | Explore platform, occasional searches |
| **Explorer** | $9/month or $0.10/query | Frequent travelers | Vacation planning, multi-search sessions |
| **Pro** | $29/month or $0.05/query | Power users, agencies | Advanced features, API access, bulk queries |
| **Enterprise** | Custom (from $299/month) | Tourism boards, platforms | White-label, unlimited usage, SLA |

---

## Detailed Pricing Table

### 1. Core Limits

| Feature | Free | Explorer | Pro | Enterprise |
|---------|------|----------|-----|------------|
| **Queries per Month** | 100 | 1,000 | 5,000 | Unlimited |
| **Queries per Hour** | 10 | 100 | 500 | Unlimited |
| **Queries per Day** | 100 | 1,000 | 5,000 | Unlimited |
| **Overage Rate** | Not allowed | $0.10/query | $0.05/query | N/A |
| **Token Budget per Query** | 4,000 | 8,000 | 16,000 | 32,000 |
| **Session Duration** | 15 min | 30 min | 60 min | 120 min |
| **Concurrent Sessions** | 1 | 3 | 10 | Unlimited |

### 2. AI Capabilities

| Capability | Free | Explorer | Pro | Enterprise |
|------------|------|----------|-----|------------|
| **Natural Language Search** | ✅ Basic (3 filters max) | ✅ Advanced (unlimited filters) | ✅ Advanced | ✅ Advanced |
| **Personalized Recommendations** | ❌ | ✅ Top 3 | ✅ Top 10 | ✅ Unlimited |
| **Multi-Criteria Comparison** | ❌ | ✅ 2 beaches | ✅ 5 beaches | ✅ 10 beaches |
| **Context-Aware Suggestions** | ❌ | ✅ Basic | ✅ Advanced | ✅ Advanced + Custom |
| **Educational Queries** | ✅ Limited | ✅ Full | ✅ Full | ✅ Full |
| **Accessibility Search** | ✅ Basic | ✅ Full | ✅ Full | ✅ Full |
| **Multi-Language Support** | 1 language (English) | 19 languages | 19 languages | 19 languages + Custom |
| **Global Search** | ✅ 5 results | ✅ 20 results | ✅ Unlimited | ✅ Unlimited |

### 3. Data Access Scope

| Data Type | Free | Explorer | Pro | Enterprise |
|-----------|------|----------|-----|------------|
| **Beach Results per Query** | 5 | 20 | 100 | Unlimited |
| **Geographic Entities** | Countries only | Countries, Regions | All (Countries, Regions, Islands, Cities) | All + Custom |
| **Weather Data** | ❌ | ✅ Current only | ✅ Current + 3-day forecast | ✅ Current + 7-day forecast |
| **Beach Details** | Basic (name, location, image) | Standard (+ facilities, activities) | Full (+ badges, articles, 360° tours) | Full + Custom fields |
| **Spatial Queries** | ❌ | ✅ Within 10km | ✅ Within 50km | ✅ Unlimited radius |
| **Map View** | ❌ | ✅ Up to 50 beaches | ✅ Up to 500 beaches | ✅ Unlimited |
| **Historical Data** | ❌ | ❌ | ✅ Most-visited beaches | ✅ Full analytics |

### 4. Advanced Features

| Feature | Free | Explorer | Pro | Enterprise |
|---------|------|----------|-----|------------|
| **AI-Powered Itineraries** | ❌ | ❌ | ✅ 1 per day | ✅ Unlimited |
| **Beach Comparisons** | ❌ | ✅ 2 beaches | ✅ 5 beaches | ✅ 10 beaches |
| **360° Virtual Tours** | ❌ | ✅ View only | ✅ View + Share | ✅ View + Embed |
| **Export Results** | ❌ | ❌ | ✅ CSV, PDF | ✅ CSV, PDF, JSON, API |
| **API Access** | ❌ | ❌ | ✅ REST API | ✅ REST + GraphQL |
| **Webhooks** | ❌ | ❌ | ❌ | ✅ Custom webhooks |
| **Custom Branding** | ❌ | ❌ | ❌ | ✅ White-label |
| **Priority Support** | ❌ | Email (48h) | Email (24h) | Dedicated (4h) |

### 5. Technical Limits

| Limit | Free | Explorer | Pro | Enterprise |
|-------|------|----------|-----|------------|
| **Response Time SLA** | Best effort | < 3s (95th percentile) | < 2s (95th percentile) | < 1s (99th percentile) |
| **Uptime SLA** | Best effort | 99.0% | 99.5% | 99.9% |
| **Rate Limit** | 10/hour | 100/hour | 500/hour | Custom |
| **Burst Limit** | 3 queries/min | 10 queries/min | 50 queries/min | Unlimited |
| **Cache TTL** | 30 days | 8 hours | 1 hour | Real-time option |
| **Context Window** | 4K tokens | 8K tokens | 16K tokens | 32K tokens |
| **Max Session History** | 3 messages | 5 messages | 10 messages | 20 messages |

---

## Feature Gating Details

### Free Tier: Discovery & Trial

**Allowed**:
- ✅ Basic search with up to 3 filters (country, beach_type, surface)
- ✅ View 5 beaches per query
- ✅ English language only
- ✅ Educational queries (definitions, beach types)
- ✅ Accessibility search (basic filters)
- ✅ 15-minute session memory

**Blocked**:
- ❌ Advanced filters (facilities, activities, occupancy)
- ❌ Personalized recommendations
- ❌ Beach comparisons
- ❌ Weather data
- ❌ Map view
- ❌ Spatial queries ("near me")
- ❌ Multi-language support
- ❌ 360° tours
- ❌ Itinerary generation
- ❌ Export functionality
- ❌ API access

**Upsell Message**:
> "You've used 95 of 100 free queries this month. Upgrade to Explorer for 1,000 queries/month, weather data, and map view for just $9/month."

### Explorer Tier: Vacation Planners

**Unlocked from Free**:
- ✅ Advanced search (unlimited filters)
- ✅ 20 beaches per query (vs 5)
- ✅ All 19 languages
- ✅ Weather data (current)
- ✅ Map view (up to 50 beaches)
- ✅ Spatial queries (within 10km)
- ✅ Personalized recommendations (top 3)
- ✅ Beach comparisons (2 beaches)
- ✅ 360° virtual tours
- ✅ 30-minute session memory

**Still Blocked**:
- ❌ AI-powered itineraries
- ❌ 3-day weather forecast
- ❌ Compare 5+ beaches
- ❌ Export results
- ❌ API access
- ❌ Most-visited beaches analytics
- ❌ Spatial queries > 10km

**Upsell Message**:
> "You've compared 2 beaches. Upgrade to Pro to compare up to 5 beaches side-by-side, generate itineraries, and access our API for $29/month."

### Pro Tier: Power Users & Agencies

**Unlocked from Explorer**:
- ✅ 100 beaches per query (vs 20)
- ✅ 3-day weather forecast
- ✅ AI-powered itineraries (1 per day)
- ✅ Compare 5 beaches (vs 2)
- ✅ Spatial queries (within 50km)
- ✅ Map view (up to 500 beaches)
- ✅ Export results (CSV, PDF)
- ✅ REST API access
- ✅ Most-visited beaches analytics
- ✅ Priority email support (24h response)
- ✅ 60-minute session memory

**Still Blocked**:
- ❌ Unlimited itineraries
- ❌ 7-day weather forecast
- ❌ Compare 10 beaches
- ❌ White-label embedding
- ❌ Custom branding
- ❌ GraphQL API
- ❌ Webhooks
- ❌ Dedicated support
- ❌ SLA guarantees

**Upsell Message**:
> "Your agency is using 4,500 queries/month. Upgrade to Enterprise for unlimited queries, white-label embedding, and 99.9% uptime SLA. Contact sales for custom pricing."

### Enterprise Tier: Tourism Boards & Platforms

**Unlocked from Pro**:
- ✅ Unlimited queries
- ✅ Unlimited itineraries
- ✅ 7-day weather forecast
- ✅ Compare 10 beaches
- ✅ Unlimited spatial radius
- ✅ Unlimited map view
- ✅ White-label embedding
- ✅ Custom branding (logo, colors, prompts)
- ✅ GraphQL API
- ✅ Webhooks (custom integrations)
- ✅ Dedicated support (4h response)
- ✅ 99.9% uptime SLA
- ✅ Custom data fields
- ✅ Private Meilisearch indexes
- ✅ Audit logs
- ✅ SSO integration
- ✅ 120-minute session memory

**Custom Options**:
- 🔧 Custom AI prompts (brand voice)
- 🔧 Private data sources (your beaches only)
- 🔧 Custom analytics dashboard
- 🔧 On-premise deployment
- 🔧 Dedicated infrastructure

---

## Usage Limits Breakdown

### Query Limits

| Tier | Per Hour | Per Day | Per Month | Overage |
|------|----------|---------|-----------|---------|
| Free | 10 | 100 | 100 | ❌ Hard block |
| Explorer | 100 | 1,000 | 1,000 | ✅ $0.10/query |
| Pro | 500 | 5,000 | 5,000 | ✅ $0.05/query |
| Enterprise | ∞ | ∞ | ∞ | N/A |

**Enforcement**:
- Rate limiting via Redis (sliding window)
- 429 Too Many Requests response with `Retry-After` header
- Email notification at 80%, 90%, 100% of quota

### Data Scope Limits

| Tier | Beaches/Query | Entities/Query | Filters | Languages |
|------|---------------|----------------|---------|-----------|
| Free | 5 | 5 | 3 | 1 (en) |
| Explorer | 20 | 10 | Unlimited | 14 |
| Pro | 100 | 50 | Unlimited | 14 |
| Enterprise | Unlimited | Unlimited | Unlimited | 14 + Custom |

### Concurrency Limits

| Tier | Concurrent Sessions | Concurrent API Calls | Session Duration |
|------|---------------------|----------------------|------------------|
| Free | 1 | 1 | 15 min |
| Explorer | 3 | 5 | 30 min |
| Pro | 10 | 20 | 60 min |
| Enterprise | Unlimited | Unlimited | 120 min |

**Enforcement**:
- Session tracking via Redis
- 409 Conflict response if concurrency exceeded
- Oldest session auto-terminated if limit reached

---

## Upsell Triggers

### Trigger 1: Query Limit Reached

**Free → Explorer**:
```
🚫 You've reached your 100 queries for this month.

Upgrade to Explorer for:
✅ 1,000 queries/month (10x more)
✅ Weather data for all beaches
✅ Map view with spatial search
✅ All 19 languages

Only $9/month or $0.10/query pay-as-you-go
[Upgrade Now]
```

**Explorer → Pro**:
```
🚫 You've used 950 of 1,000 queries this month.

Upgrade to Pro for:
✅ 5,000 queries/month (5x more)
✅ AI-powered itineraries
✅ Compare 5 beaches side-by-side
✅ REST API access
✅ Export results (CSV, PDF)

Only $29/month or $0.05/query overage
[Upgrade Now]
```

### Trigger 2: Feature Blocked

**Free user tries to view weather**:
```
🌤️ Weather data is available on Explorer tier and above.

Upgrade to see:
✅ Current weather for all beaches
✅ Temperature, wind, humidity
✅ Weather-based recommendations

Start your 7-day free trial
[Try Explorer Free]
```

**Explorer user tries to generate itinerary**:
```
🗺️ AI-powered itineraries are available on Pro tier.

Upgrade to unlock:
✅ Custom beach-hopping itineraries
✅ Optimized routes and timing
✅ 1 itinerary per day

Upgrade to Pro for $29/month
[Upgrade Now]
```

### Trigger 3: Data Limit Reached

**Free user gets 5 results, but 50 exist**:
```
Found 50 beaches matching your criteria.
Showing 5 results (Free tier limit).

Upgrade to Explorer to see all 50 beaches:
✅ 20 beaches per query
✅ Advanced filtering
✅ Map view

Only $9/month
[See All Results]
```

### Trigger 4: Performance Degradation

**Pro user hits rate limit**:
```
⚠️ You're approaching your rate limit (480/500 queries this hour).

Upgrade to Enterprise for:
✅ Unlimited queries
✅ No rate limits
✅ 99.9% uptime SLA
✅ Dedicated support

Contact sales for custom pricing
[Contact Sales]
```

---

## Enterprise Hooks

### 1. Service Level Agreement (SLA)

**Uptime SLA**: 99.9% (max 43 minutes downtime/month)

**Performance SLA**:
- 99th percentile response time: < 1 second
- 95th percentile response time: < 500ms
- Average response time: < 300ms

**Support SLA**:
- Critical issues: 1-hour response, 4-hour resolution
- High priority: 4-hour response, 24-hour resolution
- Medium priority: 24-hour response, 72-hour resolution

**Penalties**:
- < 99.9% uptime: 10% monthly credit
- < 99.5% uptime: 25% monthly credit
- < 99.0% uptime: 50% monthly credit

### 2. Custom Prompts (Brand Voice)

**Capability**: Customize AI agent's personality and tone

**Examples**:
- Tourism board: "Formal, informative, emphasize sustainability"
- Hotel chain: "Friendly, luxury-focused, upsell amenities"
- Travel agency: "Adventurous, budget-conscious, family-friendly"

**Implementation**:
- Custom system instructions per enterprise customer
- Stored in database, loaded per API key
- A/B testing support (test different prompts)

### 3. Private Indexes (Data Isolation)

**Capability**: Dedicated Meilisearch index with customer's beaches only

**Use Cases**:
- Tourism board: Only beaches in their region
- Hotel chain: Only beaches near their properties
- Travel platform: Curated beach collection

**Implementation**:
- Separate Meilisearch index: `beaches_{customer_id}`
- Isolated from main index (no cross-contamination)
- Custom indexing rules (e.g., only 5-star beaches)

### 4. Audit Logs (Compliance)

**Logged Events**:
- Every query (timestamp, user, query text, results)
- API calls (endpoint, parameters, response time)
- Rate limit violations
- Errors and failures
- Data exports

**Retention**: 90 days (configurable up to 1 year)

**Access**: Via dashboard or API export (CSV, JSON)

**Compliance**: GDPR-ready (anonymized user IDs, data deletion on request)

### 5. Additional Enterprise Features

**SSO Integration**:
- SAML 2.0, OAuth 2.0, OpenID Connect
- Integrate with customer's identity provider

**Custom Analytics Dashboard**:
- Query volume trends
- Popular beaches/destinations
- User behavior insights
- Performance metrics

**On-Premise Deployment** (Premium Add-On):
- Self-hosted AI agent
- Customer's infrastructure
- Full data control
- Custom pricing (from $5,000/month)

**Dedicated Infrastructure**:
- Isolated servers (no multi-tenancy)
- Guaranteed resources (CPU, RAM, bandwidth)
- Custom scaling rules

---

## Pricing Summary Table

| Tier | Monthly Price | Per-Query Price | Best For | Key Unlock |
|------|---------------|-----------------|----------|------------|
| **Free** | $0 | N/A | Trial users | Basic search, 100 queries |
| **Explorer** | $9 | $0.10 overage | Travelers | Weather, map, 1K queries |
| **Pro** | $29 | $0.05 overage | Agencies | API, itineraries, 5K queries |
| **Enterprise** | Custom ($299+) | N/A | Platforms | Unlimited, SLA, white-label |

**Annual Discount**: 20% off (2 months free)
- Explorer: $86.40/year (save $21.60)
- Pro: $278.40/year (save $69.60)

**Volume Discounts** (Enterprise):
- 10K-50K queries/month: $299/month
- 50K-200K queries/month: $799/month
- 200K+ queries/month: Custom pricing

---

**End of Pricing Document**

This pricing model balances accessibility (free tier), growth (Explorer/Pro), and enterprise value (unlimited + SLA). Upsell triggers are designed to convert users at natural friction points (limits, blocked features, performance).

