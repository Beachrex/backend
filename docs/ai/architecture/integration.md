# Beachrex AI Agent - Platform Integration

**Version**: 1.0  
**Last Updated**: 2025-12-22  
**Status**: Production Integration Design  
**Purpose**: Defines how AI agent integrates with existing Beachrex platform

---

## 1. Where the AI Lives

### Architecture Decision: **Dedicated AI Service (Microservice)**

**Rationale**:
- **Isolation**: AI failures don't crash main API
- **Scalability**: Scale AI service independently (CPU-intensive LLM calls)
- **Cost Control**: Monitor and limit AI costs separately from core platform
- **Deployment**: Update AI logic without redeploying main API
- **Security**: Separate attack surface (AI prompt injection vs SQL injection)

### Component Diagram

```
┌─────────────────────────────────────────────────────────────────┐
│                         Frontend (Next.js)                       │
│                    https://beachrex.com                          │
└────────────────────────────┬────────────────────────────────────┘
                             │
                             │ HTTPS
                             ▼
┌─────────────────────────────────────────────────────────────────┐
│                    API Gateway / Load Balancer                   │
│                      https://api.beachrex.com                    │
└───────────┬──────────────────────────────┬──────────────────────┘
            │                              │
            │ /api/v1/beaches              │ /api/v1/ai/*
            │ /api/v1/countries            │
            ▼                              ▼
┌───────────────────────────┐  ┌──────────────────────────────────┐
│   Main API (Laravel 11)   │  │   AI Service (Laravel 11)        │
│   Port: 8000              │  │   Port: 8001                     │
│                           │  │                                  │
│   Controllers:            │  │   Controllers:                   │
│   - BeachController       │  │   - AgentController              │
│   - CountryController     │  │   - SessionController            │
│   - SearchController      │  │   - ChatController               │
│                           │  │                                  │
│   Services:               │  │   Services:                      │
│   - BeachFilterService    │  │   - ContextAssemblyService       │
│   - CacheService          │  │   - LLMService (OpenAI/Anthropic)│
│   - WeatherService        │  │   - PermissionService            │
│                           │  │   - CostTrackingService          │
└───────────┬───────────────┘  └──────────┬───────────────────────┘
            │                             │
            │                             │ Internal API calls
            │                             │ (http://localhost:8000)
            │                             │
            ▼                             ▼
┌─────────────────────────────────────────────────────────────────┐
│                    Shared Infrastructure                         │
│                                                                  │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐          │
│  │  PostgreSQL  │  │    Redis     │  │ Meilisearch  │          │
│  │  (Database)  │  │   (Cache +   │  │   (Search)   │          │
│  │              │  │   Sessions)  │  │              │          │
│  └──────────────┘  └──────────────┘  └──────────────┘          │
└─────────────────────────────────────────────────────────────────┘
```

### Service Responsibilities

#### Main API (Existing Laravel App)
- **Owns**: Beach data, geographic entities, reference data
- **Responsibilities**: CRUD operations, filtering, search, spatial queries
- **Authentication**: Laravel Sanctum (session + token-based)
- **Endpoints**: `/api/v1/beaches`, `/api/v1/countries`, `/api/v1/search`, etc.
- **No Changes Required**: Existing API remains unchanged

#### AI Service (New Microservice)
- **Owns**: AI agent logic, session management, LLM integration
- **Responsibilities**: Chat, recommendations, context assembly, cost tracking
- **Authentication**: Validates tokens via Main API (shared Sanctum)
- **Endpoints**: `/api/v1/ai/chat`, `/api/v1/ai/session`, `/api/v1/ai/recommend`
- **Internal Client**: Calls Main API for beach data (server-to-server)

### Deployment Model

**Development**:
- Main API: `http://localhost:8000`
- AI Service: `http://localhost:8001`
- Both run via Docker Compose or Laravel Sail

**Production**:
- Main API: `https://api.beachrex.com` (existing)
- AI Service: `https://ai.beachrex.com` (new subdomain)
- Or: Single domain with path-based routing (`/api/v1/ai/*` → AI service)

### Why NOT Hybrid (AI in Main API)?

**Rejected Reasons**:
- ❌ **Coupling**: AI failures crash main API
- ❌ **Scaling**: Can't scale AI independently (LLM calls are CPU-heavy)
- ❌ **Cost**: Hard to isolate AI costs from core platform costs
- ❌ **Deployment**: Every AI update requires full API deployment
- ❌ **Testing**: AI changes risk breaking core API

