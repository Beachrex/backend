# Beachrex AI Agent - API Contracts

**Version**: 1.0
**Last Updated**: 2025-12-23
**Status**: CANONICAL
**Purpose**: Defines AI interaction API endpoints, request/response schemas, authentication

---

## Document Authority

This document defines the **contract** between frontend and AI backend. These contracts are:
- ✅ **Provider-agnostic**: No OpenAI/Anthropic-specific fields
- ✅ **Version-stable**: Breaking changes require new API version
- ✅ **Language-agnostic**: Support all 19 locales

---

## OpenAPI as Canonical Contract

**This document is mirrored in the OpenAPI specification.**

### Requirements

**All endpoints defined here MUST have equivalent OpenAPI annotations**:
- ✅ Schema names must be reusable and stable (frontend depends on them)
- ✅ Error responses must use shared schemas (e.g. oneOf / discriminated unions)
- ✅ All enums must be explicitly defined in OpenAPI
- ✅ Security schemes must be documented (optional authentication)
- ✅ Examples must be provided for success and error cases

**If a discrepancy exists between this document and OpenAPI, OpenAPI is considered incomplete and must be updated.**

**OpenAPI Location**: `storage/api-docs/api-docs.json` (generated from annotations)

**Swagger UI**: `http://localhost/api/documentation`

**See**: `docs/ai/implementation/OPENAPI_COVERAGE_REPORT.md` for current coverage

---

## 1. API Endpoints

### 1.1 Chat Endpoint (Primary)

**Endpoint**: `POST /api/v1/ai/chat`

**Purpose**: Send message to AI agent, get response

**Authentication**: Optional (anonymous allowed for free tier, required for paid features)

**Rate Limiting**:
- Anonymous (no auth): 100 requests/hour per IP
- Authenticated: 500 requests/hour per user

**Request Headers**:
```http
POST /api/v1/ai/chat HTTP/1.1
Host: api.beachrex.com
Content-Type: application/json
Accept: application/json
Accept-Language: en                          # Optional, default: en
Authorization: Bearer {token}                # Optional for free tier
X-Session-ID: {session_id}                   # Optional, generated if not provided
```

**Request Body**:
```json
{
  "message": "Show me family-friendly beaches in Croatia with parking",
  "session_id": "abc123-def456-ghi789",      // Optional, auto-generated if missing
  "context": {                                // Optional, additional context
    "location": {                             // User's current location (optional)
      "latitude": 45.8150,
      "longitude": 15.9819
    },
    "preferences": {                          // Explicit preferences (optional)
      "max_distance_km": 50,
      "surface_types": ["sand", "pebble"]
    }
  }
}
```

**Response (Success - 200 OK)**:
```json
{
  "session_id": "abc123-def456-ghi789",
  "message": "I found 15 family-friendly beaches in Croatia with parking. Here are the top 5 matches:",
  "intent": "beach_search",                   // Detected intent
  "beaches": [                                 // Beach results (if applicable)
    {
      "id": 123,
      "name": "Bačvice Beach",
      "slug": "bacvice-beach",
      "country_code": "HR",
      "city": "Split",
      "surface": "sand",
      "facilities": ["parking", "playground", "showers"],
      "distance_km": 12.5,                    // If user location provided
      "match_score": 0.95                     // Relevance score (0-1)
    }
    // ... more beaches
  ],
  "filters_applied": {                        // Filters used in search
    "country": "HR",
    "facilities": ["parking"],
    "family_friendly": true
  },
  "suggestions": [                            // Follow-up suggestions
    "Show me beaches with playgrounds",
    "Which one has the best weather today?",
    "Compare the top 3 beaches"
  ],
  "metadata": {
    "tokens_used": 1250,                      // LLM tokens consumed
    "model": "gpt-3.5-turbo",                 // Model used
    "response_time_ms": 850,                  // Response time
    "cached": false                           // Was response cached?
  }
}
```

**Response (Payment Required - 402)**:
```json
{
  "error": "payment_required",
  "message": "Multi-day itineraries require payment",
  "product": {
    "type": "itinerary",
    "duration_days": 7,
    "price": 39,
    "currency": "USD"
  },
  "purchase_url": "https://beachrex.com/purchase/itinerary/xyz789"
}
```

**Response (Rate Limit Exceeded - 429)**:
```json
{
  "error": "rate_limit_exceeded",
  "message": "You have exceeded the rate limit for free tier",
  "limit": 100,
  "reset_at": "2025-12-22T15:00:00Z",         // When limit resets
  "upgrade_url": "https://beachrex.com/pricing"
}
```

