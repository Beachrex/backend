# Frontend Quick Start - AI Agent API

**Date**: 2025-12-27  
**For**: Frontend developers  
**Time to integrate**: ~15 minutes

---

## 1. Get OpenAPI Spec (1 minute)

### Development
```bash
curl http://localhost/openapi.json -o openapi.json
```

### Production
```bash
curl https://api.beachrex.com/openapi.json -o openapi.json
```

**No authentication required** - public endpoint

---

## 2. Generate TypeScript Types (2 minutes)

### Install
```bash
npm install --save-dev openapi-typescript
```

### Generate
```bash
npx openapi-typescript ./openapi.json --output src/types/api.ts
```

**Output**: `src/types/api.ts` with all API types

---

## 3. Use AI Chat Endpoint (5 minutes)

### Basic Example (Anonymous)

```typescript
import type { components } from './types/api';

type AiChatRequest = components['schemas']['AiChatRequest'];
type AiChatResponse = components['schemas']['AiChatResponse'];

async function askAI(message: string): Promise<AiChatResponse> {
  const response = await fetch('http://localhost/api/v1/ai/chat', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
      'Accept-Language': 'en', // or 'hr', 'sl', etc.
    },
    body: JSON.stringify({ message }),
  });

  if (!response.ok) {
    throw new Error(`HTTP ${response.status}`);
  }

  return response.json();
}

// Usage
const result = await askAI('Show me beaches in Croatia');
console.log(result.message); // AI response
console.log(result.beaches); // Beach recommendations
```

---

### With User Location

```typescript
const result = await fetch('http://localhost/api/v1/ai/chat', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  },
  body: JSON.stringify({
    message: 'Show me beaches near me',
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
  }),
});
```

---

### With Authentication (Paid Features)

```typescript
const result = await fetch('http://localhost/api/v1/ai/chat', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
    'Authorization': `Bearer ${userToken}`,
  },
  body: JSON.stringify({
    message: 'Create a 7-day itinerary in Croatia',
  }),
});

// If user requests paid feature without payment:
if (result.status === 402) {
  const error = await result.json();
  // Redirect to payment: error.purchase_url
}
```

---

## 4. Handle Errors (5 minutes)

### Error Types

```typescript
type AiErrorResponse = components['schemas']['AiErrorResponse'];
type AiPaymentRequiredError = components['schemas']['AiPaymentRequiredError'];
type AiRateLimitError = components['schemas']['AiRateLimitError'];
type AiTokenBudgetError = components['schemas']['AiTokenBudgetError'];
type AiServiceUnavailableError = components['schemas']['AiServiceUnavailableError'];
```

---

### Error Handling

```typescript
async function askAI(message: string, token?: string) {
  const response = await fetch('http://localhost/api/v1/ai/chat', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
      ...(token && { 'Authorization': `Bearer ${token}` }),
    },
    body: JSON.stringify({ message }),
  });

  // Success
  if (response.ok) {
    return response.json();
  }

  // Payment required (402)
  if (response.status === 402) {
    const error: AiPaymentRequiredError = await response.json();
    window.location.href = error.purchase_url;
    throw new Error('Payment required');
  }

  // Rate limit (429)
  if (response.status === 429) {
    const error: AiRateLimitError = await response.json();
    alert(`Rate limit exceeded. Try again at ${error.reset_at}`);
    throw new Error('Rate limit exceeded');
  }

  // Token budget (413)
  if (response.status === 413) {
    const error: AiTokenBudgetError = await response.json();
    alert(`Query too complex: ${error.suggestion}`);
    throw new Error('Token budget exceeded');
  }

  // Service unavailable (503)
  if (response.status === 503) {
    const error: AiServiceUnavailableError = await response.json();
    window.location.href = error.fallback_url;
    throw new Error('Service unavailable');
  }

  // Other errors
  throw new Error(`HTTP ${response.status}`);
}
```

---

## 5. Session Management (2 minutes)

### Multi-Turn Conversation

```typescript
// First message
const response1 = await askAI('Show me beaches in Croatia');
const sessionId = response1.session_id; // Save this

// Follow-up message (AI has context)
const response2 = await fetch('http://localhost/api/v1/ai/chat', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  },
  body: JSON.stringify({
    message: 'Which one has the best weather?',
    session_id: sessionId, // Reuse session
  }),
});
```

**Session expires**: 30 minutes of inactivity

---

## 6. Response Structure

### Success Response