---

## 2. Request Flow

### 2.1 Chat Request Flow (Primary Use Case)

```
┌──────┐                                                          ┌─────────┐
│ User │                                                          │ LLM API │
└───┬──┘                                                          └────▲────┘
    │                                                                  │
    │ 1. POST /api/v1/ai/chat                                          │
    │    { session_id, message, locale }                               │
    │    Authorization: Bearer {token}                                 │
    ▼                                                                  │
┌────────────┐                                                         │
│  Frontend  │                                                         │
│  (Next.js) │                                                         │
└─────┬──────┘                                                         │
      │                                                                │
      │ 2. Forward request                                             │
      ▼                                                                │
┌──────────────────────────────────────────────────────────────┐      │
│                      AI Service                              │      │
│                                                              │      │
│  ┌────────────────────────────────────────────────────────┐ │      │
│  │ 3. Validate Token (call Main API /api/v1/user)        │ │      │
│  │    - Get user ID, tier, permissions                    │ │      │
│  └────────────────────────────────────────────────────────┘ │      │
│                          │                                   │      │
│  ┌────────────────────────▼──────────────────────────────┐  │      │
│  │ 4. Check Rate Limits (Redis)                          │  │      │
│  │    - Key: rate_limit:{tier}:{user_id}:{hour}          │  │      │
│  │    - If exceeded → 429 Too Many Requests              │  │      │
│  └───────────────────────────────────────────────────────┘  │      │
│                          │                                   │      │
│  ┌────────────────────────▼──────────────────────────────┐  │      │
│  │ 5. Load Session Context (Redis)                       │  │      │
│  │    - Key: ai_session:{session_id}                     │  │      │
│  │    - Get last 5 messages, user preferences            │  │      │
│  └───────────────────────────────────────────────────────┘  │      │
│                          │                                   │      │
│  ┌────────────────────────▼──────────────────────────────┐  │      │
│  │ 6. Parse Intent (NLP / Rule-based)                    │  │      │
│  │    - "beaches in Croatia" → search query              │  │      │
│  │    - "weather at Banje" → beach detail + weather      │  │      │
│  │    - "compare X and Y" → multi-beach fetch            │  │      │
│  └───────────────────────────────────────────────────────┘  │      │
│                          │                                   │      │
│  ┌────────────────────────▼──────────────────────────────┐  │      │
│  │ 7. Assemble Context (ContextAssemblyService)          │  │      │
│  │    - Call Main API for data:                          │  │      │
│  │      GET http://localhost:8000/api/v1/beaches?...     │  │      │
│  │      GET http://localhost:8000/api/v1/search?...      │  │      │
│  │    - Apply user permissions (filter by tier)          │  │      │
│  │    - Limit results (Free: 5, Explorer: 20, Pro: 100)  │  │      │
│  └───────────────────────────────────────────────────────┘  │      │
│                          │                                   │      │
│  ┌────────────────────────▼──────────────────────────────┐  │      │
│  │ 8. Build LLM Prompt                                   │  │      │
│  │    - System instructions (role, capabilities)         │  │      │
│  │    - User query + session history                     │  │      │
│  │    - Beach data (JSON)                                │  │      │
│  │    - Total tokens: 4K-32K (tier-dependent)            │  │      │
│  └───────────────────────────────────────────────────────┘  │      │
│                          │                                   │      │
│  ┌────────────────────────▼──────────────────────────────┐  │      │
│  │ 9. Call LLM API ──────────────────────────────────────┼──┼──────┘
│  │    - OpenAI GPT-4 or Anthropic Claude                │  │
│  │    - Timeout: 30 seconds                              │  │
│  │    - Retry: 1 time with exponential backoff           │  │
│  └───────────────────────────────────────────────────────┘  │
│                          │                                   │
│  ┌────────────────────────▼──────────────────────────────┐  │
│  │ 10. Parse LLM Response                                │  │
│  │     - Extract answer, metadata, sources               │  │
│  │     - Validate JSON structure                         │  │
│  │     - Sanitize output (XSS prevention)                │  │
│  └───────────────────────────────────────────────────────┘  │
│                          │                                   │
│  ┌────────────────────────▼──────────────────────────────┐  │
│  │ 11. Track Costs (CostTrackingService)                 │  │
│  │     - Log: user_id, tokens_in, tokens_out, cost       │  │
│  │     - Store in database: ai_usage table               │  │
│  │     - Update monthly quota (if applicable)            │  │
│  └───────────────────────────────────────────────────────┘  │
│                          │                                   │
│  ┌────────────────────────▼──────────────────────────────┐  │
│  │ 12. Update Session (Redis)                            │  │
│  │     - Append user message + agent response            │  │
│  │     - Update last_activity timestamp                  │  │
│  │     - Reset TTL to 30 minutes                         │  │
│  └───────────────────────────────────────────────────────┘  │
│                          │                                   │
│  ┌────────────────────────▼──────────────────────────────┐  │
│  │ 13. Return Response                                   │  │
│  │     {                                                 │  │
│  │       "type": "answer",                               │  │
│  │       "content": "Found 15 beaches...",               │  │
│  │       "data": { beaches: [...] },                     │  │
│  │       "session_id": "...",                            │  │
│  │       "usage": { tokens: 5000, cost: 0.05 }           │  │
│  │     }                                                 │  │
│  └───────────────────────────────────────────────────────┘  │
└──────────────────────────────────────────────────────────────┘
      │
      │ 14. Display to user
      ▼
┌────────────┐
│  Frontend  │
└────────────┘
```

