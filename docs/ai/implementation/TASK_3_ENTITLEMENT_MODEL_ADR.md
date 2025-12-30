# ADR: Entitlement Model for Itinerary Purchases

**Date**: 2025-12-30
**Status**: Proposed (awaiting approval)
**Decision**: Credit-Based Entitlement Model
**Context**: Task #3 - Payments + Entitlements (Stripe)

---

## Context

We need to decide how to track user entitlements for purchased itinerary products. The system must:
1. Prevent revenue leakage (users shouldn't get unlimited itineraries for one payment)
2. Align with pricing model ($19/$39/$59 per itinerary, not per month)
3. Be simple to implement and understand
4. Enable future upsells (bundles, discounts)
5. Prevent abuse (sharing, bulk generation)

---

## Decision

**We will use a credit-based entitlement model.**

Each purchase grants the user **1 credit** for the specific itinerary type purchased:
- Purchase $19 → `itinerary_3day_credits += 1`
- Purchase $39 → `itinerary_7day_credits += 1`
- Purchase $59 → `itinerary_14day_credits += 1`

When a user generates an itinerary, the system:
1. Checks if `user->entitlements->{product_type}_credits > 0`
2. If yes: Allow generation, decrement credit by 1
3. If no: Return 402 Payment Required

---

## Alternatives Considered

### Option A: Time-Based Access ❌ REJECTED

**Model**: User purchases "itinerary access" valid for 30 days

**Example**:
- Purchase $39 for 7-day itinerary → `itinerary_access_until = now() + 30 days`
- Check: `if (now() < itinerary_access_until) { allow }`

**Pros**:
- ✅ Simple to implement (single timestamp field)
- ✅ Easy to understand ("access for 30 days")
- ✅ Familiar model (like subscriptions)

**Cons**:
- ❌ **Revenue leakage**: User can generate unlimited itineraries in 30 days
- ❌ **Encourages abuse**: User generates 100 itineraries, shares PDFs with friends
- ❌ **No usage control**: Can't limit volume
- ❌ **Misaligned pricing**: Pricing is per itinerary ($19/$39/$59), not per month
- ❌ **No upsell path**: Can't offer bundles ("buy 5, get 1 free")
- ❌ **Unclear value**: "What do I get for $39?" → "Access for 30 days" (vague)

**Why Rejected**: 
This model doesn't align with the business model. The pricing is **per itinerary**, not **per month**. Allowing unlimited itineraries for one payment would cause significant revenue leakage and encourage abuse.

---

### Option B: Credit-Based ✅ ACCEPTED

**Model**: User purchases credits for specific itinerary types

**Example**:
- Purchase $39 for 7-day itinerary → `itinerary_7day_credits += 1`
- Check: `if (itinerary_7day_credits > 0) { allow; decrement }`

**Pros**:
- ✅ **Aligns with pricing**: $39 = 1 itinerary (clear value proposition)
- ✅ **Prevents revenue leakage**: One purchase = one itinerary
- ✅ **Prevents abuse**: Can't generate unlimited itineraries
- ✅ **Enables bundles**: Future upsells ("buy 5, get 1 free" = 6 credits for $175)
- ✅ **Clear value**: "What do I get for $39?" → "One 7-day itinerary"
- ✅ **Flexible**: Can add more product types (accessibility reports, activity guides)

**Cons**:
- ⚠️ Slightly more complex (3 credit fields instead of 1 timestamp)
- ⚠️ Requires credit decrement logic (must be atomic to prevent race conditions)

**Why Accepted**:
This model aligns perfectly with the business model and prevents revenue leakage. The added complexity is minimal (3 integer fields) and the benefits far outweigh the costs.

---

### Option C: Hybrid (Time + Credit) ❌ REJECTED

**Model**: User purchases credits that expire after 30 days

**Example**:
- Purchase $39 → `itinerary_7day_credits += 1`, `credits_expire_at = now() + 30 days`
- Check: `if (itinerary_7day_credits > 0 && now() < credits_expire_at) { allow; decrement }`

**Pros**:
- ✅ Prevents hoarding (credits expire)
- ✅ Encourages usage (use it or lose it)

**Cons**:
- ❌ **User-hostile**: "I paid $39 and didn't use it in time, now it's gone"
- ❌ **Support burden**: Users will request refunds/extensions
- ❌ **Added complexity**: Need to track expiration per credit
- ❌ **Unclear value**: "What do I get for $39?" → "One itinerary, but only if you use it in 30 days"

**Why Rejected**:
This model is user-hostile and adds unnecessary complexity. If a user pays $39, they should get the itinerary whenever they want. Expiration creates support burden and bad user experience.

---

## Reasoning

### Business Alignment
The pricing model is **per itinerary** ($19/$39/$59), not **per month**. A credit-based model directly reflects this:
- $19 = 1 three-day itinerary
- $39 = 1 seven-day itinerary
- $59 = 1 fourteen-day itinerary

This is clear, transparent, and easy to communicate to users.

### Revenue Protection
Time-based access would allow a user to:
1. Purchase $39 for 7-day itinerary access
2. Generate 100 itineraries in 30 days
3. Share PDFs with friends/family
4. Result: $39 revenue, but 100 itineraries delivered (99% revenue leakage)

Credit-based prevents this:
1. Purchase $39 for 7-day itinerary
2. Generate 1 itinerary
3. Credits depleted, must purchase again
4. Result: $39 revenue, 1 itinerary delivered (0% revenue leakage)

### Future Flexibility
Credit-based enables future upsells:
- **Bundles**: "Buy 5 itineraries, get 1 free" = 6 credits for $175 (vs $195)
- **Gifting**: "Buy credits as a gift" (transfer credits to another user)
- **Promotions**: "First-time users get 1 free credit"
- **Loyalty**: "Earn 1 free credit after 10 purchases"

Time-based doesn't enable these upsells.

### User Experience
Credit-based is clearer:
- ✅ "You have 1 seven-day itinerary credit"
- ✅ "Purchase 1 more to generate another itinerary"

Time-based is vague:
- ⚠️ "You have access until Jan 30, 2026"
- ⚠️ "What does 'access' mean? How many itineraries can I generate?"

---

## Implementation Details

### Database Schema
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

### Credit Grant (Webhook)
```php
public function grantCredits(User $user, string $productType, int $credits = 1): void
{
    $entitlement = $user->entitlements()->firstOrCreate(['user_id' => $user->id]);
    
    $creditField = $productType . '_credits'; // e.g., 'itinerary_7day_credits'
    $entitlement->increment($creditField, $credits);
}
```

### Credit Check (PlanEnforcementService)
```php
public function hasEntitlement(User $user, string $productType): bool
{
    $entitlement = $user->entitlements;
    if (!$entitlement) {
        return false;
    }
    
    $creditField = $productType . '_credits';
    return $entitlement->$creditField > 0;
}
```

### Credit Decrement (ItineraryController)
```php
public function decrementCredits(User $user, string $productType): void
{
    $entitlement = $user->entitlements;
    $creditField = $productType . '_credits';
    
    // Atomic decrement (prevents race conditions)
    $entitlement->decrement($creditField, 1);
    
    // Ensure credits don't go negative
    if ($entitlement->$creditField < 0) {
        $entitlement->update([$creditField => 0]);
        throw new InsufficientCreditsException();
    }
}
```

---

## Impact

### On Codebase
- ✅ **Minimal complexity**: 3 integer fields in `user_entitlements` table
- ✅ **Simple logic**: Increment on purchase, decrement on generation
- ✅ **Atomic operations**: Use database `increment()`/`decrement()` to prevent race conditions

### On Users
- ✅ **Clear value**: "Buy 1 itinerary for $39"
- ✅ **No surprises**: Credits don't expire (unless we decide otherwise in future)
- ✅ **Transparent**: User can see credit balance via `GET /api/v1/me/entitlements`

### On Business
- ✅ **Revenue protection**: One purchase = one itinerary (no leakage)
- ✅ **Upsell opportunities**: Bundles, promotions, loyalty programs
- ✅ **Scalable**: Can add more product types (reports, guides) using same model

---

## Risks and Mitigations

### Risk 1: Race Conditions
**Problem**: Two concurrent requests could both check credits > 0, both decrement, resulting in negative credits.

**Mitigation**: Use atomic database operations (`decrement()`) and check for negative values after decrement.

```php
DB::transaction(function () use ($user, $productType) {
    $entitlement = $user->entitlements()->lockForUpdate()->first();
    $creditField = $productType . '_credits';
    
    if ($entitlement->$creditField <= 0) {
        throw new InsufficientCreditsException();
    }
    
    $entitlement->decrement($creditField, 1);
});
```

### Risk 2: Credit Hoarding
**Problem**: Users might purchase many credits and never use them.

**Mitigation**: Not a problem for business (we already have the revenue). If needed, add expiration in Phase 2.

### Risk 3: Refund Abuse
**Problem**: User generates itinerary, downloads PDF, then requests refund.

**Mitigation**: 
1. Revoke credits on refund (handled in webhook)
2. Track download count (prevent re-download after refund)
3. 30-day refund window (standard)

---

## Related Decisions

- **Task #2 Phase 1**: Itinerary endpoint design (dedicated endpoint, not chat extension)
- **Task #3 Phase 1**: Payment integration (Stripe Checkout, webhook-based)

---

## Approval Status

**Status**: Proposed (awaiting user approval)

**Approvers**:
- [ ] Product Owner (user)
- [ ] Technical Lead (AI agent)

**Next Steps**:
1. User reviews and approves this ADR
2. Proceed to Phase 2 implementation
3. Update `docs/ai/implementation/DECISIONS.md` with this ADR

---

**End of ADR**

