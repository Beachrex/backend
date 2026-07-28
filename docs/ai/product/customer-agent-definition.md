# Beachrex AI Agent - Customer-Facing Product Definition

**Product Name**: Beachrex Beach Discovery Assistant  
**Version**: 1.0  
**Last Updated**: 2025-12-22  
**Status**: Production-Ready (Platform 80% Complete)

---

## 1. Agent Purpose

### What Problem the Agent Solves for the Customer

**Primary Problem**: Beach discovery is overwhelming and time-consuming
- 2,445+ beaches across multiple countries with 40+ attributes each
- Users don't know which filters matter for their specific needs
- Language barriers prevent access to local beach information
- Weather, accessibility, and facility information scattered across sources
- Planning beach vacations requires researching dozens of websites

**Agent Solution**: Conversational beach discovery that understands intent
- Natural language queries instead of complex filter forms
- Personalized recommendations based on user preferences and constraints
- Multi-language support (19 languages) with localized insights
- Aggregated information (weather, facilities, accessibility) in one response
- Context-aware suggestions based on location, season, travel party

### What Job the Customer Hires the Agent to Do

**Job-to-be-Done**: "Help me find the perfect beach for my specific situation"

**Hiring Triggers**:
1. **Vacation Planning**: "I'm planning a family trip to Croatia in August"
2. **Local Discovery**: "What beaches near Split are good for snorkeling?"
3. **Accessibility Needs**: "I need a wheelchair-accessible beach with parking"
4. **Activity-Based**: "Where can I go windsurfing in Slovenia?"
5. **Comparison**: "Compare beaches on Krk Island for families with toddlers"
6. **Real-Time Decisions**: "Which beach should I visit today based on weather?"

**Expected Outcome**: Confident beach selection in < 5 minutes (vs 30+ minutes manual research)

---

## 2. Supported Use Cases

### 2.1 Natural Language Search

**Capability**: Translate conversational queries into structured filters

**Examples**:
- ✅ "Show me sandy beaches in Croatia with showers and parking"
  - **Agent Action**: Filter by country=HR, surface=sand, facilities=[shower, parking]
  - **Response**: List of 15 beaches with photos, map, weather

- ✅ "I need a quiet beach near Pula without crowds"
  - **Agent Action**: Filter by city=Pula, occupancy_rate=low
  - **Response**: 5 secluded beaches with current occupancy estimates

- ✅ "Dog-friendly beaches in Istria region"
  - **Agent Action**: Filter by region=Istria, badges=[dog-friendly]
  - **Response**: 8 beaches allowing dogs with specific rules/restrictions

### 2.2 Personalized Recommendations

**Capability**: Suggest beaches based on user profile and constraints

**Examples**:
- ✅ "Recommend a beach for my family (2 adults, 3-year-old, 6-year-old)"
  - **Agent Action**: Filter by user_type=family, beach_facility=[playground, shallow_water], safety=high
  - **Response**: Top 3 family-friendly beaches with kid amenities, safety info

- ✅ "I'm traveling with elderly parents who have mobility issues"
  - **Agent Action**: Filter by accessibility=[wheelchair, paved_access], parking_beach_height=low, facilities=[accessible_toilet]
  - **Response**: 4 accessible beaches with detailed accessibility info, parking distance

- ✅ "Best beaches for a romantic sunset in July"
  - **Agent Action**: Filter by beach_type=scenic, orientation=west, occupancy_rate=low, season=summer
  - **Response**: 5 sunset-view beaches with sunset times, romantic restaurant recommendations

### 2.3 Multi-Criteria Comparison

**Capability**: Compare beaches across multiple dimensions

**Examples**:
- ✅ "Compare Banje Beach vs Copacabana Beach in Dubrovnik"
  - **Agent Action**: Fetch details for both beaches
  - **Response**: Side-by-side comparison table (facilities, accessibility, occupancy, weather, prices)

- ✅ "What's the difference between pebble and sandy beaches in terms of water clarity?"
  - **Agent Action**: Aggregate data on surface types and water clarity
  - **Response**: Educational comparison with examples from database