### 2.2 Anonymous vs Authenticated Flow

#### Anonymous User (No Token)
```
User → Frontend → AI Service
                   ├─ No token validation
                   ├─ Session ID only (UUID)
                   ├─ Free tier limits applied
                   ├─ IP-based rate limiting
                   └─ No cost tracking (free tier)
```

#### Authenticated User (With Token)
```
User → Frontend → AI Service
                   ├─ Validate token via Main API
                   ├─ Get user ID, tier, permissions
                   ├─ User-based rate limiting
                   ├─ Tier-specific limits (Explorer/Pro/Enterprise)
                   └─ Cost tracking per user
```

### 2.3 Internal API Calls (AI Service → Main API)

**Authentication**: Server-to-server (shared secret or internal network)

**Example**:
```php
// AI Service calls Main API
$response = Http::withHeaders([
    'Accept-Language' => $locale,
    'X-Internal-Secret' => config('app.internal_api_secret'),
])->get('http://localhost:8000/api/v1/beaches', [
    'country' => 'HR',
    'limit' => 20,
]);
```

**Security**:
- Internal API calls bypass rate limiting (trusted source)
- Use `X-Internal-Secret` header for authentication
- Main API validates secret in middleware
- Internal calls logged separately (audit trail)

---

## 3. Permission Enforcement

### 3.1 User Permission Model

**User Tiers** (from pricing.md):
- **Free**: Anonymous or registered, 100 queries/month
- **Explorer**: Registered, $9/month, 1,000 queries/month
- **Pro**: Registered, $29/month, 5,000 queries/month
- **Enterprise**: Registered, custom pricing, unlimited queries

**User Model** (existing):
```php
// app/Models/User.php
class User extends Authenticatable {
    protected $fillable = [
        'name', 'email', 'password', 'is_admin',
        'status_id', 'country_code',
        // AI-specific fields (to be added):
        'ai_tier',           // 'free', 'explorer', 'pro', 'enterprise'
        'ai_queries_used',   // Monthly query counter
        'ai_quota_reset_at', // Next reset date
    ];
}
```

### 3.2 Permission Enforcement Points

#### Point 1: Rate Limiting (Before AI Call)

**Service**: `PermissionService::checkRateLimit()`

```php
public function checkRateLimit(User $user): bool
{
    $tier = $user->ai_tier ?? 'free';
    $limits = [
        'free'       => ['hour' => 10, 'day' => 100, 'month' => 100],
        'explorer'   => ['hour' => 100, 'day' => 1000, 'month' => 1000],
        'pro'        => ['hour' => 500, 'day' => 5000, 'month' => 5000],
        'enterprise' => ['hour' => null, 'day' => null, 'month' => null],
    ];
    
    $key = "rate_limit:{$tier}:{$user->id}:hour";
    $count = Redis::incr($key);
    Redis::expire($key, 3600); // 1 hour
    
    if ($limits[$tier]['hour'] && $count > $limits[$tier]['hour']) {
        throw new RateLimitException("Hourly limit exceeded");
    }
    
    // Repeat for day and month
    return true;
}
```

#### Point 2: Data Scope Filtering (During Context Assembly)

**Service**: `PermissionService::filterResults()`

