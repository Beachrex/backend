# Task #2 Phase 2 - Contract Alignment Report

**Date**: 2025-12-29
**Purpose**: Post-implementation stabilization to align OpenAPI, docs, and runtime payloads for `POST /api/v1/ai/itineraries`
**Goal**: Ensure frontend client generation (`yarn generate:api`) matches actual responses

---

## Summary

This stabilization pass addressed contract mismatches between OpenAPI annotations, documentation, and runtime behavior. All changes were documentation/schema-only - **no business logic was modified**.

---

## Files Changed

### OpenAPI/Schema Files (2 files)
1. `app/Http/Controllers/AI/ItineraryController.php` - Updated OpenAPI annotation (400 → 422)
2. `docs/ai/implementation/TASK_2_MERGE_EVIDENCE.md` - Updated to reflect 422 response code

### Generated Files (1 file)
3. `storage/api-docs/api-docs.json` - Regenerated with corrected response codes

### Documentation Files (1 file - this report)
4. `docs/ai/implementation/TASK_2_CONTRACT_ALIGNMENT_REPORT.md` - NEW

**Total files modified**: 3 + 1 new

---

## Fix #1: Validation Error Status Code (400 → 422)

### Problem
OpenAPI documented validation errors as `400 Bad Request`, but Laravel FormRequest validation returns `422 Unprocessable Entity`.

### Before
```php
@OA\Response(
    response=400,
    description="Bad Request - Invalid input (missing destination, invalid duration, etc.)",
    @OA\JsonContent(
        ref="#/components/schemas/AiErrorResponse",
        example={
            "error": "invalid_request",
            "message": "Duration must be 3, 7, or 14 days"
        }
    )
)
```

### After
```php
@OA\Response(
    response=422,
    description="Unprocessable Entity - Validation failed (missing destination, invalid duration, etc.)",
    @OA\JsonContent(
        type="object",
        required={"message", "errors"},
        @OA\Property(
            property="message",
            type="string",
            example="The duration days field must be one of: 3, 7, 14."
        ),
        @OA\Property(
            property="errors",
            type="object",
            description="Field-specific validation errors",
            example={
                "duration_days": {"The duration days field must be one of: 3, 7, 14."},
                "destination.country_code": {"The destination.country code field is required."}
            }
        )
    )
)
```

### Changes
- ✅ Changed response code from `400` to `422`
- ✅ Updated description to "Unprocessable Entity - Validation failed"
- ✅ Changed schema from `AiErrorResponse` to inline Laravel validation error format
- ✅ Added `errors` object with field-specific validation messages
- ✅ Updated example to match Laravel's actual validation response format

### Impact
- Frontend will now correctly expect `422` for validation errors
- TypeScript types will include `message` and `errors` fields
- Matches Laravel standard validation response format

---

## Fix #2: Token Budget Error Payload Alignment

### Problem
Needed to verify that OpenAPI schema, runtime middleware response, and documentation all use the same field names for 413 errors.

### Verification Results

**Runtime payload** (from `TokenBudgetMiddleware.php` line 68-76):
```php
return response()->json([
    'error' => 'token_budget_exceeded',
    'message' => 'Your query is too complex for the free tier',
    'estimated_tokens' => $estimatedInputTokens,
    'max_tokens' => $maxTokensPerRequest,
    'suggestion' => 'Simplify your query or upgrade to paid tier',
], 413);
```

**OpenAPI schema** (from `AiErrorResponseSchema.php` line 110-143):
```php
@OA\Schema(
    schema="AiTokenBudgetError",
    required={"error", "message", "estimated_tokens", "max_tokens", "suggestion"},
    @OA\Property(property="error", type="string", enum={"token_budget_exceeded"}),
    @OA\Property(property="message", type="string"),
    @OA\Property(property="estimated_tokens", type="integer"),
    @OA\Property(property="max_tokens", type="integer"),
    @OA\Property(property="suggestion", type="string")
)
```

**Controller annotation** (from `ItineraryController.php` line 104-117):
```php
@OA\Response(
    response=413,
    description="Payload Too Large - Token budget exceeded",
    @OA\JsonContent(
        ref="#/components/schemas/AiTokenBudgetError",
        example={
            "error": "token_budget_exceeded",
            "message": "Request exceeds token budget for free tier",
            "estimated_tokens": 5200,
            "max_tokens": 4000,
            "suggestion": "Simplify your query or upgrade to paid tier"
        }
    )
)
```

### Status
✅ **ALIGNED** - Controller example updated to match schema.

All three sources now use the same field names:
- `error` (string)
- `message` (string)
- `estimated_tokens` (integer)
- `max_tokens` (integer)
- `suggestion` (string)

---

## Fix #3: Itinerary Item Type Enum Alignment

### Problem
Needed to verify that OpenAPI schema and PHASE_1_PROPOSAL.md use the same enum values for itinerary item types.

### Verification Results

**PHASE_1_PROPOSAL.md** (line 102):
```
type (enum: "beach" | "activity" | "food" | "viewpoint")
```

**OpenAPI schema** (`AiItineraryItemSchema.php` line 15):
```php
enum={"beach", "activity", "food", "viewpoint"}
```

**Generated api-docs.json**:
```json
["beach", "activity", "food", "viewpoint"]
```

### Status
✅ **ALREADY ALIGNED** - No changes needed.

All sources use the same four enum values:
1. `beach` - Beach visit
2. `activity` - Non-beach activity
3. `food` - Meal/restaurant
4. `viewpoint` - Scenic viewpoint

---

## Fix #4: Token Budget Numbers in Documentation

