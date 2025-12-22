# Beachrex AI Agent - MVP Integration Architecture

**Version**: 1.0  
**Last Updated**: 2025-12-22  
**Status**: CANONICAL for MVP  
**Purpose**: Defines minimal AI architecture for MVP launch

---

## Document Authority

This document supersedes `integration.md` for MVP phase only. The microservice architecture in `integration.md` remains the target for Phase 2.

**MVP Decision**: AI agent is part of existing Laravel API (same app, new routes)  
**Phase 2 Migration**: Extract to dedicated microservice once we have usage/cost signals

---

## 1. Minimal AI Architecture for MVP

### 1.1 Component Overview

```
┌─────────────────────────────────────────────────────────────────┐
│                    Frontend (Next.js 14)                         │
│                   https://beachrex.com                           │
│                                                                  │
│  Components:                                                     │
│  - ChatInterface (AI chat widget)                                │
│  - BeachSearch (existing search, enhanced with AI)              │
└────────────────────────────┬────────────────────────────────────┘
                             │
                             │ HTTPS (REST API)
                             ▼
┌─────────────────────────────────────────────────────────────────┐
│              Existing Laravel 11 API (Port 8000)                 │
│                  https://api.beachrex.com                        │
│                                                                  │
│  ┌────────────────────────────────────────────────────────────┐ │
│  │  NEW: AI Routes (/api/v1/ai/*)                             │ │
│  │                                                            │ │
│  │  Controllers:                                              │ │
│  │  - AI/ChatController         (POST /api/v1/ai/chat)       │ │
│  │  - AI/SessionController      (GET/DELETE /api/v1/ai/session)│ │
│  │                                                            │ │
│  │  Services:                                                 │ │
│  │  - AI/LLMService             (provider abstraction)       │ │
│  │  - AI/ContextAssemblyService (inject beach data)          │ │
│  │  - AI/PromptService          (system prompts)             │ │
│  │  - AI/CostTrackingService    (monitor LLM costs)          │ │
│  │                                                            │ │
│  │  Middleware:                                               │ │
│  │  - AI/RateLimitMiddleware    (100/hour free, 500/hour paid)│ │
│  │  - AI/TokenBudgetMiddleware  (4K tokens free, 16K paid)   │ │
│  │  - AI/CircuitBreakerMiddleware ($500/day → disable free)  │ │
│  └────────────────────────────────────────────────────────────┘ │
│                                                                  │
│  ┌────────────────────────────────────────────────────────────┐ │
│  │  EXISTING: Beach API Routes (/api/v1/beaches/*)           │ │
│  │                                                            │ │
│  │  Controllers:                                              │ │
│  │  - BeachController           (unchanged)                   │ │
│  │  - CountryController         (unchanged)                   │ │
│  │  - SearchController          (unchanged)                   │ │
│  │  - FilterController          (unchanged)                   │ │
│  │                                                            │ │
│  │  Services:                                                 │ │
│  │  - BeachFilterService        (reused by AI)               │ │
│  │  - CacheService              (reused by AI)               │ │
│  │  - WeatherService            (reused by AI)               │ │
│  └────────────────────────────────────────────────────────────┘ │
└────────────────────────────┬────────────────────────────────────┘
                             │
                             ▼
┌─────────────────────────────────────────────────────────────────┐
│                   Shared Infrastructure                          │
│                                                                  │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐          │
│  │  PostgreSQL  │  │    Redis     │  │ Meilisearch  │          │
│  │  + PostGIS   │  │              │  │              │          │
│  │              │  │  - Cache     │  │  - Search    │          │
│  │  - beaches   │  │  - Sessions  │  │  - Indexes   │          │
│  │  - users     │  │  - Rate      │  │              │          │
│  │  - purchases │  │    limits    │  │              │          │
│  └──────────────┘  └──────────────┘  └──────────────┘          │
└─────────────────────────────────────────────────────────────────┘
                             │
                             ▼
┌─────────────────────────────────────────────────────────────────┐
│                   External Services                              │
│                                                                  │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐          │
│  │   OpenAI     │  │ OpenWeather  │  │    Stripe    │          │
│  │   API        │  │     Map      │  │   Payments   │          │
│  │              │  │              │  │              │          │
│  │  - GPT-4     │  │  - Current   │  │  - Checkout  │          │
│  │  - GPT-3.5   │  │  - Forecast  │  │  - Webhooks  │          │
│  └──────────────┘  └──────────────┘  └──────────────┘          │
└─────────────────────────────────────────────────────────────────┘
```

### 1.2 Why This Architecture for MVP

