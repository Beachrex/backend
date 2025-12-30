# Task #3 Phase 1 - Payments + Entitlements Architecture Proposal

**Date**: 2025-12-30
**Status**: PROPOSAL (awaiting approval)
**Task**: Stripe-based payment flow + entitlement system for itinerary products
**Phase**: 1 - Architecture & Contracts ONLY (NO CODE)

---

## Executive Summary

This proposal defines the architecture for Stripe-based one-time payments and entitlement tracking for multi-day itinerary products. The system will enable users to purchase itinerary generation credits and allow `PlanEnforcementService` to check entitlements before granting access.

**Key Decisions**:
1. **Entitlement Model**: Credit-based (not time-based) - see ADR in TASK_3_ENTITLEMENT_MODEL_ADR.md
2. **Payment Flow**: Stripe Checkout (hosted) for MVP simplicity
3. **Webhook Security**: Stripe signature verification (mandatory)
4. **Entitlement Storage**: Database table `user_entitlements` with product-specific credits

---

## 1. Entitlement Model Decision

### Option A: Time-Based Access ❌ REJECTED
**Model**: User purchases "itinerary access" valid for 30 days
- Purchase: $39 for 7-day itinerary → `itinerary_access_until = now() + 30 days`
- Check: `if (now() < itinerary_access_until) { allow }`

**Pros**:
- Simple to implement
- Easy to understand ("access for 30 days")

**Cons**:
- ❌ Encourages bulk generation and sharing (user generates 100 itineraries in 30 days, shares PDFs)
- ❌ No control over usage volume
- ❌ Revenue leakage (one purchase = unlimited itineraries)
- ❌ Doesn't align with pricing model ($19/$39/$59 per itinerary, not per month)

### Option B: Credit-Based ✅ ACCEPTED
**Model**: User purchases credits for specific itinerary types
- Purchase: $39 for 7-day itinerary → `itinerary_7day_credits += 1`
- Check: `if (itinerary_7day_credits > 0) { allow; decrement }`