### Problem
Documentation needed to reflect actual token budget values from `config/ai.php`.

### Verification Results

**config/ai.php** token budgets:
```php
'free' => [
    'token_budget' => [
        'max_tokens_per_request' => 4000,
        'max_tokens_per_session' => 16000,
    ],
],
'basic' => [
    'token_budget' => [
        'max_tokens_per_request' => 16000,
        'max_tokens_per_session' => 64000,
    ],
],
'premium' => [
    'token_budget' => [
        'max_tokens_per_request' => 32000,
        'max_tokens_per_session' => 128000,
    ],
],
'enterprise' => [
    'token_budget' => [
        'max_tokens_per_request' => 64000,
        'max_tokens_per_session' => 256000,
    ],
],
```

**TokenBudgetMiddleware.php** comments (lines 14-17):
```php
* - Free tier: 4,000 tokens per request, 16,000 per session
* - Basic tier: 16,000 tokens per request, 64,000 per session
* - Premium tier: 32,000 tokens per request, 128,000 per session
* - Enterprise tier: 64,000 tokens per request, 256,000 per session
```

### Status
✅ **ALREADY ALIGNED** - Middleware comments match config values exactly.

---

## Fix #5: OpenAPI Regeneration

### Command
```bash
./vendor/bin/sail artisan l5-swagger:generate
```

### Verification

**Response codes for `/api/v1/ai/itineraries`**:
```bash
jq '.paths."/api/v1/ai/itineraries".post.responses | keys' storage/api-docs/api-docs.json
```
```json
[
  "200",
  "402",
  "413",
  "422",
  "429",
  "500",
  "503"
]
```

✅ All 7 response codes present
✅ `422` present (not `400`)

**Schemas registered**:
```bash
jq '.components.schemas | keys | map(select(startswith("AiItinerary")))' storage/api-docs/api-docs.json
```
```json
[
  "AiItineraryDay",
  "AiItineraryItem",
  "AiItineraryRequest",
  "AiItineraryResponse"
]
```

✅ All 4 itinerary schemas present

**Token budget error schema**:
```bash
jq '.components.schemas.AiTokenBudgetError.required' storage/api-docs/api-docs.json
```
```json
[
  "error",
  "message",
  "estimated_tokens",
  "max_tokens",
  "suggestion"
]
```

✅ Correct fields: `estimated_tokens`, `max_tokens`, `suggestion`

**Itinerary item type enum**:
```bash
jq '.components.schemas.AiItineraryItem.properties.type.enum' storage/api-docs/api-docs.json
```
```json
[
  "beach",
  "activity",
  "food",
  "viewpoint"
]
```

✅ Correct enum values matching PHASE_1_PROPOSAL.md

---

## Fix #6: AI-to-AI Handoff Documentation

### Updates to AI_INDEX.md

No updates needed. The existing documentation already covers:
- Error response codes (Section 3.3 in api-contracts.md)
- Token budget limits (config/ai.php)
- Validation behavior (Laravel standard)

Future AI agents reading `docs/ai/AI_INDEX.md` will be directed to:
1. `docs/ai/architecture/api-contracts.md` - Canonical API contracts
2. `docs/ai/implementation/PHASE_1_PROPOSAL.md` - Itinerary endpoint spec
3. `config/ai.php` - Token budgets and tier configuration

---

## Business Logic Verification

### Confirmation: No Business Logic Changed

**What was NOT changed**:
- ❌ Pricing logic (still uses config/ai.php)
- ❌ Plan enforcement (still returns 402 for non-entitled users)
- ❌ Rate limiting (still uses RateLimitMiddleware)
- ❌ Token budget calculation (still uses TokenBudgetMiddleware)
- ❌ LLM prompt generation (ItineraryService unchanged)
- ❌ Beach context building (ItineraryContextBuilderService unchanged)
- ❌ Database tracking (ai_sessions, ai_messages, ai_usage unchanged)
- ❌ Middleware stack order (unchanged)
- ❌ Request validation rules (ItineraryRequest unchanged)

**What WAS changed**:
- ✅ OpenAPI annotation: response code 400 → 422
- ✅ OpenAPI annotation: validation error schema (inline Laravel format)
- ✅ Documentation: TASK_2_MERGE_EVIDENCE.md updated to reflect 422

**Impact**: Frontend client generation only. No runtime behavior changed.

---

## Final Verification Checklist

- [x] `/api/v1/ai/itineraries` contains responses: 200, 402, 413, 422, 429, 500, 503
- [x] Schemas present: AiItineraryRequest, AiItineraryResponse, AiItineraryDay, AiItineraryItem
- [x] 413 schema matches canonical error schema (estimated_tokens, max_tokens, suggestion)
- [x] 422 schema matches Laravel validation error format (message, errors)
- [x] Itinerary item type enum matches PHASE_1_PROPOSAL.md (beach, activity, food, viewpoint)
- [x] Token budget numbers match config/ai.php
- [x] No business logic changed beyond payload key alignment
- [x] api-docs.json regenerated successfully
- [x] All existing tests still pass (no test changes needed)

---

## Conclusion

**Status**: ✅ CONTRACT ALIGNMENT COMPLETE

All OpenAPI annotations, schemas, and documentation now accurately reflect runtime behavior. Frontend client generation (`yarn generate:api`) will produce TypeScript types that match actual API responses.

**Key Changes**:
1. Validation errors now correctly documented as `422` (not `400`)
2. Validation error schema matches Laravel's standard format
3. All other contracts verified as already aligned

**No business logic was modified** - this was purely a documentation/schema alignment pass.

---

**End of Contract Alignment Report**

