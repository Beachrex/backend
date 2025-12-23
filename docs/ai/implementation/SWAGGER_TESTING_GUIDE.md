# Swagger/OpenAPI Testing Guide - AI Endpoints

**Date**: 2025-12-23  
**Purpose**: Quick reference for testing AI endpoint documentation

---

## 1. Generate Swagger Documentation

```bash
./vendor/bin/sail artisan l5-swagger:generate
```

**Expected Output**:
```
Regenerating docs default
```

**Generated File**: `storage/api-docs/api-docs.json`

---

## 2. Access Swagger UI

**Local Development**:
```
http://localhost/api/documentation
```

**Development Server**:
```
https://dev-api.beachrex.com/api/documentation
```

---

## 3. Verify AI Endpoint in Swagger UI

### 3.1. Navigate to AI Agent Section

1. Open Swagger UI
2. Scroll to **"AI Agent"** tag
3. Expand **"POST /api/v1/ai/chat"**

### 3.2. Check Request Schema

**Expected Fields**:
- `message` (required, string, max 500)
- `session_id` (optional, UUID)
- `context` (optional, object)
  - `location` (optional)
    - `latitude` (number, -90 to 90)
    - `longitude` (number, -180 to 180)
  - `preferences` (optional)
    - `max_distance_km` (integer, 1-500)
    - `surface_types` (array, enum: sand/pebble/rock/concrete)
    - `facilities` (array of strings)

### 3.3. Check Response Schemas

**200 OK**:
- `session_id` (UUID)
- `message` (string)
- `intent` (enum: beach_search, beach_comparison, weather_query, itinerary_request, accessibility_query, general_question, unknown)
- `beaches` (array of AiBeachRecommendation)
- `filters_applied` (object)
- `suggestions` (array of strings)
- `metadata` (object)

**Error Responses**:
- 400: `AiErrorResponse`
- 402: `AiPaymentRequiredError`
- 413: `AiTokenBudgetError`
- 429: `AiRateLimitError`
- 500: `AiErrorResponse`
- 503: `AiServiceUnavailableError`

---

## 4. Test with Swagger UI "Try it out"

### 4.1. Test Anonymous Request (Free Tier)

1. Click **"Try it out"**
2. Leave **Authorization** empty (anonymous)
3. Set **Accept-Language**: `en`
4. Request Body:
```json
{
  "message": "Show me beaches in Croatia"
}
```
5. Click **"Execute"**

**Expected Response**: 200 OK with beach recommendations

---

### 4.2. Test Authenticated Request

1. Click **"Authorize"** (lock icon at top)
2. Enter Bearer token: `Bearer YOUR_TOKEN_HERE`
3. Click **"Authorize"**
4. Click **"Try it out"**
5. Request Body:
```json
{
  "message": "Create a 7-day itinerary in Croatia",
  "context": {
    "location": {
      "latitude": 45.8150,
      "longitude": 15.9819
    }
  }
}
```
6. Click **"Execute"**

**Expected Response**: 402 Payment Required (itinerary is paid feature)

---

### 4.3. Test Rate Limit (429)

1. Send 101 requests rapidly (anonymous limit: 100/hour)
2. **Expected Response**: 429 Too Many Requests

**Example Response**:
```json
{
  "error": "rate_limit_exceeded",
  "message": "You have exceeded the rate limit for free tier",
  "limit": 100,
  "reset_at": "2025-12-22T15:00:00Z",
  "upgrade_url": "https://beachrex.com/pricing"
}
```

---

### 4.4. Test Token Budget (413)

1. Send a very long message (close to 500 chars) with complex context
2. **Expected Response**: 413 Payload Too Large (if token budget exceeded)

**Example Request**:
```json
{
  "message": "I need detailed information about all beaches in Croatia, Slovenia, and Montenegro that are family-friendly, have parking, showers, playgrounds, restaurants, and are wheelchair accessible. Please include weather forecasts, water temperature, and accessibility reports for each beach. Also compare the top 10 beaches and create a detailed itinerary for a 14-day trip visiting all of them.",
  "context": {
    "location": {
      "latitude": 45.8150,
      "longitude": 15.9819
    },
    "preferences": {
      "max_distance_km": 500,
      "surface_types": ["sand", "pebble", "rock", "concrete"],
      "facilities": ["parking", "showers", "playground", "restaurant", "lifeguard", "beach_bar", "water_sports", "volleyball", "changing_rooms", "toilets"]
    }
  }
}
```

**Expected Response**: 413 Token Budget Exceeded

---

## 5. Verify Schemas with CLI

### 5.1. Check All AI Schemas

```bash
cat storage/api-docs/api-docs.json | jq '.components.schemas | keys | map(select(startswith("Ai")))'
```

**Expected Output**:
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

### 5.2. Check Endpoint Definition

