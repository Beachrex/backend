# Task #2 - 413 OpenAPI Example Fix

**Date**: 2025-12-29
**Purpose**: Fix 413 response example in ItineraryController to match AiTokenBudgetError schema exactly

---

## Problem

The OpenAPI example for 413 response in `POST /api/v1/ai/itineraries` used incorrect field names:
- `limit` instead of `max_tokens`
- `estimated` instead of `estimated_tokens`
- `upgrade_url` instead of `suggestion`

This caused a mismatch between the example and the actual schema definition.

---

## Solution

Updated the example in `app/Http/Controllers/AI/ItineraryController.php` to match the canonical `AiTokenBudgetError` schema.

### Before
```php
@OA\Response(
    response=413,
    description="Payload Too Large - Token budget exceeded",
    @OA\JsonContent(
        ref="#/components/schemas/AiTokenBudgetError",
        example={
            "error": "token_budget_exceeded",
            "message": "Request exceeds token budget for free tier",
            "limit": 4000,
            "estimated": 5200,
            "upgrade_url": "https://beachrex.com/pricing"
        }
    )
)
```

### After
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

---

## Changes Made

### Files Modified (3)
1. **app/Http/Controllers/AI/ItineraryController.php** (lines 104-117)
   - Updated 413 example to use correct field names

2. **docs/ai/implementation/TASK_2_MERGE_EVIDENCE.md** (lines 88-98)
   - Updated documentation to reflect corrected example

3. **docs/ai/implementation/TASK_2_CONTRACT_ALIGNMENT_REPORT.md** (lines 122-148)
   - Updated alignment report to show example is now aligned

### Files Regenerated (1)
4. **storage/api-docs/api-docs.json**
   - Regenerated with corrected example

---

## Verification

### 1. OpenAPI Example in api-docs.json
```bash
jq '.paths."/api/v1/ai/itineraries".post.responses."413".content."application/json".example' storage/api-docs/api-docs.json
```

**Output**:
```json
{
  "error": "token_budget_exceeded",
  "message": "Request exceeds token budget for free tier",
  "estimated_tokens": 5200,
  "max_tokens": 4000,
  "suggestion": "Simplify your query or upgrade to paid tier"
}
```

✅ Example now matches schema exactly

### 2. Schema Definition (Unchanged)
```bash
jq '.components.schemas.AiTokenBudgetError.required' storage/api-docs/api-docs.json
```

**Output**:
```json
[
  "error",
  "message",
  "estimated_tokens",
  "max_tokens",
  "suggestion"
]
```

✅ Schema unchanged - still correct

### 3. Runtime Middleware (Unchanged)
**File**: `app/Http/Middleware/AI/TokenBudgetMiddleware.php` (lines 68-76)

```php
return response()->json([
    'error' => 'token_budget_exceeded',
    'message' => 'Your query is too complex for the free tier',
    'estimated_tokens' => $estimatedInputTokens,
    'max_tokens' => $maxTokensPerRequest,
    'suggestion' => 'Simplify your query or upgrade to paid tier',
], 413);
```

✅ Runtime unchanged - already correct

---

## Alignment Confirmation

All three sources now use identical field names:

| Source | error | message | estimated_tokens | max_tokens | suggestion |
|--------|-------|---------|------------------|------------|------------|
| **Middleware** (runtime) | ✅ | ✅ | ✅ | ✅ | ✅ |
| **Schema** (AiTokenBudgetError) | ✅ | ✅ | ✅ | ✅ | ✅ |
| **Example** (ItineraryController) | ✅ | ✅ | ✅ | ✅ | ✅ |

---

## Git Summary

```bash
git status --short
```

```
M app/Http/Controllers/AI/ItineraryController.php
M docs/ai/AI_INDEX.md
M docs/ai/implementation/TASK_2_MERGE_EVIDENCE.md
M storage/api-docs/api-docs.json
?? docs/ai/implementation/TASK_2_CONTRACT_ALIGNMENT_REPORT.md
?? docs/ai/implementation/TASK_2_STABILIZATION_COMPLETE.md
?? docs/ai/implementation/TASK_2_413_EXAMPLE_FIX.md
```

```bash
git diff --stat
```

```
app/Http/Controllers/AI/ItineraryController.php | 33 +++++++++++++++++++++++-----------
docs/ai/AI_INDEX.md                             |  1 +
docs/ai/implementation/TASK_2_MERGE_EVIDENCE.md | 40 +++++++++++++++++++++++++++-------------
storage/api-docs/api-docs.json                  | 38 ++++++++++++++++++++++++++++----------
4 files changed, 78 insertions(+), 34 deletions(-)
```

---

## Impact

### Frontend Client Generation
- TypeScript types will now correctly expect `estimated_tokens`, `max_tokens`, and `suggestion` fields
- No more confusion between example and schema
- Frontend developers can rely on the example as accurate

### Documentation
- All documentation now shows consistent field names
- Future AI agents will see aligned examples
- No discrepancies between docs and runtime

---

## Status

✅ **COMPLETE** - 413 example now matches AiTokenBudgetError schema exactly

---

**End of Fix Report**

