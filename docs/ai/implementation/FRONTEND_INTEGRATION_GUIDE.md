# Frontend Integration Guide - AI Agent API

**Date**: 2025-12-27  
**Status**: ✅ READY FOR INTEGRATION  
**Purpose**: Instructions for frontend team to integrate AI Agent API

---

## 1. OpenAPI Specification Access

### 1.1. Public Endpoint (Recommended)

**URL**: `http://localhost/openapi.json` (development)  
**URL**: `https://api.beachrex.com/openapi.json` (production)

**Authentication**: None required (public endpoint)

**CORS**: Enabled (Access-Control-Allow-Origin: *)

**Example**:
```bash
curl https://api.beachrex.com/openapi.json -o openapi.json
```

---

### 1.2. Swagger UI (Human-Readable)

**URL**: `http://localhost/api/documentation` (development)  
**URL**: `https://api.beachrex.com/api/documentation` (production)

**Authentication**: None required

**Use Case**: Manual testing, API exploration, documentation

---

### 1.3. Local File Export

**Generate latest spec**:
```bash
# From Laravel API directory
./vendor/bin/sail artisan l5-swagger:generate

# Spec is generated at:
# storage/api-docs/api-docs.json
```

**Copy to frontend project**:
```bash
# From Laravel API directory
cp storage/api-docs/api-docs.json ../Frontend/openapi.json
```

---

## 2. TypeScript Client Generation

### 2.1. Using openapi-typescript (Recommended)

**Install**:
```bash
npm install --save-dev openapi-typescript
```

**Generate types**:
```bash
# From remote URL (production)
npx openapi-typescript https://api.beachrex.com/openapi.json --output src/types/api.ts

# From local file (development)
npx openapi-typescript ./openapi.json --output src/types/api.ts

# From local API server (development)
npx openapi-typescript http://localhost/openapi.json --output src/types/api.ts
```

**Output**: `src/types/api.ts` (TypeScript interfaces for all schemas)

---

### 2.2. Using openapi-generator-cli (Alternative)

**Install**:
```bash
npm install --save-dev @openapitools/openapi-generator-cli
```

**Generate client**:
```bash
npx openapi-generator-cli generate \
  -i https://api.beachrex.com/openapi.json \
  -g typescript-fetch \
  -o src/api/generated
```

**Output**: Full TypeScript client with fetch wrappers

---

### 2.3. Verify AI Schemas

**Check AI schemas are present**:
```bash
curl http://localhost/openapi.json | jq '.components.schemas | keys | map(select(startswith("Ai")))'
```

**Expected output**:
```json
[
  "AiBeachRecommendation",
  "AiChatRequest",
  "AiChatResponse",
  "AiErrorResponse",
  "AiPaymentRequiredError",
  "AiRateLimitError",
  "AiResponseMetadata",
  "AiServiceUnavailableError",
  "AiTokenBudgetError"
]
```

---

## 3. AI Chat Endpoint Integration

### 3.1. Endpoint Details

**URL**: `POST /api/v1/ai/chat`

**Authentication**: Optional (supports both anonymous and authenticated)
- Anonymous: No `Authorization` header (free tier, IP-based rate limiting)
- Authenticated: `Authorization: Bearer {token}` (paid tier, user-based rate limiting)

**Content-Type**: `application/json`

**Accept-Language**: Optional (default: `en`, supports 19 languages)

---

### 3.2. Request Schema (TypeScript)

```typescript
interface AiChatRequest {
  message: string; // Required, max 500 chars
  session_id?: string; // Optional, UUID (auto-generated if not provided)
  context?: {
    location?: {
      latitude: number; // -90 to 90
      longitude: number; // -180 to 180
    };
    preferences?: {
      max_distance_km?: number; // 1-500
      surface_types?: ('sand' | 'pebble' | 'rock' | 'concrete')[];
      facilities?: string[];
    };
  };
}
```

---

### 3.3. Response Schema (TypeScript)

```typescript
interface AiChatResponse {
  session_id: string; // UUID
  message: string; // AI response message
  intent: 'beach_search' | 'beach_comparison' | 'weather_query' | 
          'itinerary_request' | 'accessibility_query' | 'general_question' | 'unknown';
  beaches?: AiBeachRecommendation[]; // Optional, only if beaches found
  filters_applied?: Record<string, any>; // Optional, filters used
  suggestions?: string[]; // Optional, follow-up suggestions
  metadata: AiResponseMetadata; // Always present
}

interface AiBeachRecommendation {
  id: number;
  name: string;
  slug: string;
  country_code: string;
  city?: string;
  surface: string;
  facilities: string[];
  distance_km?: number; // Only if user location provided
  match_score: number; // 0-1
}

interface AiResponseMetadata {
  tokens_used: number;
  model: string;
  response_time_ms: number;
  cached: boolean;
}
```

---

### 3.4. Error Response Schemas (TypeScript)

```typescript
// Generic error (400, 500)
interface AiErrorResponse {
  error: 'invalid_request' | 'unauthorized' | 'forbidden' | 
         'session_expired' | 'internal_error';
  message: string;
}

// Payment required (402)
interface AiPaymentRequiredError {
  error: 'payment_required';
  message: string;
  product: {
    type: string;
    duration_days: number;
    price: number;
    currency: string;
  };
  purchase_url: string;
}

// Rate limit exceeded (429)
interface AiRateLimitError {
  error: 'rate_limit_exceeded';
  message: string;
  limit: number;
  reset_at: string; // ISO 8601
  upgrade_url: string;
}

// Token budget exceeded (413)
interface AiTokenBudgetError {
  error: 'token_budget_exceeded';
  message: string;
  estimated_tokens: number;
  max_tokens: number;
  suggestion: string;
}

// Service unavailable (503)
interface AiServiceUnavailableError {
  error: 'service_unavailable';
  message: string;
  retry_after: number; // seconds
  fallback_url: string;
}
```