- ✅ "Show me the top 5 beaches on Hvar Island ranked by facilities"
  - **Agent Action**: Filter by island=Hvar, sort by facility count
  - **Response**: Ranked list with facility breakdown, photos, ratings

### 2.4 Context-Aware Suggestions

**Capability**: Provide recommendations based on real-time context

**Examples**:
- ✅ "Which beach should I visit today?" (user location: Split, date: July 15)
  - **Agent Action**: Fetch weather for nearby beaches, filter by current conditions
  - **Response**: 3 beaches with best weather today, current temperature, wind, waves

- ✅ "I'm in Rovinj right now, where's the nearest beach with a restaurant?"
  - **Agent Action**: Spatial query (radius search), filter by culinary_offer=restaurant
  - **Response**: 2 nearest beaches (with distance, walking time), restaurant details

- ✅ "Plan a beach-hopping day in Zadar"
  - **Agent Action**: Fetch beaches in Zadar, optimize route
  - **Response**: Suggested itinerary (3 beaches, travel times, what to do at each)

### 2.5 Educational Queries

**Capability**: Answer questions about beaches, regions, activities

**Examples**:
- ✅ "What is a Blue Flag beach?"
  - **Agent Action**: Retrieve badge definition from database
  - **Response**: Explanation of Blue Flag certification, list of Blue Flag beaches in system

- ✅ "Tell me about beaches in Dalmatia region"
  - **Agent Action**: Fetch region details, aggregate beach statistics
  - **Response**: Overview of Dalmatia, beach types, popular destinations, climate

- ✅ "What water sports are available in Slovenia?"
  - **Agent Action**: Aggregate sporting_activity data for country=SI
  - **Response**: List of activities (diving, kayaking, SUP) with beach examples

### 2.6 Accessibility & Special Needs

**Capability**: Find beaches meeting specific accessibility requirements

**Examples**:
- ✅ "Wheelchair-accessible beaches with beach wheelchairs available"
  - **Agent Action**: Filter by accessibility=[wheelchair, beach_wheelchair]
  - **Response**: 12 beaches with detailed accessibility info, equipment rental

- ✅ "Beaches with lifeguards and first aid for safety"
  - **Agent Action**: Filter by beach_facility=[lifeguard, first_aid]
  - **Response**: 45 beaches with lifeguard schedules, emergency contact info

- ✅ "I need a beach with shade for my baby"
  - **Agent Action**: Filter by shade=[natural_shade, umbrella_rental]
  - **Response**: 20 beaches with shade options, UV index, baby-friendly facilities

### 2.7 Multi-Language Support

**Capability**: Respond in user's preferred language (19 locales)

**Examples**:
- ✅ "Pokaži mi plaže v Piranu" (Slovenian)
  - **Agent Action**: Detect locale=si, filter by city=Piran
  - **Response**: Slovenian response with 3 beaches in Piran

- ✅ "Zeig mir Strände in Kroatien mit Parkplatz" (German)
  - **Agent Action**: Detect locale=de, filter by country=HR, parking=yes
  - **Response**: German response with parking details

- ✅ "Montrez-moi des plages pour la plongée" (French)
  - **Agent Action**: Detect locale=fr, filter by sporting_activity=diving
  - **Response**: French response with diving beaches, dive centers

---

## 3. Non-Goals

### What the Agent Explicitly Does NOT Do

#### 3.1 Booking & Reservations
- ❌ Does NOT book beach chairs, umbrellas, or parking spots
- ❌ Does NOT reserve accommodations or hotels
- ❌ Does NOT process payments or transactions
- **Reason**: Beachrex is a discovery platform, not a booking platform

#### 3.2 User-Generated Content
- ❌ Does NOT accept or display user reviews or ratings
- ❌ Does NOT allow users to upload photos or videos
- ❌ Does NOT create user profiles or save preferences (beyond session)
- **Reason**: Maintains data quality, avoids moderation overhead

#### 3.3 Real-Time Crowding Data
- ❌ Does NOT provide live occupancy counts (only historical estimates)
- ❌ Does NOT show real-time parking availability
- ❌ Does NOT track beach capacity or entry restrictions
- **Reason**: Requires IoT infrastructure not currently available