**Response (Token Budget Exceeded - 413)**:
```json
{
  "error": "token_budget_exceeded",
  "message": "Your query is too complex for the free tier",
  "estimated_tokens": 5200,
  "max_tokens": 4000,
  "suggestion": "Simplify your query or upgrade to paid tier"
}
```

**Response (Service Unavailable - 503)**:
```json
{
  "error": "service_unavailable",
  "message": "AI service is temporarily unavailable due to high costs",
  "retry_after": 3600,                        // Seconds until retry
  "fallback_url": "https://beachrex.com/search"  // Fallback to regular search
}
```

---

### 1.2 Session Management Endpoints

#### Get Session Info

**Endpoint**: `GET /api/v1/ai/session/{session_id}`

**Purpose**: Get current session metadata (message count, TTL, etc.)

**Authentication**: Optional (but session must belong to user if authenticated)

**Response (200 OK)**:
```json
{
  "session_id": "abc123-def456-ghi789",
  "created_at": "2025-12-22T14:30:00Z",
  "expires_at": "2025-12-22T15:00:00Z",       // 30 min TTL
  "message_count": 5,
  "max_messages": 10,
  "user_id": null,                            // null for anonymous
  "preferences": {                            // Extracted from conversation
    "country": "HR",
    "family_friendly": true
  }
}
```

---

#### Clear Session

**Endpoint**: `DELETE /api/v1/ai/session/{session_id}`

**Purpose**: Clear session history (forget conversation)

**Authentication**: Optional (but session must belong to user if authenticated)

**Response (204 No Content)**:
```
(empty body)
```

---

## 2. Request/Response Schemas

### 2.1 Chat Request Schema

```typescript
interface ChatRequest {
  message: string;                            // Required, max 500 chars
  session_id?: string;                        // Optional, auto-generated if missing
  context?: {
    location?: {
      latitude: number;                       // -90 to 90
      longitude: number;                      // -180 to 180
    };
    preferences?: {
      max_distance_km?: number;               // Max distance from location
      surface_types?: string[];               // ["sand", "pebble", "rock", "concrete"]
      facilities?: string[];                  // ["parking", "showers", "playground", ...]
    };
  };
}
```

---

### 2.2 Chat Response Schema

```typescript
interface ChatResponse {
  session_id: string;                         // Session identifier
  message: string;                            // AI response message
  intent: IntentType;                         // Detected intent
  beaches?: Beach[];                          // Beach results (if applicable)
  filters_applied?: FilterSet;                // Filters used in search
  suggestions?: string[];                     // Follow-up suggestions
  metadata: ResponseMetadata;                 // Response metadata
}

type IntentType = 
  | "beach_search"                            // Search for beaches
  | "beach_comparison"                        // Compare beaches
  | "weather_query"                           // Weather information
  | "itinerary_request"                       // Multi-day itinerary (PAID)
  | "accessibility_query"                     // Accessibility information
  | "general_question"                        // General beach knowledge
  | "unknown";                                // Could not determine intent

interface Beach {
  id: number;
  name: string;
  slug: string;
  country_code: string;
  city: string;
  surface: string;
  facilities: string[];
  distance_km?: number;                       // NULLABLE: Only if user location provided in request context
  match_score: number;                        // 0-1, relevance to query
}

/**
 * DISTANCE HANDLING RULES:
 *
 * 1. distance_km is ONLY computed when user provides location (latitude/longitude) in request context
 * 2. If no location provided:
 *    - distance_km MUST be null (not returned in response)
 *    - AI message MUST NOT mention distance
 *    - AI should either:
 *      a) Ask user to share location for personalized recommendations
 *      b) Provide general guidance about beaches in requested area
 *      c) Suggest well-known beaches if user asks for "best" or "popular"
 *
 * 3. Country-level queries (e.g., "Show me beaches in Croatia") without location:
 *    - Return beaches based on other criteria (popularity, facilities, etc.)
 *    - Do NOT compute distance
 *    - Do NOT sort by distance
 *
 * Example:
 * Request: { "message": "Show me beaches in Croatia" }  // No context.location
 * Response: { "beaches": [{ "id": 123, "name": "Bačvice", "distance_km": null, ... }] }
 * AI Message: "Here are some well-known beaches in Croatia..." (NO distance mentioned)
 */

interface FilterSet {
  country?: string;
  region?: string;
  city?: string;
  surface?: string[];
  facilities?: string[];
  family_friendly?: boolean;
  wheelchair_accessible?: boolean;
  // ... other filters
}

interface ResponseMetadata {
  tokens_used: number;                        // LLM tokens consumed
  model: string;                              // Model used (e.g., "gpt-3.5-turbo")
  response_time_ms: number;                   // Response time in milliseconds
  cached: boolean;                            // Was response cached?
}
```

