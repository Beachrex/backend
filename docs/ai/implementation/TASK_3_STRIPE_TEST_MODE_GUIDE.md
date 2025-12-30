# Task #3 Phase 2.5: Stripe Test Mode Verification Guide

## Overview

This guide provides step-by-step instructions for manually testing the Stripe payment integration in test mode. All tests use Stripe's test mode API keys and test card numbers - **no real money is involved**.

## Prerequisites

### 1. Stripe Account Setup

1. Create a free Stripe account at https://stripe.com
2. Navigate to **Developers → API keys**
3. Copy your **Test mode** keys:
   - Publishable key: `pk_test_...`
   - Secret key: `sk_test_...`

### 2. Environment Configuration

Add the following to your `.env` file:

```bash
# AI Payments
AI_PAYMENTS_ENABLED=true

# Stripe Configuration
STRIPE_ENABLED=true
STRIPE_SECRET_KEY=sk_test_YOUR_SECRET_KEY_HERE
STRIPE_WEBHOOK_SECRET=whsec_YOUR_WEBHOOK_SECRET_HERE

# Stripe Products (Test Mode Price IDs)
STRIPE_PRODUCT_ITINERARY_3DAY_PRICE_ID=price_YOUR_3DAY_PRICE_ID
STRIPE_PRODUCT_ITINERARY_7DAY_PRICE_ID=price_YOUR_7DAY_PRICE_ID
STRIPE_PRODUCT_ITINERARY_14DAY_PRICE_ID=price_YOUR_14DAY_PRICE_ID

# Frontend URLs
FRONTEND_URL=http://localhost:3000
```

### 3. Create Stripe Products

In Stripe Dashboard (Test Mode):

1. Navigate to **Products → Add product**
2. Create 3 products:

**Product 1: 3-Day Beach Itinerary**
- Name: `3-Day Beach Itinerary`
- Description: `AI-generated 3-day beach itinerary`
- Pricing: One-time payment, $19.00 USD
- Copy the **Price ID** (starts with `price_`) to `STRIPE_PRODUCT_ITINERARY_3DAY_PRICE_ID`

**Product 2: 7-Day Beach Itinerary**
- Name: `7-Day Beach Itinerary`
- Description: `AI-generated 7-day beach itinerary`
- Pricing: One-time payment, $39.00 USD
- Copy the **Price ID** to `STRIPE_PRODUCT_ITINERARY_7DAY_PRICE_ID`

**Product 3: 14-Day Beach Itinerary**
- Name: `14-Day Beach Itinerary`
- Description: `AI-generated 14-day beach itinerary`
- Pricing: One-time payment, $59.00 USD
- Copy the **Price ID** to `STRIPE_PRODUCT_ITINERARY_14DAY_PRICE_ID`

### 4. Install Stripe CLI

The Stripe CLI is required for webhook testing.

**macOS:**
```bash
brew install stripe/stripe-cli/stripe
```

**Linux:**
```bash
wget https://github.com/stripe/stripe-cli/releases/latest/download/stripe_linux_amd64.tar.gz
tar -xvf stripe_linux_amd64.tar.gz
sudo mv stripe /usr/local/bin/
```

**Windows:**
Download from https://github.com/stripe/stripe-cli/releases/latest

**Verify installation:**
```bash
stripe --version
```

**Login to Stripe:**
```bash
stripe login
```

## Test Scenarios

### Scenario 1: Create Checkout Session

**Objective:** Verify that authenticated users can create a Stripe Checkout session.

**Steps:**

1. Start the Laravel API:
   ```bash
   ./vendor/bin/sail up -d
   ```

2. Create a test user (if not exists):
   ```bash
   ./vendor/bin/sail artisan tinker
   ```
   ```php
   $user = \App\Models\User::factory()->create(['email' => 'test@example.com']);
   $token = $user->createToken('test-token')->plainTextToken;
   echo "Token: " . $token . "\n";
   exit;
   ```

