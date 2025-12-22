# Beachrex AI Agent - Execution Model

**Version**: 1.0  
**Last Updated**: 2025-12-22  
**Status**: Production Design  
**Purpose**: Defines how the AI agent executes in production environment

---

## 1. Invocation Model

### Model Type: **Hybrid (Chat-Based Primary + Task-Based Fallback)**

#### 1.1 Chat-Based Invocation (Primary)

**Use Case**: Conversational beach discovery with multi-turn dialogue

**Flow**:
```
User: "Show me family-friendly beaches in Croatia"
Agent: [Returns 10 beaches with filters applied]

User: "Which ones have playgrounds?"
Agent: [Refines previous results, applies playground filter]

User: "What's the weather like at the first one?"
Agent: [Fetches weather for specific beach from previous context]
```

**Characteristics**:
- **Session-based**: Maintains conversation context for 30 minutes
- **Multi-turn**: Supports follow-up questions referencing previous results
- **Context-aware**: Remembers user preferences within session (e.g., "family-friendly")
- **Natural language**: Accepts conversational queries, not just keywords

**Implementation**:
- Session ID generated on first message
- Context stored in Redis with 30-minute TTL
- Each message includes session_id in request header

#### 1.2 Task-Based Invocation (Fallback)

**Use Case**: Direct API calls for specific tasks (e.g., "get recommendations")

**Flow**:
```
POST /api/v1/agent/recommend
{
  "task": "recommend_beaches",
  "parameters": {
    "user_type": "family",
    "country": "HR",
    "facilities": ["playground", "parking"],
    "limit": 5
  }
}
```

**Characteristics**:
- **Stateless**: No session context required
- **Single-turn**: One request, one response
- **Structured input**: JSON parameters, not natural language
- **Deterministic**: Same input always produces same output (cache-friendly)

**Implementation**:
- Direct API endpoint: `/api/v1/agent/task`
- No session management
- Suitable for programmatic access (API tier customers)

#### 1.3 Hybrid Decision Logic

**When to use Chat-Based**:
- User sends natural language query
- Request includes session_id (continuing conversation)
- Query references previous context ("the first one", "those beaches")

**When to use Task-Based**:
- Request is structured JSON (not natural language)
- No session_id provided
- Programmatic API call (detected via User-Agent or API key)

**Fallback**:
- If chat-based fails (context too large, session expired) → retry as task-based
- If task-based fails (invalid parameters) → return structured error (no retry)

---

## 2. Context Assembly

### 2.1 Data Injected Per Request

**Priority Order** (highest to lowest):

#### Priority 1: User Query & Session Context (Always Included)
- **User message**: Current query (max 500 characters)
- **Session history**: Last 5 messages (user + agent) from Redis
- **User preferences**: Extracted from session (e.g., "family", "wheelchair access")
- **Locale**: From Accept-Language header (default: en)
- **Tier**: Free, Explorer, Pro, Enterprise (affects limits)

**Size**: ~2-5KB per request

#### Priority 2: Search Results (If Query Requires Search)
- **Meilisearch results**: Top 20 results from global search
- **Beach list**: Top 20 beaches matching filters
- **Geographic entities**: Relevant countries/regions/cities (max 10)

**Size**: ~10-50KB per request (depends on result count)

#### Priority 3: Beach Details (If Specific Beach Referenced)
- **Full beach data**: From `/api/v1/beach-details/{id}` endpoint
- **Weather data**: Current weather (if requested or relevant)
- **Media**: Primary image URL only (not full gallery)
- **Relationships**: Facilities, activities, badges (summarized)

**Size**: ~5-20KB per beach (max 3 beaches in context)

#### Priority 4: Reference Data (If Needed for Interpretation)
- **Filter options**: Available values for filters (e.g., beach types, facilities)
- **Translations**: Localized names for entities
- **Definitions**: Badge/facility definitions (if user asks "what is X?")

**Size**: ~5-10KB (cached, rarely changes)

