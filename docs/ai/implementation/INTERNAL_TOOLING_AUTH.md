# Internal Tooling Authentication (Bearer Token)

**Status**: IMPLEMENTED ✅  
**Date**: 2025-12-31  
**Scope**: DEV/STAGING ONLY  

---

## Overview

Internal pages (`/internal/*`) require authentication to call protected API endpoints like:
- `GET /api/v1/me/entitlements` (view credit balances)
- `POST /api/v1/payments/stripe/checkout` (create checkout session)

Since the frontend (`http://localhost:3000`) and backend (`https://dev-api.beachrex.com`) are on different domains, cookie-based Sanctum SPA authentication won't work out-of-the-box. Instead, we use **Bearer token authentication** for internal tooling.

---

## How It Works

### 1. Token Source

Token is stored in frontend environment variable:
```bash
# Front/.env.local
NEXT_PUBLIC_INTERNAL_API_TOKEN=1|abc123def456ghi789...
```

**IMPORTANT**:
- ✅ Use `.env.local` (gitignored, never committed)
- ❌ Never use `.env` or `.env.development` (tracked by git)
- ❌ Never commit tokens to version control

### 2. API Client Wrapper

File: `Front/src/lib/api-client-auth.ts`

Provides:
- `createAuthenticatedClient()` - Creates API client with Bearer token
- `hasInternalApiToken()` - Checks if token is configured
- `getAuthStatus()` - Returns human-readable auth status (for debugging)

### 3. Internal Pages

All internal pages use the authenticated client:

**Example** (`/internal/ai-entitlements`):
```typescript
import { createAuthenticatedClient } from '@/lib/api-client-auth';

const client = createAuthenticatedClient();
const response = await e3A9C3C756B5255539Aa1Ab9F3A2A6A8({ client });
```

---

## Setup Instructions

### Step 1: Create Laravel Sanctum Personal Access Token

**Option A: Using Laravel Tinker (Recommended)**

```bash
# SSH into backend or use Laravel Sail
cd Api
./vendor/bin/sail artisan tinker

# In tinker console:
$user = User::find(1);  # Replace 1 with your user ID
$token = $user->createToken('internal-tooling')->plainTextToken;
echo $token;
# Output: 1|abc123def456ghi789...
```

**Option B: Using Artisan Command (if available)**

```bash
php artisan sanctum:token-create 1 internal-tooling
```

**Option C: Using Database Seeder (for testing)**

Create a seeder that generates a token for a test user.

### Step 2: Add Token to Frontend Environment

```bash
# Front/.env.local
NEXT_PUBLIC_INTERNAL_API_TOKEN=1|abc123def456ghi789...
```

**IMPORTANT**: Replace `1|abc123...` with the actual token from Step 1.

### Step 3: Restart Next.js Dev Server

```bash
cd Front
yarn dev
```

Environment variables are only loaded on server start.

### Step 4: Verify Authentication

Visit `/internal/ai-entitlements`:
- ✅ If token is valid: Page loads credit balances
- ❌ If token is missing: Yellow warning box with setup instructions
- ❌ If token is invalid: 401 error displayed

---

## Backend Verification

### Confirm Sanctum Configuration

**File**: `config/sanctum.php`

Ensure `stateful` domains include your frontend domain (for SPA auth, if needed later):
```php
'stateful' => explode(',', env('SANCTUM_STATEFUL_DOMAINS', sprintf(
    '%s%s',
    'localhost,localhost:3000,127.0.0.1,127.0.0.1:8000,::1',
    Sanctum::currentApplicationUrlWithPort()
))),
```

**Note**: For Bearer token auth, this is NOT required. Tokens work cross-domain.

### Confirm Routes Use `auth:sanctum` Middleware

**File**: `routes/api.php`

```php
// Checkout endpoint (requires authentication)
Route::middleware('auth:sanctum')->post('/checkout', [CheckoutController::class, 'create']);

// Entitlements endpoint (requires authentication)
Route::middleware('auth:sanctum')->get('/me/entitlements', [EntitlementController::class, 'index']);
```

### Test Token Manually

```bash
# Replace TOKEN with your actual token
curl -H "Authorization: Bearer 1|abc123..." \
     https://dev-api.beachrex.com/api/v1/me/entitlements
```

**Expected Response** (200 OK):
```json
{
  "entitlements": {
    "itinerary_3day_credits": 0,
    "itinerary_7day_credits": 0,
    "itinerary_14day_credits": 0
  },
  "purchases": []
}
```

**Error Response** (401 Unauthorized):
```json
{
  "message": "Unauthenticated."
}
```

---

## Security Considerations

### ✅ Safe Practices

1. **Environment Variables**: Token stored in `.env.local` (gitignored)
2. **Production Guard**: Internal pages return 404 in production
3. **Token Masking**: Debug output shows only first 10 characters
4. **HTTPS Only**: Backend API uses HTTPS (dev-api.beachrex.com)
5. **Token Rotation**: Revoke and regenerate tokens periodically

### ❌ Unsafe Practices (DO NOT DO)

1. **Never commit tokens to git** (use `.env.local`, not `.env`)
2. **Never use production tokens in dev** (create separate tokens)
3. **Never share tokens** (each developer should create their own)
4. **Never log full tokens** (mask sensitive data)
5. **Never use internal pages in production** (production guard prevents this)