#### 3.4 Navigation & Directions
- ❌ Does NOT provide turn-by-turn driving directions
- ❌ Does NOT calculate exact travel times (only estimates)
- ❌ Does NOT integrate with Google Maps/Waze for routing
- **Reason**: Defers to specialized navigation apps

#### 3.5 Social Features
- ❌ Does NOT connect users with each other
- ❌ Does NOT create shareable itineraries or trip plans
- ❌ Does NOT send notifications or reminders
- **Reason**: Avoids scope creep, focuses on discovery

#### 3.6 Predictive Analytics
- ❌ Does NOT predict future weather beyond current data
- ❌ Does NOT forecast beach conditions (waves, jellyfish, algae)
- ❌ Does NOT predict crowd levels beyond historical patterns
- **Reason**: Requires data sources not currently integrated

---

## 4. Authority Boundaries

### 4.1 Data Access Permissions

**READ Access** (Agent can query):
- ✅ All active beaches (status_id = 1) with full details
- ✅ Geographic entities (continents, countries, regions, islands, cities)
- ✅ Reference data (beach types, facilities, activities, badges)
- ✅ Translations for all entities in all 19 locales
- ✅ Media (images, videos, 360° tours) for active beaches
- ✅ Current weather data (via OpenWeatherMap API)
- ✅ Beach navigation relationships (prev/next beaches)
- ✅ Meilisearch indexes for global search

**NO Access** (Agent cannot query):
- ❌ Inactive beaches (status_id != 1)
- ❌ Admin panel data (user accounts, audit logs)
- ❌ Database credentials, API keys, secrets
- ❌ Server logs, error logs, performance metrics
- ❌ User session data beyond current conversation
- ❌ Payment information (N/A - no payments in system)

### 4.2 Action Permissions

**ALLOWED Actions**:
- ✅ Query beaches with any combination of 40+ filters
- ✅ Perform spatial queries (radius, bounding box, nearest)
- ✅ Fetch weather data for beaches and geographic entities
- ✅ Search across all entities via Meilisearch
- ✅ Generate beach comparisons and recommendations
- ✅ Construct image URLs via CDN (Imagor optimization)
- ✅ Track conversation context within session (ephemeral)

**FORBIDDEN Actions**:
- ❌ Modify beach data (name, description, coordinates, facilities)
- ❌ Create, update, or delete any database records
- ❌ Upload or delete media files
- ❌ Change beach status (active/inactive)
- ❌ Modify translations or reference data
- ❌ Clear caches or trigger re-indexing
- ❌ Execute database migrations or schema changes
- ❌ Access or modify other users' conversations

### 4.3 Read-Only vs Write Permissions

**Read-Only** (100% of agent capabilities):
- All beach discovery features are read-only
- Agent queries API endpoints but never modifies data
- Conversation history stored ephemerally (session-only, not persisted)

**Write Permissions** (None):
- Agent has ZERO write permissions to database
- Agent cannot trigger any data modification actions
- Agent cannot send emails, SMS, or external notifications

**Rationale**: Safety-first design prevents data corruption, maintains data quality

---

## 5. Output Types

### 5.1 Insights

**Beach Insights**:
- "This beach is popular with families due to shallow water and playground"
- "Water clarity is excellent here because of the pebble surface"
- "Parking fills up quickly on weekends (arrive before 10 AM)"
- "This beach has strong currents - not recommended for weak swimmers"

**Regional Insights**:
- "Istria region has 87 beaches, mostly pebble with clear water"
- "Dalmatia beaches are busier in July-August, consider June or September"
- "Slovenian coast has only 47km of coastline but 23 beaches"

**Seasonal Insights**:
- "Water temperature in July averages 24°C in this area"
- "This beach faces west - perfect for sunset views"
- "Occupancy is typically low on weekdays outside July-August"

### 5.2 Recommendations