---

### 2.3 Error Response Schema

```typescript
interface ErrorResponse {
  error: ErrorCode;                           // Machine-readable error code
  message: string;                            // Human-readable error message
  details?: any;                              // Additional error details
}

type ErrorCode =
  | "payment_required"                        // 402: Paid feature requested
  | "rate_limit_exceeded"                     // 429: Too many requests
  | "token_budget_exceeded"                   // 413: Query too complex
  | "service_unavailable"                     // 503: AI temporarily disabled
  | "invalid_request"                         // 400: Malformed request
  | "unauthorized"                            // 401: Auth required
  | "forbidden"                               // 403: Not allowed
  | "session_expired"                         // 410: Session no longer exists
  | "internal_error";                         // 500: Server error
```

---

## 3. Authentication & Authorization

### 3.1 Authentication Methods

#### Anonymous (Free Tier)
- **No authentication required**
- Rate limited by IP (100 requests/hour)
- Session identified by `X-Session-ID` header (client-generated UUID)
- Cannot access paid features

#### Authenticated (Paid Tier)
- **Bearer token required** (Laravel Sanctum)
- Rate limited by user (500 requests/hour)
- Session tied to user account
- Can access paid features (if purchased)

**Example**:
```http
Authorization: Bearer 1|abc123def456ghi789...
```

---

### 3.2 Payment Enforcement Points

#### Free Tier (No Payment Required)
- ✅ Beach search (unlimited queries, rate limited)
- ✅ Beach comparison (up to 3 beaches)
- ✅ Current weather
- ✅ General questions
- ✅ Single-day suggestions

#### Paid Tier (Payment Required)
- ❌ Multi-day itineraries (2+ days) → 402 Payment Required
- ❌ Verified accessibility reports → 402 Payment Required
- ❌ Expert activity guides → 402 Payment Required
- ❌ Multi-day weather forecasts → 402 Payment Required

**Enforcement**:
```php
// In ChatController::chat()
if ($intent === 'itinerary_request' && $days >= 2) {
    if (!$user || !$user->hasPurchased('itinerary', $tripHash)) {
        return response()->json([
            'error' => 'payment_required',
            'message' => 'Multi-day itineraries require payment',
            'product' => [
                'type' => 'itinerary',
                'duration_days' => $days,
                'price' => $this->calculatePrice($days),
                'currency' => 'USD'
            ],
            'purchase_url' => route('purchase.itinerary', $tripHash)
        ], 402);
    }
}
```

---

## 4. Locale Support

### 4.1 Locale Detection

**Priority Order**:
1. `Accept-Language` header (e.g., `Accept-Language: hr`)
2. User account locale (if authenticated)
3. Default: `en`

**Supported Locales**:
- `en` - English
- `hr` - Croatian
- `sl` - Slovenian
- `de` - German
- `it` - Italian
- `fr` - French
- `es` - Spanish
- ... (14 total)

---

### 4.2 Locale-Specific Responses

**System Prompt**: Loaded from `storage/prompts/system/{locale}.txt`

**Response Language**: AI responds in same language as user query (detected by LLM)

**Beach Names**: Returned in requested locale (from `beach_translations` table)

**Example**:
```http
Accept-Language: hr
```

```json
{
  "message": "Pronašao sam 15 obiteljskih plaža u Hrvatskoj s parkingom...",
  "beaches": [
    {
      "name": "Plaža Bačvice",  // Croatian translation
      "city": "Split"
    }
  ]
}
```

---

## 5. Caching Strategy

### 5.1 Cacheable Responses

**Cache Key**: `ai:response:{hash(message + filters + locale)}`

**TTL**: 1 hour (responses are relatively stable)

**Conditions**:
- ✅ Intent is `beach_search` (deterministic)
- ✅ No user-specific context (location, preferences)
- ✅ No session history (first message in session)
- ❌ Intent is `itinerary_request` (personalized, not cacheable)

**Example**:
```php
$cacheKey = "ai:response:" . md5($message . json_encode($filters) . $locale);
$cached = Redis::get($cacheKey);

if ($cached) {
    return json_decode($cached, true);
}

// ... call LLM ...

Redis::setex($cacheKey, 3600, json_encode($response));
```

---

**End of API Contracts**

These contracts are stable and provider-agnostic. Implementation details may change, but API contracts remain backward-compatible.