3. Create a checkout session:
   ```bash
   curl -X POST http://localhost/api/v1/payments/stripe/checkout \
     -H "Authorization: Bearer YOUR_TOKEN_HERE" \
     -H "Content-Type: application/json" \
     -d '{
       "product_type": "itinerary_3day",
       "success_url": "http://localhost:3000/payment/success",
       "cancel_url": "http://localhost:3000/payment/cancel"
     }'
   ```

**Expected Response:**
```json
{
  "session_id": "cs_test_...",
  "url": "https://checkout.stripe.com/c/pay/cs_test_..."
}
```

4. Open the `url` in a browser
5. Use Stripe test card: `4242 4242 4242 4242`
   - Expiry: Any future date (e.g., `12/34`)
   - CVC: Any 3 digits (e.g., `123`)
   - ZIP: Any 5 digits (e.g., `12345`)

6. Complete the payment
7. You should be redirected to `success_url`

**Expected Database State:**
```bash
./vendor/bin/sail artisan tinker
```
```php
$user = \App\Models\User::where('email', 'test@example.com')->first();
$user->entitlements; // Should show 1 credit for itinerary_3day_credits
$user->purchases; // Should show 1 purchase record
exit;
```

### Scenario 2: Webhook Event Processing

**Objective:** Verify that webhook events are processed correctly and credits are granted.

**Steps:**

1. Start webhook forwarding (in a separate terminal):
   ```bash
   stripe listen --forward-to localhost/api/v1/payments/stripe/webhook
   ```

2. Copy the webhook signing secret (starts with `whsec_`) and update `.env`:
   ```bash
   STRIPE_WEBHOOK_SECRET=whsec_YOUR_WEBHOOK_SECRET_HERE
   ```

3. Restart the API to load the new secret:
   ```bash
   ./vendor/bin/sail artisan config:clear
   ```

4. Create a checkout session (repeat Scenario 1 steps 3-6)

5. Monitor the webhook forwarding terminal - you should see:
   ```
   --> checkout.session.completed [evt_...]
   <-- [200] POST http://localhost/api/v1/payments/stripe/webhook [evt_...]
   ```

6. Check the Laravel logs:
   ```bash
   ./vendor/bin/sail artisan tail
   ```

   You should see:
   ```
   [INFO] Credits granted: user_id=X, product_type=itinerary_3day, credits=1
   ```

**Expected Database State:**
```bash
./vendor/bin/sail artisan tinker
```
```php
$user = \App\Models\User::find(YOUR_USER_ID);
$user->entitlements->itinerary_3day_credits; // Should be 1
$user->purchases()->where('status', 'completed')->count(); // Should be 1
exit;
```

### Scenario 3: Idempotency

**Objective:** Verify that duplicate webhook events do not grant credits twice.

**Steps:**

1. Trigger a test webhook event:
   ```bash
   stripe trigger checkout.session.completed
   ```

2. Note the event ID from the output (e.g., `evt_test_...`)

3. Check the database:
   ```bash
   ./vendor/bin/sail artisan tinker
   ```
   ```php
   \App\Models\Purchase::where('stripe_event_id', 'evt_test_...')->count(); // Should be 1
   exit;
   ```

4. Manually replay the same event:
   ```bash
   stripe events resend evt_test_...
   ```

5. Check the database again:
   ```bash
   ./vendor/bin/sail artisan tinker
   ```
   ```php
   \App\Models\Purchase::where('stripe_event_id', 'evt_test_...')->count(); // Should still be 1
   exit;
   ```

**Expected Result:** Credits are only granted once, even if the webhook is received multiple times.

### Scenario 4: Refund Processing

**Objective:** Verify that refunds revoke credits correctly.

**Steps:**

1. Complete a purchase (Scenario 1)

