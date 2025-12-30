# Task #3 Phase 1 - Summary for User Approval

**Date**: 2025-12-30
**Task**: Payments + Entitlements (Stripe)
**Phase**: 1 - Architecture & Contracts ONLY (NO CODE)
**Status**: AWAITING USER APPROVAL

---

## What Was Delivered

### Documents Created (4 files)
1. **TASK_3_PHASE_1_PROPOSAL.md** (300 lines) - Complete architecture proposal
2. **TASK_3_ENTITLEMENT_MODEL_ADR.md** (250 lines) - ADR for credit-based model
3. **TASK_3_PHASE_1_SUMMARY.md** (this file) - Executive summary for approval
4. **AI_INDEX.md** (updated) - Added payment & entitlement section

### Documents Updated (3 files)
5. **docs/ai/AI_INDEX.md** - Added 88-line payment section
6. **docs/ai/implementation/STATE.md** - (pending update)
7. **docs/ai/implementation/TASKS.md** - (pending update)

---

## Key Decisions Made

### 1. Entitlement Model: Credit-Based ✅

**Decision**: Use credit-based model (NOT time-based)

**Rationale**:
- ✅ Aligns with pricing ($19/$39/$59 per itinerary, not per month)
- ✅ Prevents revenue leakage (one purchase = one itinerary)
- ✅ Prevents abuse (can't generate unlimited itineraries)
- ✅ Enables future bundles ("buy 5, get 1 free")

**How it works**:
- Purchase $39 for 7-day itinerary → `itinerary_7day_credits += 1`
- Generate itinerary → Check credits > 0 → Decrement credits by 1
- No credits → 402 Payment Required

**Alternative rejected**: Time-based access (30-day window) - would allow unlimited itineraries, causing revenue leakage

---

### 2. Stripe Integration: Checkout (Hosted) ✅

**Decision**: Use Stripe Checkout (hosted payment page)

**Rationale**:
- ✅ PCI compliance handled by Stripe
- ✅ No card data stored on our servers
- ✅ Supports Apple Pay, Google Pay out of the box
- ✅ Simpler than Stripe Elements (custom form)

**Flow**:
1. User clicks "Purchase" → API creates Checkout Session
2. User redirected to Stripe Checkout page
3. User completes payment → Stripe sends webhook
4. Webhook grants credits → User can generate itinerary

---

### 3. Webhook Security: Signature Verification ✅

**Decision**: Use Stripe signature verification (mandatory)

**Rationale**:
- ✅ Prevents fake webhook attacks
- ✅ Industry standard
- ✅ Required by Stripe best practices

**Implementation**:
```php
$event = \Stripe\Webhook::constructEvent(
    $payload,
    $request->header('Stripe-Signature'),
    config('services.stripe.webhook_secret')
);
```

---

## API Endpoints Defined

### 1. POST /api/v1/payments/stripe/checkout
**Purpose**: Create Stripe Checkout session
**Auth**: Required (Laravel Sanctum)
**Request**: `{ "product_type": "itinerary_7day" }`
**Response**: `{ "checkout_url": "https://checkout.stripe.com/...", "session_id": "cs_..." }`

### 2. POST /api/v1/payments/stripe/webhook
**Purpose**: Receive Stripe webhook events
**Auth**: Stripe signature verification
**Events**: `checkout.session.completed`, `checkout.session.expired`, `charge.refunded`
**Idempotency**: Uses `stripe_event_id` to prevent duplicates

### 3. GET /api/v1/me/entitlements
**Purpose**: Query user's credit balance
**Auth**: Required (Laravel Sanctum)
**Response**: `{ "entitlements": { "itinerary_3day_credits": 0, ... }, "purchases": [...] }`

---

## Database Schema Defined

### user_entitlements Table
```sql
CREATE TABLE user_entitlements (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT NOT NULL UNIQUE REFERENCES users(id) ON DELETE CASCADE,
    itinerary_3day_credits INT NOT NULL DEFAULT 0,
    itinerary_7day_credits INT NOT NULL DEFAULT 0,
    itinerary_14day_credits INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMP NOT NULL DEFAULT NOW()
);
```

### purchases Table
```sql
CREATE TABLE purchases (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    product_type VARCHAR(50) NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    currency VARCHAR(3) NOT NULL DEFAULT 'USD',
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    stripe_session_id VARCHAR(255) UNIQUE,
    stripe_payment_intent_id VARCHAR(255),
    stripe_event_id VARCHAR(255) UNIQUE,
    metadata JSONB,
    purchased_at TIMESTAMP,
    created_at TIMESTAMP NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMP NOT NULL DEFAULT NOW()
);
```

---

## PlanEnforcementService Integration

### Current Behavior (Task #2)
```php
// All users get 402 (no payment system yet)
if (!$this->canAccessFeature($user, 'multi_day_itineraries')) {
    return $this->paymentRequiredResponse(...); // 402
}
```

### New Behavior (Task #3 Phase 2)
```php
// Check if user has credits
$productType = $this->mapDaysToProductType($days); // 3→itinerary_3day, 7→itinerary_7day, 14→itinerary_14day

if (!$this->hasEntitlement($user, $productType)) {
    return $this->paymentRequiredResponse(...); // 402 with purchase URL
}

// Credits will be decremented in ItineraryController after successful generation
```

**Key Changes**:
1. ✅ Map `duration_days` to `product_type`
2. ✅ Check `user->entitlements->{product_type}_credits > 0`
3. ✅ Return 402 if no credits
4. ✅ Decrement credits AFTER successful itinerary generation (in controller)

---

## OpenAPI Schemas Defined

**New schema files** (to be created in Phase 2):
1. `app/Swagger/Schemas/Payment/CheckoutRequestSchema.php`
2. `app/Swagger/Schemas/Payment/CheckoutResponseSchema.php`
3. `app/Swagger/Schemas/Payment/ProductSchema.php`
4. `app/Swagger/Schemas/Payment/EntitlementsResponseSchema.php`
5. `app/Swagger/Schemas/Payment/PurchaseSchema.php`
6. `app/Swagger/Schemas/Payment/PaymentErrorResponseSchema.php`

All schemas follow existing patterns in `app/Swagger/Schemas/`.

---

## Security & Compliance

### GDPR Compliance ✅
- ✅ No card data stored (Stripe handles)
- ✅ Only Stripe IDs stored (session_id, payment_intent_id)
- ✅ Purchases retained 7 years (accounting/tax requirements)
- ✅ User can delete account → cascade delete purchases/entitlements

### Security ✅
- ✅ Checkout requires authentication (Laravel Sanctum)
- ✅ Webhook uses signature verification (Stripe-Signature header)
- ✅ Entitlements scoped to user (can't view others' credits)
- ✅ Rate limiting (10 checkout requests/minute, 60 entitlement requests/minute)

### Feature Flags ✅
- ✅ `AI_PAYMENTS_ENABLED` - Kill switch for entire payment system
- ✅ `STRIPE_ENABLED` - Kill switch for Stripe integration only

---

## Open Questions (Need User Approval)

### Q1: Refund Handling
**Question**: Should refunds revoke entitlement credits?

**Options**:
1. **Revoke credits** - User loses credits when refunded (prevents abuse)
2. **Keep credits** - User keeps credits even after refund (generous policy)

**Recommendation**: Revoke credits (prevent abuse)

**Your decision**: _______________

---

### Q2: Credit Expiration
**Question**: Should credits expire after a certain period?

**Options**:
1. **No expiration** - Credits never expire (user-friendly, MVP simplicity)
2. **1-year expiration** - Credits expire after 1 year (encourages usage)
3. **6-month expiration** - Credits expire after 6 months (aggressive)

**Recommendation**: No expiration for MVP (add in Phase 2 if needed)

**Your decision**: _______________

---

### Q3: Bundle Pricing
**Question**: Should we offer bundle discounts (e.g., "buy 5, get 1 free")?

**Options**:
1. **No bundles** - Single purchases only (MVP simplicity)
2. **Bundles** - Offer 5-pack, 10-pack with discounts (future upsell)

**Recommendation**: No bundles for MVP (add in Phase 2)

**Your decision**: _______________

---

## Phase 2 Scope (After Approval)

### Implementation Tasks (Estimated: 1 week)
1. Create migrations (`user_entitlements`, `purchases`)
2. Create models (`UserEntitlement`, `Purchase`)
3. Create controllers (`CheckoutController`, `WebhookController`, `EntitlementController`)
4. Create services (`StripeService`, `EntitlementService`)
5. Update `PlanEnforcementService` (credit checks)
6. Update `ItineraryController` (credit decrement)
7. Create OpenAPI schemas (6 files)
8. Write tests (checkout, webhook, entitlement, enforcement)
9. Regenerate `storage/api-docs/api-docs.json`
10. Update documentation

### Testing Strategy
- Unit tests: Services (StripeService, EntitlementService)
- Feature tests: Controllers (checkout, webhook, entitlement)
- Integration tests: Full flow (purchase → webhook → credits → itinerary)
- Mock Stripe API (use Stripe test mode)

---

## Approval Checklist

Please review and approve:

- [ ] **Entitlement model**: Credit-based (NOT time-based) ✅
- [ ] **API endpoints**: 3 endpoints (checkout, webhook, entitlements) ✅
- [ ] **Database schema**: 2 tables (user_entitlements, purchases) ✅
- [ ] **Security**: Stripe signature verification, Laravel Sanctum auth ✅
- [ ] **GDPR compliance**: No card data stored, 7-year retention ✅
- [ ] **Open Question #1**: Refund handling (revoke credits?) ❓
- [ ] **Open Question #2**: Credit expiration (no expiration for MVP?) ❓
- [ ] **Open Question #3**: Bundle pricing (no bundles for MVP?) ❓

---

## Next Steps

**If approved**:
1. User responds with approval + answers to open questions
2. AI agent proceeds to Phase 2 (implementation)
3. Estimated timeline: 1 week for full implementation + tests

**If changes requested**:
1. User specifies changes needed
2. AI agent updates Phase 1 proposal
3. Re-submit for approval

---

## Files to Review

**Primary**:
1. `docs/ai/implementation/TASK_3_PHASE_1_PROPOSAL.md` - Full architecture (300 lines)
2. `docs/ai/implementation/TASK_3_ENTITLEMENT_MODEL_ADR.md` - Entitlement model decision (250 lines)

**Secondary**:
3. `docs/ai/AI_INDEX.md` - Payment section added (lines 217-301)

---

**Status**: AWAITING USER APPROVAL

**Next Action**: User reviews proposal and provides approval/feedback