**Advantages**:
- ✅ **Fastest to market**: No new infrastructure, no inter-service communication
- ✅ **Simplest**: Single codebase, single deployment, single monitoring
- ✅ **Lowest risk**: Validate AI value before investing in microservice
- ✅ **Easy migration**: Routes namespaced under `/api/v1/ai/*`, can extract later
- ✅ **Cost visibility**: Track AI costs separately (middleware logging)

**Trade-offs Accepted for MVP**:
- ⚠️ **No isolation**: AI failures could impact main API (mitigated by circuit breakers)
- ⚠️ **Coupled scaling**: Can't scale AI independently (acceptable for MVP traffic)
- ⚠️ **Shared resources**: AI and main API share same server (monitor CPU/memory)

**Migration Path to Phase 2**:
1. Extract `app/Http/Controllers/AI/*` and `app/Services/AI/*` to new Laravel app
2. Update frontend to call new AI service URL (or use API gateway routing)
3. No changes to API contracts (frontend sees no difference)

---

## 2. Component Responsibilities

### 2.1 AI Controllers (NEW)

#### `AI/ChatController`
**Responsibility**: Handle conversational AI queries

**Endpoints**:
- `POST /api/v1/ai/chat` - Send message, get AI response

**Methods**:
- `chat(Request $request): JsonResponse` - Main chat endpoint

**Dependencies**:
- `LLMService` - Call LLM provider
- `ContextAssemblyService` - Inject beach data into prompt
- `SessionService` - Manage conversation context

---

#### `AI/SessionController`
**Responsibility**: Manage AI session lifecycle

**Endpoints**:
- `GET /api/v1/ai/session` - Get current session info
- `DELETE /api/v1/ai/session/{id}` - Clear session (forget conversation)

**Methods**:
- `show(string $sessionId): JsonResponse` - Get session metadata
- `destroy(string $sessionId): JsonResponse` - Clear session from Redis

---

### 2.2 AI Services (NEW)

#### `AI/LLMService`
**Responsibility**: Provider-agnostic LLM abstraction

**Interface**:
```php
interface LLMProvider {
    public function chat(array $messages, array $options): LLMResponse;
}
```

**Implementations**:
- `OpenAIProvider` - OpenAI API integration (MVP)
- `AnthropicProvider` - Anthropic Claude (Phase 2)

**Configuration**:
- Provider selection via `config/ai.php`
- API keys via `.env` (OPENAI_API_KEY)

---

#### `AI/ContextAssemblyService`
**Responsibility**: Inject beach data into LLM context

**Methods**:
- `assembleContext(string $userQuery, ?string $sessionId): array` - Build LLM messages array
- `injectBeachData(array $beachIds): string` - Format beach data for LLM
- `injectFilters(array $filters): string` - Format available filters for LLM

**Data Sources**:
- Beach data: Via `BeachFilterService` (existing)
- Filters: Via `FilterController` (existing)
- Weather: Via `WeatherService` (existing)

---

#### `AI/PromptService`
**Responsibility**: Manage system prompts and output formats

**Methods**:
- `getSystemPrompt(string $locale): string` - Get system prompt for locale
- `getOutputFormat(string $intentType): string` - Get JSON schema for intent

**Prompt Storage**:
- System prompts: `storage/prompts/system/{locale}.txt`
- Output formats: `storage/prompts/formats/{intent}.json`

---

#### `AI/CostTrackingService`
**Responsibility**: Monitor LLM costs and enforce circuit breakers

**Methods**:
- `trackQuery(int $tokensUsed, float $cost): void` - Log query cost
- `getDailyCost(): float` - Get total cost for today
- `checkCircuitBreaker(): bool` - Check if AI should be disabled

**Circuit Breaker Logic**:
- Daily cost > $500 → Disable free tier (Redis flag: `ai:free_tier_enabled = 0`)
- Daily cost > $5,000 → Emergency shutdown (Redis flag: `ai:global_enabled = 0`)

---

### 2.3 AI Middleware (NEW)

#### `AI/RateLimitMiddleware`
**Responsibility**: Enforce query rate limits

**Limits**:
- Free tier (anonymous): 100 queries/hour per IP
- Paid tier (authenticated): 500 queries/hour per user

**Implementation**: Redis sliding window

---

#### `AI/TokenBudgetMiddleware`
**Responsibility**: Enforce token budgets per query

**Limits**:
- Free tier: 4K tokens per query
- Paid tier: 16K tokens per query

**Implementation**: Estimate tokens before LLM call, reject if over budget

---

#### `AI/CircuitBreakerMiddleware`
**Responsibility**: Disable AI if costs exceed thresholds

**Checks**:
- Check Redis flag `ai:global_enabled` (if 0, return 503 Service Unavailable)
- Check Redis flag `ai:free_tier_enabled` (if 0 and user is free tier, return 402 Payment Required)