```php
public function filterResults(array $beaches, User $user): array
{
    $tier = $user->ai_tier ?? 'free';
    $limits = [
        'free'       => 5,
        'explorer'   => 20,
        'pro'        => 100,
        'enterprise' => null, // unlimited
    ];
    
    $limit = $limits[$tier];
    
    if ($limit && count($beaches) > $limit) {
        return [
            'beaches' => array_slice($beaches, 0, $limit),
            'total' => count($beaches),
            'limited' => true,
            'upgrade_message' => "Showing {$limit} of " . count($beaches) . " beaches. Upgrade to see all.",
        ];
    }
    
    return ['beaches' => $beaches, 'limited' => false];
}
```

#### Point 3: Feature Gating (Before Execution)

**Service**: `PermissionService::canUseFeature()`

```php
public function canUseFeature(User $user, string $feature): bool
{
    $tier = $user->ai_tier ?? 'free';
    
    $features = [
        'weather'       => ['explorer', 'pro', 'enterprise'],
        'map_view'      => ['explorer', 'pro', 'enterprise'],
        'comparison'    => ['explorer', 'pro', 'enterprise'],
        'itinerary'     => ['pro', 'enterprise'],
        'api_access'    => ['pro', 'enterprise'],
        'white_label'   => ['enterprise'],
    ];
    
    if (!isset($features[$feature])) {
        return true; // Feature not gated
    }
    
    return in_array($tier, $features[$feature]);
}
```

**Usage**:
```php
// In AgentController
if (!$this->permissionService->canUseFeature($user, 'weather')) {
    return response()->json([
        'type' => 'feature_blocked',
        'message' => 'Weather data is available on Explorer tier and above.',
        'upgrade_url' => 'https://beachrex.com/pricing',
    ], 403);
}
```

#### Point 4: Token Budget Enforcement (Before LLM Call)

**Service**: `PermissionService::getTokenBudget()`

```php
public function getTokenBudget(User $user): int
{
    $tier = $user->ai_tier ?? 'free';
    
    return match($tier) {
        'free'       => 4000,
        'explorer'   => 8000,
        'pro'        => 16000,
        'enterprise' => 32000,
        default      => 4000,
    };
}
```

### 3.3 Permission Context Injection

**Every AI request includes permission context**:

```json
{
  "user": {
    "id": 123,
    "tier": "explorer",
    "permissions": {
      "max_beaches_per_query": 20,
      "max_tokens": 8000,
      "features": ["weather", "map_view", "comparison"],
      "rate_limits": {
        "hour": 100,
        "day": 1000,
        "month": 1000
      }
    }
  }
}
```

**LLM System Prompt Includes Permissions**:
```
You are a beach discovery assistant for a user on the Explorer tier.

Constraints:
- Show maximum 20 beaches per response
- Weather data is available
- Itinerary generation is NOT available (Pro tier feature)
- If user requests blocked feature, suggest upgrade
```

---

## 4. Observability

### 4.1 Logging Strategy

#### Log Levels

**DEBUG**: Development only
- LLM prompts (full text)
- LLM responses (full text)
- Context assembly details

**INFO**: Production
- Request received (user_id, session_id, message)
- Response sent (type, tokens, latency)
- Feature usage (weather, comparison, itinerary)

**WARNING**: Production
- Rate limit approached (80%, 90%)
- Token budget exceeded (truncation occurred)
- Fallback triggered (Meilisearch down, using cache)

**ERROR**: Production + Alerts
- LLM API failure (timeout, 500 error)
- Permission check failed (invalid token)
- Database query failed

**CRITICAL**: Production + Immediate Alerts
- AI service down (health check failed)
- Redis down (session storage unavailable)
- Cost tracking failed (billing impact)

#### Log Structure (JSON)

```json
{
  "timestamp": "2025-12-22T14:35:00Z",
  "level": "INFO",
  "service": "ai-service",
  "event": "chat_request",
  "user_id": 123,
  "session_id": "550e8400-e29b-41d4-a716-446655440000",
  "tier": "explorer",
  "message": "Show me beaches in Croatia",
  "intent": "search",
  "context": {
    "beaches_fetched": 25,
    "beaches_returned": 20,
    "limited": true
  },
  "llm": {
    "provider": "openai",
    "model": "gpt-4",
    "tokens_in": 3500,
    "tokens_out": 800,
    "latency_ms": 2300,
    "cost_usd": 0.042
  },
  "response_type": "answer",
  "duration_ms": 3100
}
```