2. Find the payment intent ID:
   ```bash
   ./vendor/bin/sail artisan tinker
   ```
   ```php
   $purchase = \App\Models\Purchase::latest()->first();
   echo "Payment Intent: " . $purchase->stripe_payment_intent_id . "\n";
   exit;
   ```

3. Issue a refund in Stripe Dashboard:
   - Navigate to **Payments**
   - Find the payment
   - Click **Refund payment**
   - Enter full amount
   - Click **Refund**

4. Check the webhook forwarding terminal - you should see:
   ```
   --> charge.refunded [evt_...]
   <-- [200] POST http://localhost/api/v1/payments/stripe/webhook [evt_...]
   ```

5. Check the database:
   ```bash
   ./vendor/bin/sail artisan tinker
   ```
   ```php
   $user = \App\Models\User::find(YOUR_USER_ID);
   $user->entitlements->itinerary_3day_credits; // Should be 0 (or negative if already consumed)
   $user->purchases()->where('status', 'refunded')->count(); // Should be 1
   exit;
   ```

**Expected Result:** Credits are revoked. If the user already consumed the credit, the balance goes negative.

### Scenario 5: Query Entitlements

**Objective:** Verify that users can query their credit balances and purchase history.

**Steps:**

1. Query entitlements:
   ```bash
   curl -X GET http://localhost/api/v1/me/entitlements \
     -H "Authorization: Bearer YOUR_TOKEN_HERE"
   ```

**Expected Response:**
```json
{
  "credits": {
    "itinerary_3day_credits": 1,
    "itinerary_7day_credits": 0,
    "itinerary_14day_credits": 0
  },
  "purchases": [
    {
      "id": 1,
      "product_type": "itinerary_3day",
      "amount": 19.00,
      "currency": "usd",
      "status": "completed",
      "purchased_at": "2025-12-30T12:00:00.000000Z"
    }
  ]
}
```

## Troubleshooting

### Webhook Not Received

**Problem:** Webhook events are not being received by the API.

**Solution:**
1. Verify Stripe CLI is running: `stripe listen --forward-to localhost/api/v1/payments/stripe/webhook`
2. Check that `STRIPE_WEBHOOK_SECRET` in `.env` matches the secret from Stripe CLI
3. Restart the API: `./vendor/bin/sail artisan config:clear`

### Invalid Signature Error

**Problem:** Webhook returns 400 with "Invalid signature" error.

**Solution:**
1. Verify `STRIPE_WEBHOOK_SECRET` is correct
2. Ensure you're using the webhook secret from Stripe CLI (starts with `whsec_`), not the API secret key
3. Restart the API after updating `.env`

### Credits Not Granted

**Problem:** Payment succeeds but credits are not granted.

**Solution:**
1. Check Laravel logs: `./vendor/bin/sail artisan tail`
2. Verify webhook was received: Check Stripe CLI output
3. Check database: `\App\Models\Purchase::latest()->first()`
4. Verify product type mapping in `config/ai.php`

## Production Deployment

When deploying to production:

1. **Switch to Live Mode:**
   - Use live API keys (`sk_live_...`, `pk_live_...`)
   - Create products in live mode
   - Update `.env` with live price IDs

2. **Configure Webhook Endpoint:**
   - In Stripe Dashboard (Live Mode), navigate to **Developers → Webhooks**
   - Click **Add endpoint**
   - URL: `https://yourdomain.com/api/v1/payments/stripe/webhook`
   - Events to send: `checkout.session.completed`, `checkout.session.expired`, `charge.refunded`
   - Copy the **Signing secret** to `STRIPE_WEBHOOK_SECRET`

3. **Test in Production:**
   - Use real card for a small test purchase
   - Verify credits are granted
   - Issue a refund and verify credits are revoked

## Summary

This guide covers all critical payment flows:
- ✅ Checkout session creation
- ✅ Webhook event processing
- ✅ Credit granting
- ✅ Idempotency
- ✅ Refund processing
- ✅ Entitlement queries

All tests use Stripe test mode - no real money is involved.