**Personalized Recommendations**:
```
Based on your needs (family with toddler, parking required, shade):

1. **Bačvice Beach, Split** ⭐ Top Pick
   - Shallow sandy area perfect for toddlers
   - Large parking lot 100m from beach
   - Natural shade + umbrella rentals
   - Playground and changing facilities
   - Current weather: 26°C, sunny

2. **Ploče Beach, Split**
   - Pebble beach with gradual depth
   - Parking available (200m walk)
   - Shaded areas under pine trees
   - Family-friendly facilities

3. **Žnjan Beach, Split**
   - Long sandy beach, shallow water
   - Free parking nearby
   - Beach bar with kids menu
   - Lifeguard on duty
```

**Activity-Based Recommendations**:
```
For snorkeling in Istria region:

1. **Kamenjak Beach** - Best underwater visibility
2. **Verudela Beach** - Rocky areas with marine life
3. **Rt Savudrija** - Shallow reefs, beginner-friendly
```

### 5.3 Generated Content

**Comparison Tables**:
```markdown
| Feature              | Banje Beach | Copacabana Beach |
|---------------------|-------------|------------------|
| Surface             | Pebble      | Pebble           |
| Facilities          | 12          | 8                |
| Accessibility       | ✅ Yes      | ⚠️ Limited       |
| Parking             | Paid        | Free             |
| Restaurant          | ✅ Yes      | ✅ Yes           |
| Current Weather     | 28°C ☀️    | 28°C ☀️         |
| Occupancy (Est.)    | High        | Medium           |
```

**Itineraries**:
```
Beach-Hopping Day in Zadar:

Morning (9:00-12:00): Kolovare Beach
- City beach, easy access, coffee nearby
- 15-min walk from Old Town

Lunch (12:00-14:00): Borik Beach
- Beach restaurant with local cuisine
- 10-min drive from Kolovare

Afternoon (14:00-17:00): Zaton Beach
- Quieter, family-friendly
- 15-min drive from Borik

Sunset (18:00-20:00): Puntamika Beach
- West-facing, stunning sunset views
- 20-min drive from Zaton
```

**Educational Content**:
```
🏖️ Beach Types in Croatia:

**Sandy Beaches** (15% of beaches)
- Softer surface, warmer water
- Popular with families
- Examples: Sakarun, Bačvice

**Pebble Beaches** (70% of beaches)
- Clearer water, better snorkeling
- Requires beach shoes
- Examples: Zlatni Rat, Banje

**Rocky Beaches** (15% of beaches)
- Best for diving, exploring
- Less crowded
- Examples: Kamenjak, Stiniva
```

### 5.4 Automated Actions

**None** - Agent is purely informational, no automated actions triggered

**Future Consideration** (not in v1.0):
- Email itinerary to user
- Save favorite beaches to profile
- Set weather alerts for planned beach visits

---

## 6. Safety & Guardrails

### 6.1 What Must Never Be Exposed

**System Internals**:
- ❌ Database credentials, API keys, secrets
- ❌ Server IP addresses, infrastructure details
- ❌ Admin panel URLs or access methods
- ❌ Internal service endpoints (Meilisearch, Redis)
- ❌ Error stack traces, debug information

**Sensitive Data**:
- ❌ Other users' conversations or queries
- ❌ User IP addresses, session tokens
- ❌ Analytics data (which beaches users viewed)
- ❌ A/B test configurations, feature flags

**Business Logic**:
- ❌ Exact cache TTL values, invalidation strategies
- ❌ Search ranking algorithms, scoring formulas
- ❌ Cost structure (API calls, CDN bandwidth)

### 6.2 What Must Be Redacted

**User Input Sanitization**:
- Remove SQL injection attempts: `'; DROP TABLE beaches; --`
- Remove XSS attempts: `<script>alert('xss')</script>`
- Remove profanity and offensive language from queries
- Truncate excessively long inputs (> 500 characters)

**Response Filtering**:
- Redact any accidentally exposed email addresses
- Redact phone numbers (except official beach contact info)
- Remove any HTML/JavaScript from beach descriptions
- Filter out broken image URLs (return placeholder instead)

### 6.3 How Hallucinations Are Prevented

**Grounding in Database**:
- ✅ Agent ONLY uses data from Beachrex database (no external knowledge)
- ✅ All beach attributes come from structured fields (not generated)
- ✅ Weather data comes from OpenWeatherMap API (not predicted)
- ✅ Translations come from translation tables (not AI-translated)