---

## 3. Data Flow

### 3.1 Typical Chat Request Flow

```
1. User sends message
   ↓
2. Frontend: POST /api/v1/ai/chat
   {
     "message": "Show me family-friendly beaches in Croatia",
     "session_id": "abc123" (optional)
   }
   ↓
3. Middleware: RateLimitMiddleware
   - Check IP rate limit (100/hour for free)
   - If exceeded, return 429 Too Many Requests
   ↓
4. Middleware: CircuitBreakerMiddleware
   - Check if AI is globally enabled
   - If disabled, return 503 Service Unavailable
   ↓
5. Controller: ChatController::chat()
   - Validate request
   - Get or create session_id
   ↓
6. Service: ContextAssemblyService::assembleContext()
   - Load session history from Redis (last 5 messages)
   - Call BeachFilterService to get matching beaches
   - Format beach data for LLM context
   ↓
7. Middleware: TokenBudgetMiddleware
   - Estimate total tokens (system prompt + context + user message)
   - If > 4K tokens (free tier), return 413 Payload Too Large
   ↓
8. Service: LLMService::chat()
   - Call OpenAI API (GPT-3.5-turbo for free tier)
   - Get response
   ↓
9. Service: CostTrackingService::trackQuery()
   - Log tokens used and cost
   - Check if daily cost exceeds thresholds
   - Update circuit breaker flags if needed
   ↓
10. Controller: Return response
    {
      "session_id": "abc123",
      "message": "I found 15 family-friendly beaches...",
      "beaches": [...],
      "metadata": {
        "tokens_used": 1250,
        "model": "gpt-3.5-turbo"
      }
    }
```

---

## 4. File Structure

```
app/
├── Http/
│   ├── Controllers/
│   │   ├── AI/
│   │   │   ├── ChatController.php          (NEW)
│   │   │   └── SessionController.php       (NEW)
│   │   ├── BeachController.php             (EXISTING)
│   │   └── ...
│   └── Middleware/
│       ├── AI/
│       │   ├── RateLimitMiddleware.php     (NEW)
│       │   ├── TokenBudgetMiddleware.php   (NEW)
│       │   └── CircuitBreakerMiddleware.php (NEW)
│       └── ...
├── Services/
│   ├── AI/
│   │   ├── LLMService.php                  (NEW - interface)
│   │   ├── Providers/
│   │   │   └── OpenAIProvider.php          (NEW)
│   │   ├── ContextAssemblyService.php      (NEW)
│   │   ├── PromptService.php               (NEW)
│   │   └── CostTrackingService.php         (NEW)
│   ├── BeachFilterService.php              (EXISTING - reused)
│   ├── CacheService.php                    (EXISTING - reused)
│   └── WeatherService.php                  (EXISTING - reused)
└── ...

config/
└── ai.php                                  (NEW - AI configuration)

storage/
└── prompts/
    ├── system/
    │   ├── en.txt                          (NEW - English system prompt)
    │   ├── hr.txt                          (NEW - Croatian system prompt)
    │   └── ...
    └── formats/
        ├── beach_search.json               (NEW - Output format for beach search)
        ├── comparison.json                 (NEW - Output format for comparison)
        └── ...

routes/
└── api.php                                 (MODIFIED - add AI routes)
```

---

## 5. Configuration

### 5.1 Environment Variables (NEW)

```env
# LLM Provider
AI_PROVIDER=openai                          # openai | anthropic
OPENAI_API_KEY=sk-...                       # OpenAI API key
OPENAI_MODEL_FREE=gpt-3.5-turbo            # Model for free tier
OPENAI_MODEL_PAID=gpt-4                     # Model for paid tier

# Rate Limiting
AI_RATE_LIMIT_FREE=100                      # Queries per hour (free tier)
AI_RATE_LIMIT_PAID=500                      # Queries per hour (paid tier)

# Token Budgets
AI_TOKEN_BUDGET_FREE=4000                   # Max tokens per query (free tier)
AI_TOKEN_BUDGET_PAID=16000                  # Max tokens per query (paid tier)

# Circuit Breakers
AI_COST_THRESHOLD_FREE_DISABLE=500          # Daily cost to disable free tier ($)
AI_COST_THRESHOLD_EMERGENCY=5000            # Daily cost for emergency shutdown ($)

# Session Management
AI_SESSION_TTL=1800                         # Session TTL in seconds (30 min)
AI_SESSION_MAX_MESSAGES=10                  # Max messages per session
```

---

**End of MVP Integration Architecture**

This document defines the minimal AI architecture for MVP. Phase 2 will migrate to dedicated microservice as defined in `integration.md`.