```bash
cat storage/api-docs/api-docs.json | jq '.paths."/api/v1/ai/chat".post | keys'
```

**Expected Output**:
```json
[
  "description",
  "operationId",
  "parameters",
  "requestBody",
  "responses",
  "security",
  "summary",
  "tags"
]
```

---

### 5.3. Check Security Scheme

```bash
cat storage/api-docs/api-docs.json | jq '.paths."/api/v1/ai/chat".post.security'
```

**Expected Output**:
```json
[
  {"sanctum": []},  // Authenticated
  {}                // Anonymous
]
```

---

### 5.4. Check Response Codes

```bash
cat storage/api-docs/api-docs.json | jq '.paths."/api/v1/ai/chat".post.responses | keys'
```

**Expected Output**:
```json
[
  "200",
  "400",
  "402",
  "413",
  "429",
  "500",
  "503"
]
```

---

## 6. Export OpenAPI Schema

### 6.1. Export as JSON

```bash
cp storage/api-docs/api-docs.json public/openapi.json
```

**URL**: `http://localhost/openapi.json`

---

### 6.2. Export as YAML

```bash
./vendor/bin/sail artisan l5-swagger:generate
cat storage/api-docs/api-docs.yaml
```

---

### 6.3. Generate TypeScript Interfaces

**Using openapi-typescript**:

```bash
npx openapi-typescript http://localhost/openapi.json --output src/types/api.ts
```

**Result**: TypeScript interfaces for all AI schemas

---

## 7. Validate OpenAPI Schema

### 7.1. Using Swagger Editor

1. Go to: https://editor.swagger.io/
2. File → Import File → `storage/api-docs/api-docs.json`
3. Check for validation errors

**Expected**: No errors

---

### 7.2. Using CLI Validator

```bash
npx @apidevtools/swagger-cli validate storage/api-docs/api-docs.json
```

**Expected Output**:
```
storage/api-docs/api-docs.json is valid
```

---

## 8. Common Issues and Solutions

### Issue 1: Swagger UI Not Loading

**Solution**:
```bash
./vendor/bin/sail artisan l5-swagger:generate
php artisan config:clear
php artisan cache:clear
```

---

### Issue 2: Schemas Not Showing

**Check**:
```bash
cat config/l5-swagger.php | grep annotations
```

**Expected**:
```php
'annotations' => [
    base_path('app/Swagger'),
    base_path('app/Swagger/Schemas'),
    base_path('app/Http/Controllers'),
],
```

---

### Issue 3: Security Scheme Not Working

**Check**:
```bash
cat storage/api-docs/api-docs.json | jq '.components.securitySchemes'
```

**Expected**:
```json
{
  "sanctum": {
    "type": "apiKey",
    "description": "Enter token in format (Bearer <token>)",
    "name": "Authorization",
    "in": "header"
  }
}
```

---

## 9. Quick Reference: curl Examples

### 9.1. Success (200)

```bash
curl -X POST http://localhost/api/v1/ai/chat \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -H "Accept-Language: en" \
  -d '{
    "message": "Show me beaches in Croatia"
  }' | jq
```

---

### 9.2. Payment Required (402)

```bash
curl -X POST http://localhost/api/v1/ai/chat \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -d '{
    "message": "Create a 7-day itinerary in Croatia"
  }' | jq
```

---

### 9.3. Rate Limit (429)

```bash
# Send 101 requests rapidly
for i in {1..101}; do
  curl -X POST http://localhost/api/v1/ai/chat \
    -H "Content-Type: application/json" \
    -H "Accept: application/json" \
    -d '{"message": "Test"}' | jq '.error // .session_id'
  echo "---"
done
```

---

### 9.4. Token Budget (413)

```bash
curl -X POST http://localhost/api/v1/ai/chat \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "message": "I need detailed information about all beaches in Croatia, Slovenia, and Montenegro that are family-friendly, have parking, showers, playgrounds, restaurants, and are wheelchair accessible. Please include weather forecasts, water temperature, and accessibility reports for each beach.",
    "context": {
      "location": {"latitude": 45.8150, "longitude": 15.9819},
      "preferences": {
        "max_distance_km": 500,
        "surface_types": ["sand", "pebble", "rock", "concrete"],
        "facilities": ["parking", "showers", "playground", "restaurant", "lifeguard"]
      }
    }
  }' | jq
```

---

## Summary

**Swagger UI**: `http://localhost/api/documentation`

**OpenAPI JSON**: `storage/api-docs/api-docs.json`

**Schemas**: 9 AI schemas (AiChatRequest, AiChatResponse, etc.)

**Endpoints**: 1 endpoint (POST /api/v1/ai/chat)

**Response Codes**: 7 documented (200, 400, 402, 413, 429, 500, 503)

**Security**: Optional authentication (sanctum or anonymous)

**All tests passing**: ✅