**Explicit Uncertainty Handling**:
```
User: "Is this beach safe for swimming?"
Agent: "This beach has a lifeguard on duty and first aid station. 
        However, I don't have real-time data on water conditions 
        (currents, jellyfish, etc.). Please check local warnings 
        before swimming."
```

**No Speculation**:
- ❌ Agent does NOT predict future weather
- ❌ Agent does NOT estimate prices (unless in database)
- ❌ Agent does NOT make safety guarantees
- ❌ Agent does NOT recommend medical/health advice

**Confidence Scoring**:
```
High Confidence: "This beach has 12 facilities including showers and toilets"
                 (Data from database, factual)

Medium Confidence: "This beach is typically less crowded on weekdays"
                   (Based on historical occupancy_rate field)

Low Confidence: "Water temperature is approximately 23°C today"
                (Weather API data, may be slightly inaccurate)

No Data: "I don't have information about jellyfish presence at this beach"
         (Explicit admission of missing data)
```

**Fallback Responses**:
- If no beaches match filters → "No beaches found matching your criteria. Try relaxing some filters."
- If weather API fails → "Weather data temporarily unavailable. Beach details still shown."
- If ambiguous query → "Did you mean [Option A] or [Option B]? Please clarify."

---

## 7. Monetization Model

### 7.1 Pricing Axis

**Primary Model**: Usage-Based (API Calls)

**Tiers**:

#### Free Tier
- **Price**: $0/month
- **Limits**: 100 queries/month
- **Features**:
  - Basic beach search (up to 3 filters)
  - List view only (no map)
  - Weather data (current only)
  - 5 results per query
  - English language only
- **Target**: Casual users, trial users

#### Explorer Tier
- **Price**: $9/month or $0.10/query (pay-as-you-go)
- **Limits**: 1,000 queries/month
- **Features**:
  - Advanced search (unlimited filters)
  - Map view with spatial queries
  - Weather data (current + 3-day forecast)
  - 20 results per query
  - All 19 languages
  - Beach comparisons (up to 3 beaches)
  - 360° virtual tours
- **Target**: Frequent travelers, vacation planners

#### Pro Tier
- **Price**: $29/month or $0.05/query (bulk discount)
- **Limits**: 5,000 queries/month
- **Features**:
  - Everything in Explorer
  - Unlimited results per query
  - Personalized recommendations (AI-powered)
  - Itinerary generation
  - Priority support
  - API access (for developers)
  - Export results (CSV, PDF)
- **Target**: Travel agencies, tour operators, power users

#### Enterprise Tier
- **Price**: Custom (starts at $299/month)
- **Limits**: Unlimited queries
- **Features**:
  - Everything in Pro
  - White-label embedding
  - Custom branding
  - Dedicated support
  - SLA guarantees (99.9% uptime)
  - Custom integrations
  - Analytics dashboard
- **Target**: Tourism boards, hotel chains, travel platforms

### 7.2 Feature Gating Per Plan

| Feature                          | Free | Explorer | Pro | Enterprise |
|----------------------------------|------|----------|-----|------------|
| **Queries/Month**                | 100  | 1,000    | 5,000 | Unlimited |
| **Results per Query**            | 5    | 20       | Unlimited | Unlimited |
| **Filters**                      | 3    | Unlimited | Unlimited | Unlimited |
| **Languages**                    | 1    | 14       | 14  | 14         |
| **Map View**                     | ❌   | ✅       | ✅  | ✅         |
| **Spatial Queries**              | ❌   | ✅       | ✅  | ✅         |
| **Weather Forecast**             | ❌   | 3-day    | 7-day | 7-day    |
| **Beach Comparisons**            | ❌   | 3 beaches | Unlimited | Unlimited |
| **360° Tours**                   | ❌   | ✅       | ✅  | ✅         |
| **AI Recommendations**           | ❌   | ❌       | ✅  | ✅         |
| **Itinerary Generation**         | ❌   | ❌       | ✅  | ✅         |
| **API Access**                   | ❌   | ❌       | ✅  | ✅         |
| **Export (CSV/PDF)**             | ❌   | ❌       | ✅  | ✅         |
| **White-Label Embedding**        | ❌   | ❌       | ❌  | ✅         |
| **Custom Integrations**          | ❌   | ❌       | ❌  | ✅         |
| **Analytics Dashboard**          | ❌   | ❌       | ❌  | ✅         |
| **Support**                      | Community | Email | Priority | Dedicated |