```typescript
{
  session_id: "abc123-def456-ghi789",
  message: "I found 15 beaches in Croatia. Here are the top 5:",
  intent: "beach_search",
  beaches: [
    {
      id: 123,
      name: "Bačvice Beach",
      slug: "bacvice-beach",
      country_code: "HR",
      city: "Split",
      surface: "sand",
      facilities: ["parking", "playground", "showers"],
      distance_km: 12.5, // Only if user location provided
      match_score: 0.95
    },
    // ... more beaches
  ],
  filters_applied: {
    country: "HR",
    facilities: ["parking"]
  },
  suggestions: [
    "Show me beaches with playgrounds",
    "Which one has the best weather today?",
    "Compare the top 3 beaches"
  ],
  metadata: {
    tokens_used: 1250,
    model: "gpt-3.5-turbo",
    response_time_ms: 850,
    cached: false
  }
}
```

---

## 7. Intent Types

**AI detects user intent**:

- `beach_search` - User wants to find beaches
- `beach_comparison` - User wants to compare beaches
- `weather_query` - User asks about weather
- `itinerary_request` - User wants multi-day itinerary (PAID)
- `accessibility_query` - User asks about accessibility
- `general_question` - General question about beaches
- `unknown` - Intent unclear

**Use intent to customize UI**:

```typescript
const response = await askAI('Show me beaches in Croatia');

switch (response.intent) {
  case 'beach_search':
    // Show beach list
    showBeachList(response.beaches);
    break;
  
  case 'beach_comparison':
    // Show comparison table
    showComparisonTable(response.beaches);
    break;
  
  case 'itinerary_request':
    // Show payment prompt (if 402 error)
    showPaymentPrompt();
    break;
}
```

---

## 8. Multi-Language Support

**Supported languages**: en, hr, sl, de, it, fr, es, pt, nl, pl, cs, sk, hu, ro

```typescript
// Croatian
const response = await fetch('http://localhost/api/v1/ai/chat', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
    'Accept-Language': 'hr',
  },
  body: JSON.stringify({
    message: 'Pokaži mi plaže u Hrvatskoj'
  }),
});

// AI responds in Croatian
console.log(response.message); // "Pronašao sam 15 plaža u Hrvatskoj..."
```

---

## 9. Rate Limits

**Anonymous** (no auth):
- 100 requests/hour per IP
- Free tier features only

**Authenticated** (with Bearer token):
- 500 requests/hour per user
- Access to paid features (if purchased)

**Handle 429 errors**:

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

## 10. Testing

### Swagger UI (Manual Testing)

**URL**: `http://localhost/api/documentation`

1. Navigate to "AI Agent" section
2. Expand "POST /api/v1/ai/chat"
3. Click "Try it out"
4. Enter message and click "Execute"

---

### curl (Command Line)

```bash
curl -X POST http://localhost/api/v1/ai/chat \
  -H "Content-Type: application/json" \
  -H "Accept-Language: en" \
  -d '{"message": "Show me beaches in Croatia"}' | jq
```

---

## 11. Summary

**OpenAPI Spec**: `http://localhost/openapi.json` (public, no auth)

**AI Endpoint**: `POST /api/v1/ai/chat`

**TypeScript Types**: Auto-generated from OpenAPI spec

**Authentication**: Optional (anonymous = free, authenticated = paid)

**Rate Limits**: 100/hour (anonymous), 500/hour (authenticated)

**Languages**: 14 supported (set via `Accept-Language` header)

**Session TTL**: 30 minutes

**Error Codes**: 200, 400, 402, 413, 429, 500, 503

**Integration Time**: ~15 minutes

---

## 12. Full Example (React Hook)

```typescript
import { useState } from 'react';
import type { components } from './types/api';

type AiChatResponse = components['schemas']['AiChatResponse'];

export function useAiChat() {
  const [loading, setLoading] = useState(false);
  const [sessionId, setSessionId] = useState<string | null>(null);

  async function sendMessage(message: string, token?: string): Promise<AiChatResponse> {
    setLoading(true);
    
    try {
      const response = await fetch('http://localhost/api/v1/ai/chat', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'Accept-Language': 'en',
          ...(token && { 'Authorization': `Bearer ${token}` }),
        },
        body: JSON.stringify({
          message,
          ...(sessionId && { session_id: sessionId }),
        }),
      });

      if (!response.ok) {
        if (response.status === 402) {
          const error = await response.json();
          window.location.href = error.purchase_url;
        }
        throw new Error(`HTTP ${response.status}`);
      }

      const data: AiChatResponse = await response.json();
      setSessionId(data.session_id);
      return data;
    } finally {
      setLoading(false);
    }
  }

  return { sendMessage, loading, sessionId };
}

// Usage in component
function ChatComponent() {
  const { sendMessage, loading } = useAiChat();

  async function handleSubmit(message: string) {
    const response = await sendMessage(message);
    console.log(response.message);
    console.log(response.beaches);
  }

  return (
    <div>
      {/* Your chat UI */}
    </div>
  );
}
```

---

**Ready to integrate! 🚀**

**Questions?** See: `docs/ai/implementation/FRONTEND_INTEGRATION_GUIDE.md` (full guide)