### Token Revocation

If a token is compromised:

```bash
# Laravel Tinker
$user = User::find(1);
$user->tokens()->where('name', 'internal-tooling')->delete();
```

Or delete from database:
```sql
DELETE FROM personal_access_tokens WHERE name = 'internal-tooling';
```

---

## Troubleshooting

### Problem: 401 Unauthenticated

**Cause**: Token is missing, invalid, or expired.

**Solution**:
1. Check `.env.local` has `NEXT_PUBLIC_INTERNAL_API_TOKEN`
2. Verify token format: `1|abc123...` (starts with `1|`)
3. Test token manually with curl (see "Test Token Manually" above)
4. Regenerate token if invalid

### Problem: Token Not Found in Environment

**Cause**: Environment variable not loaded.

**Solution**:
1. Ensure file is named `.env.local` (not `.env.development`)
2. Restart Next.js dev server (`yarn dev`)
3. Check browser console for warning: `NEXT_PUBLIC_INTERNAL_API_TOKEN not set`

### Problem: CORS Error

**Cause**: Backend not configured for cross-origin requests.

**Solution**:
Bearer tokens should work cross-domain. If CORS error persists:
1. Check `config/cors.php` allows `Authorization` header
2. Verify backend returns `Access-Control-Allow-Headers: Authorization`

### Problem: Token Works in curl but not in browser

**Cause**: Browser caching or client configuration issue.

**Solution**:
1. Hard refresh browser (Cmd+Shift+R / Ctrl+Shift+R)
2. Clear browser cache
3. Check Network tab: Verify `Authorization: Bearer ...` header is sent
4. Verify `createAuthenticatedClient()` is called (not default client)

---

## Files Modified

### Frontend (3 files)

1. **`Front/src/lib/api-client-auth.ts`** (NEW)
   - Authenticated client wrapper
   - Token management functions
   - Auth status helpers

2. **`Front/src/app/internal/ai-entitlements/page.tsx`** (MODIFIED)
   - Uses `createAuthenticatedClient()`
   - Shows token configuration warning if missing
   - Displays auth status

3. **`Front/src/app/internal/ai-purchase-itinerary/page.tsx`** (MODIFIED)
   - Uses `createAuthenticatedClient()`
   - Shows token configuration warning if missing
   - Displays auth status

4. **`Front/src/app/internal/ai-playground/page.tsx`** (MODIFIED)
   - Uses `createAuthenticatedClient()` for checkout flow
   - Existing anonymous chat flow unchanged

### Backend (0 files)

No backend changes required. Sanctum already supports Bearer tokens.

---

## Testing Checklist

### Local Testing (Frontend + Backend)

- [ ] Create Sanctum token via tinker
- [ ] Add token to `Front/.env.local`
- [ ] Restart Next.js dev server
- [ ] Visit `/internal/ai-entitlements`
- [ ] Verify credit balances load (no 401 error)
- [ ] Visit `/internal/ai-purchase-itinerary`
- [ ] Click "Buy 3-Day" button
- [ ] Verify redirect to Stripe Checkout (no 401 error)
- [ ] Complete test payment (card: 4242 4242 4242 4242)
- [ ] Return to `/internal/ai-entitlements`
- [ ] Verify credits granted (itinerary_3day_credits = 1)

### Error Handling

- [ ] Remove token from `.env.local`
- [ ] Restart dev server
- [ ] Visit `/internal/ai-entitlements`
- [ ] Verify yellow warning box appears
- [ ] Verify setup instructions displayed
- [ ] Add invalid token (e.g., `invalid-token`)
- [ ] Restart dev server
- [ ] Visit `/internal/ai-entitlements`
- [ ] Verify 401 error displayed

### Production Guard

- [ ] Set `NEXT_PUBLIC_BASE_URL=https://beachrex.com`
- [ ] Restart dev server
- [ ] Visit `/internal/ai-entitlements`
- [ ] Verify 404 Not Found (production guard active)

---

## Future Improvements (Phase 3)

### Option 1: UI-Based Token Input (No Environment Variable)

Instead of `.env.local`, allow users to paste token in UI:

```typescript
// Store token in localStorage
localStorage.setItem('internal-api-token', token);

// Retrieve token
const token = localStorage.getItem('internal-api-token');
```

**Pros**: No need to restart dev server, easier for non-technical users  
**Cons**: Token visible in browser DevTools, less secure

### Option 2: OAuth Flow (Production-Ready)

For public-facing pages (not internal tooling):
- Implement full OAuth2 flow
- Use Laravel Passport or Sanctum SPA auth
- Cookie-based sessions with CSRF protection

**Pros**: Production-ready, secure, standard practice  
**Cons**: More complex, requires CORS configuration

---

## References

- **Laravel Sanctum Docs**: https://laravel.com/docs/11.x/sanctum
- **Personal Access Tokens**: https://laravel.com/docs/11.x/sanctum#issuing-api-tokens
- **OpenAPI Client Docs**: https://github.com/hey-api/openapi-ts

---

**End of INTERNAL_TOOLING_AUTH.md**