#### Log Storage

**Development**: Laravel log file (`storage/logs/laravel.log`)

**Production**:
- **Application Logs**: CloudWatch / Datadog / Papertrail
- **Structured Logs**: Elasticsearch (for querying)
- **Retention**: 30 days (90 days for Enterprise tier)

### 4.2 Cost Tracking

#### Database Schema

```sql
CREATE TABLE ai_usage (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT REFERENCES users(id),
    session_id UUID,
    tier VARCHAR(20),
    event_type VARCHAR(50), -- 'chat', 'recommend', 'compare'
    
    -- LLM metrics
    llm_provider VARCHAR(20), -- 'openai', 'anthropic'
    llm_model VARCHAR(50),    -- 'gpt-4', 'claude-3-opus'
    tokens_input INT,
    tokens_output INT,
    tokens_total INT,
    cost_usd DECIMAL(10, 6),
    
    -- Performance metrics
    latency_ms INT,
    cache_hit BOOLEAN,
    
    -- Context metrics
    beaches_fetched INT,
    beaches_returned INT,
    context_truncated BOOLEAN,
    
    -- Metadata
    locale VARCHAR(5),
    user_agent TEXT,
    ip_address INET,
    
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE INDEX idx_ai_usage_user_created ON ai_usage(user_id, created_at);
CREATE INDEX idx_ai_usage_tier_created ON ai_usage(tier, created_at);
CREATE INDEX idx_ai_usage_session ON ai_usage(session_id);
```

#### Cost Tracking Service

```php
class CostTrackingService
{
    public function track(array $data): void
    {
        DB::table('ai_usage')->insert([
            'user_id' => $data['user_id'],
            'session_id' => $data['session_id'],
            'tier' => $data['tier'],
            'event_type' => $data['event_type'],
            'llm_provider' => $data['llm_provider'],
            'llm_model' => $data['llm_model'],
            'tokens_input' => $data['tokens_input'],
            'tokens_output' => $data['tokens_output'],
            'tokens_total' => $data['tokens_input'] + $data['tokens_output'],
            'cost_usd' => $this->calculateCost($data),
            'latency_ms' => $data['latency_ms'],
            'cache_hit' => $data['cache_hit'] ?? false,
            'beaches_fetched' => $data['beaches_fetched'] ?? 0,
            'beaches_returned' => $data['beaches_returned'] ?? 0,
            'context_truncated' => $data['context_truncated'] ?? false,
            'locale' => $data['locale'],
            'user_agent' => $data['user_agent'],
            'ip_address' => $data['ip_address'],
            'created_at' => now(),
        ]);
    }
    
    private function calculateCost(array $data): float
    {
        // OpenAI GPT-4 pricing (example)
        $rates = [
            'gpt-4' => ['input' => 0.03 / 1000, 'output' => 0.06 / 1000],
            'gpt-3.5-turbo' => ['input' => 0.0015 / 1000, 'output' => 0.002 / 1000],
        ];
        
        $rate = $rates[$data['llm_model']] ?? $rates['gpt-4'];
        
        return ($data['tokens_input'] * $rate['input']) + 
               ($data['tokens_output'] * $rate['output']);
    }
}
```

#### Cost Dashboards

**Metrics to Track**:
- Total cost per day/week/month
- Cost per user tier (Free, Explorer, Pro, Enterprise)
- Cost per user (identify high-usage users)
- Average tokens per request
- Average cost per request
- Cache hit rate (lower cost when cache hit)

**Alerts**:
- Daily cost > $100 (unexpected spike)
- Single user cost > $10/day (abuse detection)
- Free tier cost > $50/day (should be $0, investigate)

### 4.3 User Activity Audit

#### Audit Log Schema

```sql
CREATE TABLE ai_audit_log (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT REFERENCES users(id),
    session_id UUID,
    event VARCHAR(50), -- 'chat', 'rate_limit_hit', 'feature_blocked', 'upgrade'
    
    -- Request details
    request_method VARCHAR(10),
    request_path VARCHAR(255),
    request_body JSONB,
    
    -- Response details
    response_status INT,
    response_body JSONB,
    
    -- Security
    ip_address INET,
    user_agent TEXT,
    
    -- Metadata
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE INDEX idx_ai_audit_user_created ON ai_audit_log(user_id, created_at);
CREATE INDEX idx_ai_audit_event ON ai_audit_log(event);
```