#### Priority 5: System Instructions (Always Included)
- **Agent role**: "You are a beach discovery assistant..."
- **Capabilities**: What agent can/cannot do
- **Response format**: JSON structure, tone, language
- **Safety rules**: No speculation, grounded in data, explicit uncertainty

**Size**: ~3-5KB (static, same for all requests)

### 2.2 Hard Limits on Context Size

**Total Context Budget**: 32,000 tokens (~128KB text)

**Breakdown**:
- **System instructions**: 1,000 tokens (fixed)
- **User query + session**: 2,000 tokens (max)
- **Search results**: 10,000 tokens (max 20 results)
- **Beach details**: 15,000 tokens (max 3 beaches)
- **Reference data**: 2,000 tokens (cached definitions)
- **Response buffer**: 2,000 tokens (agent's answer)

**Overflow Handling**:
1. If context exceeds 32K tokens → truncate in reverse priority order
2. Priority 4 (reference data) dropped first
3. Priority 3 (beach details) limited to 1 beach instead of 3
4. Priority 2 (search results) limited to 10 instead of 20
5. Priority 1 (session history) limited to 3 messages instead of 5
6. If still exceeds → return error "Query too complex, please simplify"

**Monitoring**:
- Log context size per request
- Alert if >80% of requests hit truncation
- Track which priority levels are most frequently truncated

### 2.3 Priority of Sources

**Decision Tree**:

```
User Query: "Show me beaches near Split"
  ↓
1. Parse intent: Location-based search
  ↓
2. Check Meilisearch: Search "Split" → Find city entity
  ↓
3. Query Database: GET /api/v1/beaches?city=split
  ↓
4. Assemble context: City info + beach list
  ↓
5. Generate response: "Found 15 beaches in Split..."
```

**Source Priority Rules**:
- **Search first**: If query is ambiguous or exploratory → Meilisearch
- **Database second**: If query has specific filters → Direct API call
- **API third**: If query needs external data (weather) → OpenWeatherMap
- **Cache always**: Check cache before hitting database or API

**Example Scenarios**:

| Query | Source Priority | Reasoning |
|-------|----------------|-----------|
| "Beaches in Croatia" | DB → Search | Specific filter (country=HR), no search needed |
| "Best beach for snorkeling" | Search → DB | Ambiguous "best", search for snorkeling beaches |
| "Weather at Banje Beach" | Search → DB → API | Find beach (search), get details (DB), fetch weather (API) |
| "What is Blue Flag?" | Cache → DB | Definition query, likely cached |

---

## 3. Memory Model

### 3.1 Session Memory (Short-Term)

**Storage**: Redis  
**TTL**: 30 minutes (sliding window, resets on each message)  
**Scope**: Per session_id (UUID generated on first message)

**Stored Data**:
```json
{
  "session_id": "550e8400-e29b-41d4-a716-446655440000",
  "created_at": "2025-12-22T14:30:00Z",
  "last_activity": "2025-12-22T14:35:00Z",
  "locale": "en",
  "tier": "explorer",
  "messages": [
    {
      "role": "user",
      "content": "Show me family beaches in Croatia",
      "timestamp": "2025-12-22T14:30:00Z"
    },
    {
      "role": "agent",
      "content": "Found 25 family-friendly beaches...",
      "timestamp": "2025-12-22T14:30:05Z",
      "metadata": {
        "filters_applied": {"country": "HR", "user_type": "family"},
        "result_count": 25
      }
    }
  ],
  "context": {
    "user_preferences": {
      "user_type": "family",
      "country": "HR"
    },
    "last_results": {
      "beach_ids": [123, 456, 789],
      "entity_type": "beach"
    }
  }
}
```

**Limits**:
- Max 10 messages per session (5 user + 5 agent)
- Max 50KB per session (if exceeded, drop oldest messages)
- Max 1,000 active sessions per server (LRU eviction)

**Isolation**:
- Sessions are isolated by session_id (no cross-session access)
- No user authentication required (anonymous sessions)
- Session data never persisted to database (Redis only)

### 3.2 Long-Term Memory (None for v1.0)

**Rationale**: Privacy-first design, no user accounts

**Explicitly NOT Stored**:
- ❌ User search history across sessions
- ❌ Favorite beaches or saved preferences
- ❌ User location or GPS coordinates
- ❌ Conversation history beyond 30 minutes
- ❌ User profile or demographic data

**Future Consideration** (v2.0+):
- If user authentication added → opt-in long-term memory
- Stored preferences: favorite beaches, accessibility needs
- Requires GDPR compliance, privacy policy updates

### 3.3 Per-Customer Isolation Rules

**Free Tier**:
- Session memory only (30 minutes)
- No cross-session data
- Sessions purged immediately on expiration

**Paid Tiers** (Explorer, Pro, Enterprise):
- Same session memory model
- No additional long-term memory (v1.0)
- API key tracked for usage metering only (not linked to sessions)

**Enterprise Tier** (White-Label):
- Isolated Redis namespace per customer
- Customer-specific system instructions (branding)
- No data sharing between enterprise customers

**Security**:
- Session IDs are UUIDs (not sequential, not guessable)
- No session hijacking (session_id validated per request)
- Redis data encrypted at rest (if supported by hosting)

---

## 4. Action Model

### 4.1 Read-Only Answers (Primary Mode)

**Scope**: 95% of agent interactions

**Allowed Actions**:
- ✅ Query beaches with filters
- ✅ Search across entities (Meilisearch)
- ✅ Fetch beach details
- ✅ Fetch weather data
- ✅ Perform spatial queries (PostGIS)
- ✅ Aggregate statistics (e.g., "How many beaches in Croatia?")
- ✅ Compare beaches side-by-side
- ✅ Generate recommendations

**Response Format**:
```json
{
  "type": "answer",
  "content": "Found 15 family-friendly beaches in Split...",
  "data": {
    "beaches": [...],
    "filters_applied": {...},
    "result_count": 15
  },
  "sources": [
    {"type": "api", "endpoint": "/api/v1/beaches", "cache": "HIT"}
  ]
}
```

### 4.2 Action Proposals (Informational Only)

**Scope**: 5% of interactions (when agent suggests next steps)

**Use Cases**:
- "Would you like me to show beaches with playgrounds?"
- "I can compare these 3 beaches for you. Should I proceed?"
- "Shall I find beaches within 10km of your location?"

**Response Format**:
```json
{
  "type": "proposal",
  "content": "I found 25 beaches. Would you like me to filter by facilities?",
  "proposed_actions": [
    {
      "action_id": "filter_by_facilities",
      "label": "Show only beaches with showers and parking",
      "parameters": {"beach_facility": ["shower", "parking"]}
    },
    {
      "action_id": "show_on_map",
      "label": "View these beaches on a map",
      "parameters": {"beach_ids": [123, 456, 789]}
    }
  ]
}
```

**User Confirmation Required**:
- User must explicitly confirm action (click button or type "yes")
- Agent does NOT auto-execute proposed actions
- Proposals expire after 5 minutes (not stored in session)

### 4.3 Auto-Executed Actions (None in v1.0)

**Explicitly Forbidden**:
- ❌ Modifying beach data
- ❌ Creating user accounts
- ❌ Sending emails or notifications
- ❌ Making reservations or bookings
- ❌ Charging user's payment method
- ❌ Sharing data with third parties

**Rationale**: Safety-first design, read-only agent

**Future Consideration** (v2.0+):
- Email itinerary to user (with explicit consent)
- Save favorite beaches to profile (if authentication added)
- Set weather alerts (opt-in feature)

---

## 5. Failure Handling

### 5.1 Partial Answers (Graceful Degradation)

**Scenario 1**: Weather API fails, but beach data available
```json
{
  "type": "partial_answer",
  "content": "Here are 5 beaches in Split. Weather data is temporarily unavailable.",
  "data": {
    "beaches": [...],
    "weather": null
  },
  "warnings": [
    {
      "code": "WEATHER_API_UNAVAILABLE",
      "message": "Weather data could not be fetched. Please try again later."
    }
  ]
}
```

**Scenario 2**: Meilisearch down, fallback to database search
```json
{
  "type": "partial_answer",
  "content": "Found 10 beaches matching your criteria (search limited).",
  "data": {
    "beaches": [...],
    "search_quality": "degraded"
  },
  "warnings": [
    {
      "code": "SEARCH_DEGRADED",
      "message": "Search functionality is limited. Results may be incomplete."
    }
  ]
}
```

**Scenario 3**: Some beaches missing translations
```json
{
  "type": "partial_answer",
  "content": "Found 8 beaches (2 excluded due to missing translations).",
  "data": {
    "beaches": [...],
    "excluded_count": 2
  },
  "warnings": [
    {
      "code": "INCOMPLETE_TRANSLATIONS",
      "message": "Some beaches were excluded because translations are unavailable in your language."
    }
  ]
}
```

### 5.2 Fallback Behavior

**Fallback Chain**:

1. **Primary**: Meilisearch + Database + Weather API
   ↓ (if Meilisearch fails)
2. **Fallback 1**: Database only (no global search)
   ↓ (if Database slow/timeout)
3. **Fallback 2**: Cached data only (stale acceptable)
   ↓ (if Cache miss)
4. **Fallback 3**: Error response with retry suggestion

**Timeout Handling**:
- API calls: 5-second timeout
- Database queries: 10-second timeout
- Meilisearch: 2-second timeout
- Weather API: 3-second timeout

**Retry Logic**:
- Automatic retry: 1 time with exponential backoff (1 second)
- User-initiated retry: Allowed (no automatic retry loop)
- Circuit breaker: After 5 consecutive failures, disable service for 1 minute

### 5.3 User-Visible vs Silent Errors

**User-Visible Errors** (show to user):
- ❌ "No beaches found matching your criteria" (empty result set)
- ❌ "Weather data temporarily unavailable" (external API failure)
- ❌ "Query too complex, please simplify" (context overflow)
- ❌ "Rate limit exceeded, please upgrade or wait" (quota exhausted)
- ❌ "Invalid location coordinates" (user input error)

**Silent Errors** (log only, don't show):
- 🔇 Cache miss (fallback to database)
- 🔇 Slow query (< 10s, still returns results)
- 🔇 Partial translation missing (use English fallback)
- 🔇 Image URL broken (use placeholder)
- 🔇 Non-critical metadata missing (e.g., beach_views counter)

**Error Response Format**:
```json
{
  "type": "error",
  "error": {
    "code": "RATE_LIMIT_EXCEEDED",
    "message": "You've reached your query limit for this hour. Please upgrade to Explorer tier or wait 45 minutes.",
    "user_message": "Rate limit exceeded",
    "retry_after": 2700,
    "upgrade_url": "https://beachrex.com/pricing"
  }
}
```

**Logging**:
- All errors logged to application log (Laravel log)
- Critical errors (500, database down) → alert DevOps
- User errors (400, rate limit) → metrics only (no alert)

---

## 6. Cost Control

### 6.1 Token Budgeting

**Per-Request Budget**:
- **Free Tier**: 4,000 tokens max (input + output)
- **Explorer Tier**: 8,000 tokens max
- **Pro Tier**: 16,000 tokens max
- **Enterprise Tier**: 32,000 tokens max

**Token Allocation**:
```
Input Tokens (Context):
- System instructions: 1,000 tokens (fixed)
- User query + session: 1,000-2,000 tokens
- Search results: 2,000-10,000 tokens (tier-dependent)
- Beach details: 1,000-15,000 tokens (tier-dependent)

Output Tokens (Response):
- Free: Max 500 tokens (~400 words)
- Explorer: Max 1,000 tokens (~800 words)
- Pro: Max 2,000 tokens (~1,600 words)
- Enterprise: Max 4,000 tokens (~3,200 words)
```

**Enforcement**:
- Pre-flight check: Estimate input tokens before API call
- If exceeds budget → truncate context (see Section 2.2)
- Post-flight check: Truncate output if exceeds token limit
- Log token usage per request for billing

### 6.2 Hard Caps

**Per-User Caps** (by tier):

| Tier | Queries/Hour | Queries/Day | Queries/Month | Token Budget/Query |
|------|--------------|-------------|---------------|-------------------|
| Free | 10 | 100 | 100 | 4,000 |
| Explorer | 100 | 1,000 | 1,000 | 8,000 |
| Pro | 500 | 5,000 | 5,000 | 16,000 |
| Enterprise | Unlimited | Unlimited | Unlimited | 32,000 |

**Enforcement**:
- Rate limiting via Redis (sliding window)
- Key: `rate_limit:{tier}:{user_id_or_ip}:{hour|day|month}`
- Response: 429 Too Many Requests with Retry-After header

**Overage Handling**:
- Free: Hard block (no overage allowed)
- Explorer/Pro: Automatic overage billing at tier rate
- Enterprise: No overage (unlimited)

**Cost Monitoring**:
- Track token usage per tier per month
- Alert if Free tier users consistently hit limits (upsell opportunity)
- Alert if Pro tier users exceed expected usage (fraud detection)

### 6.3 Graceful Degradation

**Degradation Levels**:

**Level 0: Normal Operation**
- All features enabled
- Full context (32K tokens)
- Real-time weather data
- Meilisearch + Database + API

**Level 1: Minor Degradation** (80% capacity)
- Reduce context to 24K tokens
- Limit search results to 15 (instead of 20)
- Cache weather data for 2 hours (instead of 1)
- All features still available

**Level 2: Moderate Degradation** (60% capacity)
- Reduce context to 16K tokens
- Limit search results to 10
- Disable weather API (use cached data only)
- Disable 360° tour links (reduce response size)

**Level 3: Severe Degradation** (40% capacity)
- Reduce context to 8K tokens
- Limit search results to 5
- Disable Meilisearch (database search only)
- Disable media URLs (text-only responses)
- Show banner: "Service degraded, some features limited"

**Level 4: Emergency Mode** (20% capacity)
- Cached responses only (no live queries)
- Show banner: "Service experiencing issues, please try again later"
- Queue requests for processing when capacity recovers

**Triggers**:
- CPU usage > 80% → Level 1
- Database response time > 5s → Level 2
- Error rate > 10% → Level 3
- Error rate > 50% → Level 4

**Recovery**:
- Auto-recover when metrics return to normal for 5 minutes
- Manual override available (DevOps can force degradation level)

---

## Production Readiness Checklist

### Monitoring
- [ ] Token usage per request logged
- [ ] Context size per request logged
- [ ] API latency per endpoint tracked
- [ ] Error rate per error code tracked
- [ ] Cache hit rate monitored
- [ ] Rate limit violations logged

### Alerting
- [ ] Alert if error rate > 5% for 5 minutes
- [ ] Alert if average latency > 3s for 5 minutes
- [ ] Alert if cache hit rate < 50%
- [ ] Alert if token budget exceeded > 10% of requests
- [ ] Alert if external API (weather) down

### Scaling
- [ ] Horizontal scaling: Add more API servers
- [ ] Redis cluster: Session data distributed
- [ ] Database read replicas: Reduce load on primary
- [ ] CDN: Cache static responses (reference data)
- [ ] Load balancer: Distribute requests evenly

### Disaster Recovery
- [ ] Redis backup: Daily snapshots
- [ ] Database backup: Hourly incremental, daily full
- [ ] Failover: Automatic switch to replica if primary down
- [ ] Circuit breaker: Disable failing services automatically
- [ ] Rollback plan: Revert to previous version in < 5 minutes

---

**End of Execution Model**

This document defines how the Beachrex AI agent executes in production. It prioritizes reliability, cost control, and graceful degradation over feature completeness. All decisions are designed for production scale, not demos.