---

### 3.5. Example Request (Anonymous)

```typescript
const response = await fetch('http://localhost/api/v1/ai/chat', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
    'Accept-Language': 'en',
  },
  body: JSON.stringify({
    message: 'Show me family-friendly beaches in Croatia with parking',
    context: {
      location: {
        latitude: 45.8150,
        longitude: 15.9819
      },
      preferences: {
        max_distance_km: 50,
        surface_types: ['sand', 'pebble'],
        facilities: ['parking', 'playground']
      }
    }
  })
});

const data: AiChatResponse = await response.json();
```

---

### 3.6. Example Request (Authenticated)

```typescript
const response = await fetch('http://localhost/api/v1/ai/chat', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
    'Accept-Language': 'en',
    'Authorization': `Bearer ${userToken}`,
  },
  body: JSON.stringify({
    message: 'Create a 7-day itinerary in Croatia',
    session_id: 'abc123-def456-ghi789', // Reuse session
  })
});

if (response.status === 402) {
  const error: AiPaymentRequiredError = await response.json();
  // Redirect to payment: error.purchase_url
}
```

---

### 3.7. Error Handling

```typescript
async function sendAiMessage(message: string, token?: string): Promise<AiChatResponse> {
  const response = await fetch('http://localhost/api/v1/ai/chat', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
      ...(token && { 'Authorization': `Bearer ${token}` }),
    },
    body: JSON.stringify({ message }),
  });

  if (!response.ok) {
    switch (response.status) {
      case 400:
        const badRequest: AiErrorResponse = await response.json();
        throw new Error(`Invalid request: ${badRequest.message}`);
      
      case 402:
        const paymentRequired: AiPaymentRequiredError = await response.json();
        // Redirect to payment page
        window.location.href = paymentRequired.purchase_url;
        throw new Error('Payment required');
      
      case 413:
        const tokenBudget: AiTokenBudgetError = await response.json();
        throw new Error(`Query too complex: ${tokenBudget.suggestion}`);
      
      case 429:
        const rateLimit: AiRateLimitError = await response.json();
        throw new Error(`Rate limit exceeded. Try again at ${rateLimit.reset_at}`);
      
      case 503:
        const unavailable: AiServiceUnavailableError = await response.json();
        // Redirect to fallback search
        window.location.href = unavailable.fallback_url;
        throw new Error('AI service temporarily unavailable');
      
      default:
        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
    }
  }

  return response.json();
}
```

---

## 4. Session Management

### 4.1. Session ID Handling

**First message** (no session_id):
```typescript
const response = await sendAiMessage('Show me beaches in Croatia');
const sessionId = response.session_id; // Save this for follow-up messages
```

**Follow-up messages** (with session_id):
```typescript
const response = await sendAiMessage('Which one has the best weather?', sessionId);
// AI has context from previous messages
```

**Session expiry**: 30 minutes of inactivity

---

### 4.2. Anonymous vs Authenticated Sessions

**Anonymous**:
- Session identified by client-generated UUID
- No server-side persistence (Redis only, 30-minute TTL)
- Rate limited by IP (100 requests/hour)

**Authenticated**:
- Session tied to user account
- Persisted in database (90 days retention)
- Rate limited by user (500 requests/hour)

---

## 5. Rate Limiting

### 5.1. Rate Limit Headers (Future)

**Note**: Currently not exposed, but planned for future:
- `X-RateLimit-Limit`: Total requests allowed
- `X-RateLimit-Remaining`: Requests remaining
- `X-RateLimit-Reset`: When limit resets (Unix timestamp)

---

### 5.2. Handling 429 Errors

```typescript
if (response.status === 429) {
  const error: AiRateLimitError = await response.json();
  
  // Show upgrade prompt
  showUpgradeModal({
    message: error.message,
    limit: error.limit,
    resetAt: new Date(error.reset_at),
    upgradeUrl: error.upgrade_url,
  });
}
```

---

## 6. Multi-Language Support

### 6.1. Supported Languages

**19 languages**: en, hr, sl, de, it, fr, es, pt, nl, pl, cs, sk, hu, ro

**Set language**:
```typescript
headers: {
  'Accept-Language': 'hr', // Croatian
}
```

**AI response**: Will be in the requested language

---

## 7. Testing

### 7.1. Test Endpoints

**Swagger UI**: `http://localhost/api/documentation`
- Click "Try it out" on `/api/v1/ai/chat`
- Test with different messages and contexts

**curl**:
```bash
curl -X POST http://localhost/api/v1/ai/chat \
  -H "Content-Type: application/json" \
  -H "Accept-Language: en" \
  -d '{"message": "Show me beaches in Croatia"}' | jq
```

---

## 8. Summary

**OpenAPI Spec**: `http://localhost/openapi.json` (public, no auth)

**AI Endpoint**: `POST /api/v1/ai/chat` (optional auth)

**TypeScript Generation**: `npx openapi-typescript http://localhost/openapi.json --output src/types/api.ts`

**Schemas**: 9 AI schemas (AiChatRequest, AiChatResponse, 5 error types)

**Response Codes**: 200, 400, 402, 413, 429, 500, 503

**Authentication**: Optional (anonymous = free tier, authenticated = paid tier)

**Rate Limits**: 100/hour (anonymous), 500/hour (authenticated)

**Session TTL**: 30 minutes

**Languages**: 14 supported (set via Accept-Language header)

**Ready for integration**: ✅