#### Audit Events

**Security Events** (always logged):
- `auth_failed`: Invalid token
- `rate_limit_exceeded`: User hit rate limit
- `permission_denied`: User tried to access blocked feature
- `suspicious_activity`: Unusual patterns (e.g., 100 requests in 1 minute)

**Business Events** (logged for analytics):
- `chat_request`: User sent message
- `feature_used`: User used specific feature (weather, comparison, etc.)
- `upgrade_prompted`: User shown upgrade message
- `session_created`: New session started
- `session_expired`: Session timed out

**Compliance Events** (Enterprise tier only):
- `data_exported`: User exported data
- `api_key_created`: User generated API key
- `api_key_revoked`: User revoked API key

#### Audit Log Retention

- **Free/Explorer/Pro**: 30 days
- **Enterprise**: 90 days (configurable up to 1 year)
- **Compliance**: GDPR-ready (anonymize on user deletion)

---

## 5. Kill Switches

### 5.1 Per-User Kill Switch

**Use Case**: Disable AI for specific user (abuse, non-payment, security)

**Implementation**:

```php
// Add to users table
ALTER TABLE users ADD COLUMN ai_enabled BOOLEAN DEFAULT TRUE;

// Middleware check
class CheckAIEnabled
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        
        if ($user && !$user->ai_enabled) {
            return response()->json([
                'error' => 'AI access disabled for your account. Contact support.',
                'code' => 'AI_DISABLED',
            ], 403);
        }
        
        return $next($request);
    }
}
```

**Admin Panel**:
- Toggle switch in user edit page
- Reason field (required): "Abuse", "Non-payment", "Security"
- Notification sent to user (email)

### 5.2 Per-Tenant Kill Switch (Enterprise)

**Use Case**: Disable AI for entire enterprise customer (contract ended, non-payment)

**Implementation**:

```php
// Add to tenants table (if multi-tenancy)
CREATE TABLE tenants (
    id BIGSERIAL PRIMARY KEY,
    name VARCHAR(255),
    ai_enabled BOOLEAN DEFAULT TRUE,
    ai_disabled_reason TEXT,
    ai_disabled_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT NOW()
);

// Middleware check
class CheckTenantAIEnabled
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        $tenant = $user->tenant; // Assuming user belongs to tenant
        
        if ($tenant && !$tenant->ai_enabled) {
            return response()->json([
                'error' => 'AI access disabled for your organization. Contact your administrator.',
                'code' => 'TENANT_AI_DISABLED',
                'reason' => $tenant->ai_disabled_reason,
            ], 403);
        }
        
        return $next($request);
    }
}
```

### 5.3 Global Kill Switch

**Use Case**: Emergency shutdown (cost spike, security breach, LLM API down)

**Implementation**:

#### Option 1: Environment Variable (Requires Deployment)

```php
// .env
AI_ENABLED=true

// Middleware
if (!config('ai.enabled')) {
    return response()->json([
        'error' => 'AI service is temporarily unavailable. Please try again later.',
        'code' => 'AI_GLOBALLY_DISABLED',
    ], 503);
}
```

#### Option 2: Redis Flag (Instant, No Deployment)

```php
// Enable/disable via Redis
Redis::set('ai:global_enabled', 'true');

// Middleware
if (Redis::get('ai:global_enabled') !== 'true') {
    return response()->json([
        'error' => 'AI service is temporarily unavailable. Please try again later.',
        'code' => 'AI_GLOBALLY_DISABLED',
        'retry_after': 300, // 5 minutes
    ], 503);
}
```

**Admin Command**:
```bash
# Disable AI globally
php artisan ai:disable --reason="Cost spike detected"

# Enable AI globally
php artisan ai:enable
```

#### Option 3: Feature Flag (Gradual Rollout)

```php
// Using LaunchDarkly or similar
if (!FeatureFlag::isEnabled('ai_service', $user)) {
    return response()->json([
        'error' => 'AI service is not available for your account.',
        'code' => 'AI_NOT_ENABLED',
    ], 403);
}
```