### 7.3 Overage Handling

**Free Tier**:
- After 100 queries → Soft paywall (upgrade prompt)
- Can purchase Explorer tier or pay-as-you-go ($0.10/query)

**Explorer/Pro Tiers**:
- After monthly limit → Automatic overage billing at tier rate
- Explorer: $0.10/query overage
- Pro: $0.05/query overage
- Email notification at 80%, 90%, 100% of quota

**Rate Limiting**:
- Free: 10 queries/hour
- Explorer: 100 queries/hour
- Pro: 500 queries/hour
- Enterprise: No rate limit

---

## 8. Success Metrics

### 8.1 Customer Success Signals

**Engagement Metrics**:
- **Query Completion Rate**: % of queries that return results (target: >90%)
- **Refinement Rate**: % of users who refine search after initial results (target: 30-40%)
- **Click-Through Rate**: % of results clicked for details (target: >60%)
- **Session Duration**: Average time spent interacting with agent (target: 5-10 min)
- **Return Rate**: % of users who return within 7 days (target: >40%)

**Satisfaction Metrics**:
- **Thumbs Up/Down**: Explicit feedback on agent responses (target: >80% positive)
- **Query Abandonment**: % of queries abandoned mid-conversation (target: <15%)
- **Upgrade Rate**: % of Free users who upgrade to paid tier (target: >10%)
- **Churn Rate**: % of paid users who cancel (target: <5%/month)

**Outcome Metrics**:
- **Beach Visit Confirmation**: % of users who report visiting recommended beach (survey)
- **Recommendation Accuracy**: % of users satisfied with AI recommendations (target: >85%)
- **Time-to-Decision**: Average time from first query to beach selection (target: <5 min)

### 8.2 Business KPIs

**Revenue Metrics**:
- **MRR (Monthly Recurring Revenue)**: Total subscription revenue per month
- **ARPU (Average Revenue Per User)**: MRR / Total Active Users
- **LTV (Lifetime Value)**: Average revenue per customer over lifetime
- **CAC (Customer Acquisition Cost)**: Marketing spend / New customers
- **LTV:CAC Ratio**: Target >3:1

**Growth Metrics**:
- **New Signups**: Free tier signups per month (target: 1,000+/month)
- **Conversion Rate**: Free → Paid conversion (target: >10%)
- **Expansion Revenue**: Upgrades from Explorer → Pro (target: 20% of Explorer users)
- **Net Revenue Retention**: (MRR + Expansion - Churn) / MRR (target: >100%)

**Usage Metrics**:
- **Total Queries**: All queries across all tiers (target: 100,000+/month)
- **Queries per User**: Average queries per active user (target: 20+/month)
- **API Calls**: Backend API calls per agent query (efficiency metric, target: <5)
- **Cache Hit Rate**: % of queries served from cache (target: >70%)

**Operational Metrics**:
- **Response Time**: Average agent response latency (target: <2 seconds)
- **Error Rate**: % of queries that fail (target: <1%)
- **Uptime**: System availability (target: 99.9% for Enterprise, 99.5% for others)
- **Support Tickets**: Customer support requests per 1,000 users (target: <10)

**Product Metrics**:
- **Feature Adoption**: % of users using advanced features (map, comparisons, itineraries)
- **Language Distribution**: Usage across 19 locales (identify growth opportunities)
- **Filter Usage**: Most-used filters (inform product roadmap)
- **Beach Coverage**: % of beaches in database that get queried (target: >60%)

---

**End of Customer-Facing Agent Definition**

This agent is the primary commercial offering of the Beachrex platform. It transforms beach discovery from a tedious research process into a conversational, personalized experience. The agent is strictly read-only, grounded in database facts, and monetized through usage-based pricing tiers. Success is measured by customer engagement, satisfaction, and revenue growth.