**Pros**:
- ✅ Aligns with pricing model ($19/$39/$59 per itinerary)
- ✅ Prevents revenue leakage (one purchase = one itinerary)
- ✅ Clear value proposition ("buy one, get one")
- ✅ Enables future bundles ("buy 5, get 1 free")
- ✅ Prevents abuse (can't generate unlimited itineraries)

**Cons**:
- Slightly more complex (3 credit types instead of 1 timestamp)

**Decision**: **Credit-based model** (Option B)

**Rationale**: Aligns with business model, prevents revenue leakage, enables future upsells.

---

## 2. Stripe Product Mapping

### Products and Prices

**Stripe Products** (created in Stripe Dashboard):
1. `itinerary_3day` - 3-Day Beach Itinerary
2. `itinerary_7day` - 7-Day Beach Itinerary
3. `itinerary_14day` - 14-Day Beach Itinerary

**Stripe Prices** (one-time payment, USD):
- `price_itinerary_3day` → $19.00 USD
- `price_itinerary_7day` → $39.00 USD
- `price_itinerary_14day` → $59.00 USD

**Configuration** (stored in `config/ai.php`):
```php
'stripe' => [
    'products' => [
        'itinerary_3day' => [
            'price_id' => env('STRIPE_PRICE_ITINERARY_3DAY'),
            'amount' => 19.00,
            'currency' => 'USD',
            'credits' => 1,
        ],
        'itinerary_7day' => [
            'price_id' => env('STRIPE_PRICE_ITINERARY_7DAY'),
            'amount' => 39.00,
            'currency' => 'USD',
            'credits' => 1,
        ],
        'itinerary_14day' => [
            'price_id' => env('STRIPE_PRICE_ITINERARY_14DAY'),
            'amount' => 59.00,
            'currency' => 'USD',
            'credits' => 1,
        ],
    ],
],
```

**Stripe Metadata** (attached to Checkout Session):
- `product_type` = `itinerary_3day` | `itinerary_7day` | `itinerary_14day`
- `user_id` = `{user_id}` (Beachrex user ID)
- `credits` = `1` (number of credits to grant)

---

## 3. API Endpoints

### 3.1. POST /api/v1/payments/stripe/checkout

**Purpose**: Create Stripe Checkout session for itinerary purchase

**Authentication**: Required (Laravel Sanctum)

**Request**:
```json
{
  "product_type": "itinerary_7day"
}
```

**Request Schema**:
- `product_type` (string, required, enum: `itinerary_3day`, `itinerary_7day`, `itinerary_14day`)

**Response 200** (Success):
```json
{
  "checkout_url": "https://checkout.stripe.com/c/pay/cs_test_...",
  "session_id": "cs_test_a1b2c3d4e5f6...",
  "product": {
    "type": "itinerary_7day",
    "price": 39.00,
    "currency": "USD",
    "credits": 1
  }
}
```

**Response 400** (Invalid product_type):
```json
{
  "error": "invalid_product",
  "message": "Invalid product type. Must be one of: itinerary_3day, itinerary_7day, itinerary_14day"
}
```

**Response 401** (Unauthenticated):
```json
{
  "error": "unauthenticated",
  "message": "Authentication required to purchase products"
}
```

**Response 500** (Stripe API error):
```json
{
  "error": "payment_gateway_error",
  "message": "Unable to create checkout session. Please try again later."
}
```

---

### 3.2. POST /api/v1/payments/stripe/webhook

**Purpose**: Receive Stripe webhook events (payment confirmation)

**Authentication**: Stripe signature verification (NOT Laravel Sanctum)

**Request**: Stripe webhook payload (JSON)

**Events Handled**:
1. `checkout.session.completed` - Payment successful, grant entitlement
2. `checkout.session.expired` - Payment abandoned, no action
3. `charge.refunded` - Refund issued, revoke entitlement (future)

**Response 200** (Success):
```json
{
  "received": true
}
```

**Response 400** (Invalid signature):
```json
{
  "error": "invalid_signature",
  "message": "Webhook signature verification failed"
}
```

**Response 422** (Missing metadata):
```json
{
  "error": "invalid_webhook_data",
  "message": "Missing required metadata: product_type or user_id"
}
```

**Idempotency**: Use Stripe `event.id` to prevent duplicate processing

---

### 3.3. GET /api/v1/me/entitlements

**Purpose**: Query current user's entitlement state

**Authentication**: Required (Laravel Sanctum)

**Request**: None (GET)

**Response 200** (Success):
```json
{
  "entitlements": {
    "itinerary_3day_credits": 0,
    "itinerary_7day_credits": 1,
    "itinerary_14day_credits": 0
  },
  "purchases": [
    {
      "id": 123,
      "product_type": "itinerary_7day",
      "amount": 39.00,
      "currency": "USD",
      "status": "completed",
      "purchased_at": "2025-12-30T10:30:00Z",
      "stripe_session_id": "cs_test_a1b2c3d4e5f6..."
    }
  ]
}
```

**Response 401** (Unauthenticated):
```json
{
  "error": "unauthenticated",
  "message": "Authentication required"
}
```

---

## 4. Request/Response JSON Contracts

### 4.1. CheckoutRequest
```json
{
  "product_type": "itinerary_7day"
}
```

**Validation Rules**:
- `product_type`: required, string, in:itinerary_3day,itinerary_7day,itinerary_14day

### 4.2. CheckoutResponse
```json
{
  "checkout_url": "https://checkout.stripe.com/c/pay/cs_test_...",
  "session_id": "cs_test_a1b2c3d4e5f6...",
  "product": {
    "type": "itinerary_7day",
    "price": 39.00,
    "currency": "USD",
    "credits": 1
  }
}
```

### 4.3. EntitlementsResponse
```json
{
  "entitlements": {
    "itinerary_3day_credits": 0,
    "itinerary_7day_credits": 1,
    "itinerary_14day_credits": 0
  },
  "purchases": [
    {
      "id": 123,
      "product_type": "itinerary_7day",
      "amount": 39.00,
      "currency": "USD",
      "status": "completed",
      "purchased_at": "2025-12-30T10:30:00Z",
      "stripe_session_id": "cs_test_a1b2c3d4e5f6..."
    }
  ]
}
```

### 4.4. Error Responses

**400 Invalid Product**:
```json
{
  "error": "invalid_product",
  "message": "Invalid product type. Must be one of: itinerary_3day, itinerary_7day, itinerary_14day"
}
```

**401 Unauthenticated**:
```json
{
  "error": "unauthenticated",
  "message": "Authentication required"
}
```

**500 Payment Gateway Error**:
```json
{
  "error": "payment_gateway_error",
  "message": "Unable to create checkout session. Please try again later."
}
```

---

## 5. OpenAPI Schemas

### Schema File Locations

**New schema files** (to be created in Phase 2):
1. `app/Swagger/Schemas/Payment/CheckoutRequestSchema.php`
2. `app/Swagger/Schemas/Payment/CheckoutResponseSchema.php`
3. `app/Swagger/Schemas/Payment/ProductSchema.php`
4. `app/Swagger/Schemas/Payment/EntitlementsResponseSchema.php`
5. `app/Swagger/Schemas/Payment/PurchaseSchema.php`
6. `app/Swagger/Schemas/Payment/PaymentErrorResponseSchema.php`

### Schema Definitions

**CheckoutRequest**:
```php
@OA\Schema(
    schema="CheckoutRequest",
    required={"product_type"},
    @OA\Property(property="product_type", type="string", enum={"itinerary_3day", "itinerary_7day", "itinerary_14day"})
)
```

**CheckoutResponse**:
```php
@OA\Schema(
    schema="CheckoutResponse",
    required={"checkout_url", "session_id", "product"},
    @OA\Property(property="checkout_url", type="string", format="uri"),
    @OA\Property(property="session_id", type="string"),
    @OA\Property(property="product", ref="#/components/schemas/Product")
)
```

**Product**:
```php
@OA\Schema(
    schema="Product",
    required={"type", "price", "currency", "credits"},
    @OA\Property(property="type", type="string"),
    @OA\Property(property="price", type="number", format="float"),
    @OA\Property(property="currency", type="string"),
    @OA\Property(property="credits", type="integer")
)
```

**EntitlementsResponse**:
```php
@OA\Schema(
    schema="EntitlementsResponse",
    required={"entitlements", "purchases"},
    @OA\Property(property="entitlements", type="object"),
    @OA\Property(property="purchases", type="array", @OA\Items(ref="#/components/schemas/Purchase"))
)
```

---

## 6. Security

### 6.1. Checkout Endpoint Security
- **Authentication**: Laravel Sanctum (required)
- **Authorization**: Any authenticated user can create checkout session
- **Rate Limiting**: 10 requests per minute per user (prevent spam)
- **CSRF**: Not required (API endpoint, token-based auth)

### 6.2. Webhook Endpoint Security
- **Authentication**: Stripe signature verification (mandatory)
- **Signature Header**: `Stripe-Signature`
- **Verification**: `Stripe\Webhook::constructEvent($payload, $signature, $secret)`
- **Secret**: `STRIPE_WEBHOOK_SECRET` (from .env)
- **IP Whitelist**: Optional (Stripe webhook IPs)

### 6.3. Entitlements Endpoint Security
- **Authentication**: Laravel Sanctum (required)
- **Authorization**: User can only view their own entitlements
- **Rate Limiting**: 60 requests per minute per user

---

## 7. PlanEnforcementService Integration

### Current Behavior (Task #2)
```php
public function checkMultiDayItinerary(?User $user, int $days): ?JsonResponse
{
    if ($days < 2) {
        return null; // Single-day is free
    }
    
    // Check if user can access multi-day itineraries
    if (!$this->canAccessFeature($user, 'multi_day_itineraries')) {
        return $this->paymentRequiredResponse(...); // 402
    }
    
    return null; // Allowed
}
```

### New Behavior (Task #3 Phase 2)
```php
public function checkMultiDayItinerary(?User $user, int $days): ?JsonResponse
{
    if ($days < 2) {
        return null; // Single-day is free
    }
    
    if (!$user) {
        return $this->paymentRequiredResponse(...); // 402 - must be authenticated
    }
    
    // Check if user has credits for this itinerary type
    $productType = $this->mapDaysToProductType($days); // 3→itinerary_3day, 7→itinerary_7day, 14→itinerary_14day
    
    if (!$this->hasEntitlement($user, $productType)) {
        return $this->paymentRequiredResponse(...); // 402 - no credits
    }
    
    return null; // Allowed (credits will be decremented in ItineraryController)
}

private function hasEntitlement(User $user, string $productType): bool
{
    $creditField = $productType . '_credits'; // e.g., 'itinerary_7day_credits'
    return $user->entitlements->$creditField > 0;
}

private function mapDaysToProductType(int $days): string
{
    if ($days <= 3) return 'itinerary_3day';
    if ($days <= 7) return 'itinerary_7day';
    return 'itinerary_14day';
}
```

**Key Changes**:
1. ✅ Check user authentication (anonymous users get 402)
2. ✅ Map `duration_days` to `product_type` (3→itinerary_3day, 7→itinerary_7day, 14→itinerary_14day)
3. ✅ Check `user->entitlements->{product_type}_credits > 0`
4. ✅ Return 402 if no credits
5. ✅ Return null if credits available (allow request)

**Credit Decrement** (in `ItineraryController`):
```php
public function generate(ItineraryRequest $request)
{
    // ... existing validation ...
    
    // Decrement credits AFTER successful generation
    $productType = $this->mapDaysToProductType($request->duration_days);
    $this->decrementCredits($request->user(), $productType);
    
    return response()->json($itinerary, 200);
}
```

---

## 8. Database Schema

### 8.1. user_entitlements Table

**Purpose**: Store user entitlement credits

```sql
CREATE TABLE user_entitlements (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    itinerary_3day_credits INT NOT NULL DEFAULT 0,
    itinerary_7day_credits INT NOT NULL DEFAULT 0,
    itinerary_14day_credits INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMP NOT NULL DEFAULT NOW(),
    UNIQUE(user_id)
);

CREATE INDEX idx_user_entitlements_user_id ON user_entitlements(user_id);
```

**Columns**:
- `id`: Primary key
- `user_id`: Foreign key to users table (unique, one row per user)
- `itinerary_3day_credits`: Number of 3-day itinerary credits
- `itinerary_7day_credits`: Number of 7-day itinerary credits
- `itinerary_14day_credits`: Number of 14-day itinerary credits
- `created_at`, `updated_at`: Timestamps

**Constraints**:
- `user_id` is unique (one entitlement record per user)
- Credits cannot be negative (enforced in application logic)

---

### 8.2. purchases Table

**Purpose**: Store purchase history for audit trail

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

CREATE INDEX idx_purchases_user_id ON purchases(user_id);
CREATE INDEX idx_purchases_stripe_session_id ON purchases(stripe_session_id);
CREATE INDEX idx_purchases_stripe_event_id ON purchases(stripe_event_id);
CREATE INDEX idx_purchases_status ON purchases(status);
```

**Columns**:
- `id`: Primary key
- `user_id`: Foreign key to users table
- `product_type`: Product purchased (e.g., `itinerary_7day`)
- `amount`: Purchase amount (e.g., 39.00)
- `currency`: Currency code (e.g., `USD`)
- `status`: Purchase status (`pending`, `completed`, `failed`, `refunded`)
- `stripe_session_id`: Stripe Checkout Session ID (unique)
- `stripe_payment_intent_id`: Stripe Payment Intent ID
- `stripe_event_id`: Stripe webhook event ID (for idempotency)
- `metadata`: Additional data (JSONB)
- `purchased_at`: Timestamp when payment completed
- `created_at`, `updated_at`: Timestamps

**Status Values**:
- `pending`: Checkout session created, payment not completed
- `completed`: Payment successful, credits granted
- `failed`: Payment failed
- `refunded`: Payment refunded, credits revoked

---

## 9. Webhook Idempotency

**Problem**: Stripe may send duplicate webhook events

**Solution**: Use `stripe_event_id` as idempotency key

**Implementation**:
```php
public function handleWebhook(Request $request)
{
    $event = $this->verifySignature($request);
    
    // Check if event already processed
    if (Purchase::where('stripe_event_id', $event->id)->exists()) {
        return response()->json(['received' => true], 200); // Already processed
    }
    
    // Process event...
    $purchase = Purchase::create([
        'stripe_event_id' => $event->id,
        // ... other fields
    ]);
    
    return response()->json(['received' => true], 200);
}
```

---

## 10. GDPR Compliance

**Data Stored**:
- ✅ User ID (necessary for entitlement)
- ✅ Stripe Session ID (necessary for refunds/support)
- ✅ Purchase amount/currency (necessary for accounting)
- ✅ Purchase timestamp (necessary for audit trail)

**Data NOT Stored**:
- ❌ Credit card numbers (Stripe handles)
- ❌ CVV codes (Stripe handles)
- ❌ Billing addresses (Stripe handles, optional to sync)

**Data Retention**:
- Purchases: 7 years (accounting/tax requirements)
- Entitlements: Indefinite (user owns credits)

**User Rights**:
- Right to access: `GET /api/v1/me/entitlements`
- Right to deletion: Delete user → cascade delete purchases/entitlements
- Right to portability: Export purchases as JSON/CSV

---

## 11. Feature Flag / Kill Switch

**Configuration** (`config/ai.php`):
```php
'payments' => [
    'enabled' => env('AI_PAYMENTS_ENABLED', false),
    'stripe_enabled' => env('STRIPE_ENABLED', false),
],
```

**Behavior**:
- If `AI_PAYMENTS_ENABLED=false`: All itineraries return 402 (payment system disabled)
- If `STRIPE_ENABLED=false`: Checkout endpoint returns 503 (Stripe integration disabled)

**Emergency Kill Switch**:
```bash
# Disable all payments
php artisan config:set AI_PAYMENTS_ENABLED=false

# Disable Stripe only (keep entitlement checks)
php artisan config:set STRIPE_ENABLED=false
```

---

## 12. Open Questions

### Q1: Refund Handling
**Question**: Should refunds revoke entitlement credits?

**Options**:
1. **Revoke credits** - User loses credits when refunded
2. **Keep credits** - User keeps credits even after refund (generous policy)

**Recommendation**: Revoke credits (prevent abuse)

**Decision Needed**: User approval

---

### Q2: Credit Expiration
**Question**: Should credits expire after a certain period?

**Options**:
1. **No expiration** - Credits never expire (user-friendly)
2. **1-year expiration** - Credits expire after 1 year (encourages usage)
3. **6-month expiration** - Credits expire after 6 months (aggressive)

**Recommendation**: No expiration for MVP (simplicity)

**Decision Needed**: User approval

---

### Q3: Bundle Pricing
**Question**: Should we offer bundle discounts (e.g., "buy 5, get 1 free")?

**Options**:
1. **No bundles** - Single purchases only (MVP simplicity)
2. **Bundles** - Offer 5-pack, 10-pack with discounts

**Recommendation**: No bundles for MVP (add in Phase 2)

**Decision Needed**: User approval

---

## 13. Documentation Updates

### 13.1. docs/ai/AI_INDEX.md
**Section to add**: Payment & Entitlement System

**Content**:
- Payment flow overview
- Entitlement model (credit-based)
- API endpoints for checkout/entitlements
- PlanEnforcementService integration

### 13.2. docs/ai/implementation/STATE.md
**Updates**:
- Mark Task #3 Phase 1 as IN_PROGRESS
- Add Phase 1 deliverables checklist

### 13.3. docs/ai/implementation/TASKS.md
**Updates**:
- Mark Task #3 Phase 1 as IN_PROGRESS
- Add Phase 2 subtasks (implementation)

### 13.4. docs/ai/implementation/DECISIONS.md
**New ADR**: Entitlement Model Choice (Credit-Based vs Time-Based)

---

## 14. Phase 1 Deliverables Checklist

- [x] Define entitlement model (credit-based)
- [x] Define Stripe product mapping (3 products, 3 prices)
- [x] Define API endpoints (checkout, webhook, entitlements)
- [x] Define request/response contracts
- [x] Define OpenAPI schemas
- [x] Define security (auth, webhook verification)
- [x] Define PlanEnforcementService integration
- [x] Define database schema (user_entitlements, purchases)
- [x] Define webhook idempotency strategy
- [x] Define GDPR compliance approach
- [x] Define feature flag / kill switch
- [x] Document open questions
- [x] Create this proposal document

---

## 15. Next Steps (Awaiting Approval)

**User must approve**:
1. ✅ Entitlement model (credit-based)
2. ✅ API endpoint contracts
3. ✅ Database schema
4. ❓ Open questions (refunds, expiration, bundles)

**After approval**:
- Proceed to Phase 2 (implementation)
- Create migrations, models, controllers, services
- Write tests
- Add OpenAPI annotations
- Update documentation

---

**End of Phase 1 Proposal**

**Status**: AWAITING USER APPROVAL

**Next Action**: User reviews proposal and approves/requests changes