**Use Cases**:
- Gradual rollout (10% → 50% → 100% of users)
- A/B testing (50% get AI, 50% don't)
- Canary deployment (new AI version for 5% of users)

### 5.4 Kill Switch Dashboard

**Admin Panel** (`/admin/ai/kill-switches`):

```
┌─────────────────────────────────────────────────────────┐
│ AI Service Kill Switches                                │
├─────────────────────────────────────────────────────────┤
│                                                         │
│ Global Kill Switch:  [●] Enabled  [ ] Disabled          │
│                                                         │
│ Per-Tier Kill Switches:                                │
│   Free:       [●] Enabled  [ ] Disabled                 │
│   Explorer:   [●] Enabled  [ ] Disabled                 │
│   Pro:        [●] Enabled  [ ] Disabled                 │
│   Enterprise: [●] Enabled  [ ] Disabled                 │
│                                                         │
│ Emergency Actions:                                      │
│   [ Disable All AI ]  [ Enable All AI ]                 │
│                                                         │
│ Recent Kill Switch Events:                              │
│   2025-12-22 14:30 - Global disabled (Cost spike)       │
│   2025-12-22 14:45 - Global enabled                     │
│   2025-12-21 10:00 - User #123 disabled (Abuse)         │
└─────────────────────────────────────────────────────────┘
```

### 5.5 Automatic Kill Switches (Circuit Breakers)

**Trigger Conditions**:

1. **Cost Spike**: Daily cost > $500 (threshold configurable)
   - Action: Disable Free tier, alert admin
   
2. **Error Rate**: > 50% of requests fail for 5 minutes
   - Action: Disable AI globally, alert DevOps
   
3. **LLM API Down**: 5 consecutive failures
   - Action: Enable fallback mode (cached responses only)
   
4. **Rate Limit Abuse**: Single user > 1,000 requests/hour
   - Action: Disable AI for that user, alert admin

**Implementation**:

```php
class CircuitBreakerService
{
    public function check(): void
    {
        // Check cost spike
        $dailyCost = $this->getDailyCost();
        if ($dailyCost > 500) {
            $this->disableFreeTier();
            $this->alertAdmin("Cost spike: ${dailyCost}");
        }
        
        // Check error rate
        $errorRate = $this->getErrorRate(minutes: 5);
        if ($errorRate > 0.5) {
            $this->disableGlobally();
            $this->alertDevOps("Error rate: {$errorRate}");
        }
        
        // Check LLM API health
        if ($this->getLLMFailureCount() >= 5) {
            $this->enableFallbackMode();
            $this->alertDevOps("LLM API down");
        }
    }
}
```

**Cron Job**: Run every 1 minute
```bash
* * * * * php artisan ai:circuit-breaker:check
```

---

## Integration Checklist

### Phase 1: Infrastructure Setup
- [ ] Deploy AI service (separate Laravel app)
- [ ] Configure Redis for sessions and rate limiting
- [ ] Set up internal API authentication (shared secret)
- [ ] Configure LLM API credentials (OpenAI/Anthropic)
- [ ] Set up logging (CloudWatch/Datadog)

### Phase 2: Database Changes
- [ ] Add `ai_tier`, `ai_queries_used`, `ai_quota_reset_at` to `users` table
- [ ] Create `ai_usage` table for cost tracking
- [ ] Create `ai_audit_log` table for audit trail
- [ ] Create `tenants` table (if multi-tenancy)

### Phase 3: Permission System
- [ ] Implement `PermissionService` (rate limits, feature gates, data scope)
- [ ] Add middleware for permission checks
- [ ] Add tier-based filtering to context assembly
- [ ] Test permission enforcement (unit tests)

### Phase 4: Observability
- [ ] Set up structured logging (JSON format)
- [ ] Implement cost tracking service
- [ ] Create audit log service
- [ ] Set up dashboards (cost, usage, errors)
- [ ] Configure alerts (cost spike, error rate, API down)

### Phase 5: Kill Switches
- [ ] Implement per-user kill switch (database flag)
- [ ] Implement per-tenant kill switch (if multi-tenancy)
- [ ] Implement global kill switch (Redis flag)
- [ ] Create admin panel for kill switches
- [ ] Implement circuit breakers (automatic kill switches)

### Phase 6: Testing
- [ ] Load testing (1,000 concurrent users)
- [ ] Permission testing (all tiers, all features)
- [ ] Kill switch testing (disable/enable scenarios)
- [ ] Cost tracking accuracy (compare with LLM API bills)
- [ ] Audit log completeness (all events logged)

---

**End of Integration Document**

This integration design prioritizes **safety** (kill switches, circuit breakers), **debuggability** (structured logs, audit trail), and **cost control** (tracking, alerts, automatic shutdowns). The AI service is isolated from the main API to prevent failures from cascading.

