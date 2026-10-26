# Google OAuth Setup Guide

**Status**: Phase 1 - Contract Definition Complete  
**Date**: 2025-12-31  
**Implementation**: Phase 2 (Pending)

---

## Overview

This document describes the Google OAuth 2.0 integration for Beachrex authentication. The implementation uses **Authorization Code Flow** with **Bearer Token** authentication (Sanctum Personal Access Tokens).

---

## Required Environment Variables

### Backend (.env)

Add these variables to your Laravel `.env` file:

```bash
# Google OAuth Configuration
GOOGLE_CLIENT_ID=your-google-client-id.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=your-google-client-secret
GOOGLE_REDIRECT_URI=https://dev-api.beachrex.com/api/v1/auth/google/callback

# Frontend URL (for redirects after auth)
FRONTEND_URL=http://localhost:3000

# Optional: Enable/disable Google OAuth
GOOGLE_AUTH_ENABLED=true
```

### Frontend (.env.local)

```bash
# API endpoint (already configured)
NEXT_PUBLIC_API_ENDPOINT=https://dev-api.beachrex.com

# Base URL (already configured)
NEXT_PUBLIC_BASE_URL=http://localhost:3000
```

---

## Google Cloud Console Setup

### 1. Create OAuth 2.0 Credentials

1. Go to [Google Cloud Console](https://console.cloud.google.com/)
2. Select your project (or create a new one)
3. Navigate to **APIs & Services** → **Credentials**
4. Click **Create Credentials** → **OAuth client ID**
5. Select **Web application**
6. Configure:
   - **Name**: Beachrex (Dev/Staging/Production)
   - **Authorized JavaScript origins**:
     - `http://localhost:3000` (local dev)
     - `https://dev.beachrex.com` (dev)
     - `https://beachrex.com` (production)
   - **Authorized redirect URIs**:
     - `https://dev-api.beachrex.com/api/v1/auth/google/callback` (dev)
     - `https://api.beachrex.com/api/v1/auth/google/callback` (production)

7. Click **Create**
8. Copy **Client ID** and **Client Secret** to `.env`

### 2. Enable Required APIs

Enable these APIs in Google Cloud Console:
- **Google+ API** (for user profile)
- **People API** (for user info)

---

## Authentication Flow

### 1. Frontend Initiates Login

```typescript
// User clicks "Continue with Google" button
const response = await fetch('https://dev-api.beachrex.com/api/v1/auth/google/redirect');
const { url } = await response.json();

// Redirect user to Google OAuth page
window.location.href = url;
```

### 2. User Authorizes on Google

- User sees Google consent screen
- User grants permissions (email, profile)
- Google redirects to backend callback URL with `code` and `state`

### 3. Backend Handles Callback

```
GET /api/v1/auth/google/callback?code=...&state=...
```

Backend:
1. Validates `state` parameter (CSRF protection)
2. Exchanges `code` for Google access token
3. Fetches user profile from Google
4. Creates or updates user in database
5. Issues Sanctum Bearer token
6. Returns JSON response:

```json
{
  "token": "1|xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx63440ff9",
  "token_type": "Bearer",
  "expires_in": 0,
  "user": {
    "id": 1,
    "name": "John Doe",
    "email": "john.doe@gmail.com",
    "avatar_url": "https://lh3.googleusercontent.com/a/...",
    "locale": "en",
    "created_at": "2025-12-31T10:30:00.000000Z"
  }
}
```

### 4. Frontend Stores Token

```typescript
// Store token in memory or localStorage
localStorage.setItem('auth_token', response.token);

// Configure API client to use token
client.setConfig({
  headers: {
    Authorization: `Bearer ${response.token}`
  }
});

// Redirect to app
router.push('/ai/chat');
```

---

## Local Development Flow

### 1. Start Backend

```bash
cd /Users/davidtacer/Documents/Development/Beachrex/Api
./vendor/bin/sail up -d
```

### 2. Start Frontend

```bash
cd /Users/davidtacer/Documents/Development/Beachrex/Front
yarn dev
```

### 3. Test Login Flow

1. Visit: `http://localhost:3000/ai/chat`
2. Click "Continue with Google"
3. Frontend calls: `GET https://dev-api.beachrex.com/api/v1/auth/google/redirect`
4. Backend returns: `{ "url": "https://accounts.google.com/..." }`
5. Frontend redirects to Google
6. User authorizes
7. Google redirects to: `https://dev-api.beachrex.com/api/v1/auth/google/callback?code=...&state=...`
8. Backend returns token
9. Frontend stores token and redirects to app

---

## Production Checklist

### Backend

- [ ] Set `GOOGLE_CLIENT_ID` in production `.env`
- [ ] Set `GOOGLE_CLIENT_SECRET` in production `.env`
- [ ] Set `GOOGLE_REDIRECT_URI=https://api.beachrex.com/api/v1/auth/google/callback`
- [ ] Set `FRONTEND_URL=https://beachrex.com`
- [ ] Verify CORS allows `Authorization` header
- [ ] Add rate limiting to auth endpoints (already configured)
- [ ] Test callback with production Google credentials

### Frontend

- [ ] Set `NEXT_PUBLIC_API_ENDPOINT=https://api.beachrex.com`
- [ ] Set `NEXT_PUBLIC_BASE_URL=https://beachrex.com`
- [ ] Remove internal Bearer token approach (replace with Google OAuth)
- [ ] Test login flow end-to-end
- [ ] Verify token storage (secure, HttpOnly if possible)
- [ ] Test logout flow (token revocation)

### Google Cloud Console

- [ ] Add production redirect URI: `https://api.beachrex.com/api/v1/auth/google/callback`
- [ ] Add production JavaScript origin: `https://beachrex.com`
- [ ] Verify OAuth consent screen is configured
- [ ] Set app to "Production" status (not "Testing")
- [ ] Add privacy policy URL
- [ ] Add terms of service URL

---

## Security Notes

### CSRF Protection

- Backend generates random `state` parameter
- State is stored in session/cache with expiration (5 minutes)
- Callback validates state matches stored value
- Invalid state returns 401 error

### Token Security

- Sanctum Personal Access Tokens are used (not JWT)
- Tokens do not expire by default (`expires_in: 0`)
- Tokens can be revoked via `/api/v1/auth/logout`
- Tokens are stored in `personal_access_tokens` table
- Frontend should store token securely (avoid XSS)

### Rate Limiting

- Auth endpoints are rate limited (60 requests/hour per IP)
- Prevents brute force attacks
- Returns 429 with `retry_after` header

---

## Troubleshooting

### "Invalid state parameter"

- State expired (5 minute timeout)
- User took too long to authorize
- **Solution**: Try login again

### "Invalid authorization code"

- Code already used (codes are single-use)
- Code expired (10 minute timeout)
- **Solution**: Try login again

### "Google API error"

- Google OAuth credentials invalid
- Redirect URI mismatch
- **Solution**: Check Google Cloud Console configuration

### "User creation failed"

- Database error
- Email already exists (edge case)
- **Solution**: Check backend logs

---

## API Endpoints Reference

| Endpoint | Method | Auth | Description |
|----------|--------|------|-------------|
| `/api/v1/auth/google/redirect` | GET | No | Get Google OAuth URL |
| `/api/v1/auth/google/callback` | GET | No | Handle Google callback |
| `/api/v1/auth/logout` | POST | Yes | Revoke token |
| `/api/v1/me` | GET | Yes | Get current user |
| `/api/v1/me/entitlements` | GET | Yes | Get user credits |
| `/api/v1/payments/stripe/checkout` | POST | Yes | Create checkout session |
| `/api/v1/ai/itineraries` | POST | Yes* | Generate itinerary |

*Itinerary endpoint requires auth for multi-day (paid) itineraries.

---

## Next Steps (Phase 2)

1. Install Laravel Socialite package
2. Implement controller logic
3. Add database migration for Google OAuth fields
4. Write feature tests
5. Generate OpenAPI docs
6. Update frontend to use new auth flow
7. Test end-to-end

---

**Status**: ✅ Phase 1 Complete - Awaiting approval for Phase 2 implementation

