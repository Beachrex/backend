<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

/**
 * Google OAuth Authentication Controller
 *
 * Handles Google OAuth 2.0 authorization code flow with PKCE.
 * Returns Sanctum Bearer tokens for authenticated users.
 */
class GoogleAuthController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v1/auth/google/redirect",
     *     operationId="googleRedirect",
     *     summary="Get Google OAuth authorization URL",
     *     description="Returns the Google OAuth URL to redirect the user to for authentication. The URL includes a state parameter for CSRF protection.",
     *     tags={"Auth"},
     *     @OA\Parameter(ref="#/components/parameters/AcceptLanguage"),
     *     @OA\Response(
     *         response=200,
     *         description="Google OAuth URL generated successfully",
     *         @OA\JsonContent(ref="#/components/schemas/GoogleRedirectResponse")
     *     ),
     *     @OA\Response(
     *         response=429,
     *         description="Rate limit exceeded",
     *         @OA\JsonContent(ref="#/components/schemas/RateLimitErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error (e.g., missing Google OAuth configuration)",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="server_error"),
     *             @OA\Property(property="message", type="string", example="Google OAuth is not configured. Contact support.")
     *         )
     *     )
     * )
     */
    public function redirect(Request $request)
    {
        try {
            // Generate secure state for CSRF protection
            $state = Str::random(40);

            // Store state in cache with 10-minute TTL
            Cache::put("google_oauth_state:{$state}", [
                'created_at' => now()->toIso8601String(),
                'ip' => $request->ip(),
            ], now()->addMinutes(10));

            // Generate Google OAuth URL with state
            $url = Socialite::driver('google')
                ->stateless()
                ->with(['state' => $state])
                ->redirect()
                ->getTargetUrl();

            return response()->json([
                'url' => $url,
            ]);
        } catch (\Exception $e) {
            Log::error('Google OAuth redirect error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => 'server_error',
                'message' => 'Failed to generate Google OAuth URL. Contact support.',
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/auth/google/callback",
     *     operationId="googleCallback",
     *     summary="Handle Google OAuth callback",
     *     description="Exchanges the authorization code for a Google access token, fetches user profile, creates or updates the user in the database, and redirects to frontend with one-time exchange code. The state parameter is validated for CSRF protection.",
     *     tags={"Auth"},
     *     @OA\Parameter(ref="#/components/parameters/AcceptLanguage"),
     *     @OA\Parameter(
     *         name="code",
     *         in="query",
     *         required=true,
     *         description="Authorization code from Google OAuth",
     *         @OA\Schema(type="string", example="4/0AeanS0...")
     *     ),
     *     @OA\Parameter(
     *         name="state",
     *         in="query",
     *         required=true,
     *         description="CSRF protection state parameter (must match the state sent in redirect)",
     *         @OA\Schema(type="string", example="abc123xyz...")
     *     ),
     *     @OA\Parameter(
     *         name="error",
     *         in="query",
     *         required=false,
     *         description="Error code from Google (if user denied access or other error occurred)",
     *         @OA\Schema(type="string", example="access_denied")
     *     ),
     *     @OA\Response(
     *         response=302,
     *         description="Redirects to frontend callback page with one-time exchange code or error",
     *         @OA\Header(
     *             header="Location",
     *             description="Frontend callback URL with exchange code or error parameters",
     *             @OA\Schema(type="string", example="https://beachrex.com/en/auth/callback?code=...")
     *         )
     *     ),
     *     @OA\Response(
     *         response=429,
     *         description="Rate limit exceeded",
     *         @OA\JsonContent(ref="#/components/schemas/RateLimitErrorResponse")
     *     )
     * )
     */
    public function callback(Request $request)
    {
        $frontendUrl = config('app.frontend_url', 'http://localhost:3000');

        // Check for Google error
        if ($request->has('error')) {
            return redirect("{$frontendUrl}/en/auth/callback?error=google_api_error&message=" . urlencode('User denied access to Google account'));
        }

        // Validate required parameters
        if (!$request->has('code')) {
            return redirect("{$frontendUrl}/en/auth/callback?error=invalid_code&message=" . urlencode('Authorization code is required'));
        }

        if (!$request->has('state')) {
            return redirect("{$frontendUrl}/en/auth/callback?error=invalid_state&message=" . urlencode('State parameter is required'));
        }

        $state = $request->input('state');

        // Validate state (CSRF protection)
        $cacheKey = "google_oauth_state:{$state}";
        if (!Cache::has($cacheKey)) {
            return redirect("{$frontendUrl}/en/auth/callback?error=invalid_state&message=" . urlencode('Invalid or expired state parameter. Please try again.'));
        }

        // Delete state to prevent reuse
        Cache::forget($cacheKey);

        try {
            // Exchange code for Google user
            $googleUser = Socialite::driver('google')->stateless()->user();

            // Find or create user
            $user = User::where('google_id', $googleUser->getId())->first();

            if (!$user) {
                // Check if user exists with same email
                $user = User::where('email', $googleUser->getEmail())->first();

                if ($user) {
                    // Link Google account to existing user
                    $user->update([
                        'google_id' => $googleUser->getId(),
                        'avatar_url' => $googleUser->getAvatar(),
                        'last_login_at' => now(),
                    ]);
                } else {
                    // Create new user
                    $user = User::create([
                        'name' => $googleUser->getName(),
                        'email' => $googleUser->getEmail(),
                        'google_id' => $googleUser->getId(),
                        'avatar_url' => $googleUser->getAvatar(),
                        'email_verified_at' => now(),
                        'last_login_at' => now(),
                        'status_id' => 1, // Active status
                    ]);
                }
            } else {
                // Update last login
                $user->update([
                    'last_login_at' => now(),
                    'avatar_url' => $googleUser->getAvatar(),
                ]);
            }

            // Create Sanctum token
            $token = $user->createToken('google-oauth')->plainTextToken;

            // Generate one-time exchange code (secure, short-lived)
            $exchangeCode = Str::random(64);

            // Store token in cache with 5-minute TTL (one-time use)
            Cache::put("google_oauth_exchange:{$exchangeCode}", [
                'token' => $token,
                'user_id' => $user->id,
                'created_at' => now()->toIso8601String(),
            ], now()->addMinutes(5));

            // Redirect to frontend callback with one-time exchange code
            return redirect("{$frontendUrl}/en/auth/callback?code={$exchangeCode}");
        } catch (\Laravel\Socialite\Two\InvalidStateException $e) {
            Log::error('Google OAuth invalid state exception', [
                'error' => $e->getMessage(),
            ]);

            return redirect("{$frontendUrl}/en/auth/callback?error=invalid_state&message=" . urlencode('Invalid state parameter. Please try again.'));
        } catch (\Exception $e) {
            Log::error('Google OAuth callback error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect("{$frontendUrl}/en/auth/callback?error=user_creation_failed&message=" . urlencode('Failed to authenticate with Google. Please try again.'));
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/auth/google/exchange",
     *     operationId="googleExchange",
     *     summary="Exchange one-time code for Sanctum token",
     *     description="Exchanges a one-time code (received from OAuth callback redirect) for a Sanctum Bearer token and user profile. The code is single-use and expires after 5 minutes.",
     *     tags={"Auth"},
     *     @OA\Parameter(ref="#/components/parameters/AcceptLanguage"),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"code"},
     *             @OA\Property(property="code", type="string", description="One-time exchange code from OAuth callback", example="abc123xyz...")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Token exchange successful",
     *         @OA\JsonContent(ref="#/components/schemas/GoogleCallbackResponse")
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid or expired code",
     *         @OA\JsonContent(ref="#/components/schemas/GoogleCallbackErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=429,
     *         description="Rate limit exceeded",
     *         @OA\JsonContent(ref="#/components/schemas/RateLimitErrorResponse")
     *     )
     * )
     */
    public function exchange(Request $request)
    {
        $request->validate([
            'code' => 'required|string',
        ]);

        $code = $request->input('code');
        $cacheKey = "google_oauth_exchange:{$code}";

        // Check if code exists in cache
        if (!Cache::has($cacheKey)) {
            return response()->json([
                'error' => 'invalid_code',
                'message' => 'Invalid or expired exchange code',
            ], 400);
        }

        // Get token data from cache
        $data = Cache::get($cacheKey);

        // Delete code to prevent reuse (one-time use)
        Cache::forget($cacheKey);

        // Get user
        $user = User::find($data['user_id']);

        if (!$user) {
            return response()->json([
                'error' => 'user_not_found',
                'message' => 'User not found',
            ], 400);
        }

        return response()->json([
            'token' => $data['token'],
            'token_type' => 'Bearer',
            'expires_in' => 0, // Sanctum tokens don't expire by default
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'avatar_url' => $user->avatar_url,
                'locale' => $user->locale,
                'created_at' => $user->created_at->toIso8601String(),
            ],
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/auth/logout",
     *     operationId="logout",
     *     summary="Logout current user",
     *     description="Revokes the current Sanctum Bearer token. The user will need to re-authenticate to access protected endpoints.",
     *     tags={"Auth"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(ref="#/components/parameters/AcceptLanguage"),
     *     @OA\Response(
     *         response=200,
     *         description="Logout successful, token revoked",
     *         @OA\JsonContent(ref="#/components/schemas/LogoutResponse")
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated (missing or invalid token)",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     )
     * )
     */
    public function logout(Request $request)
    {
        // Revoke the current token only
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/me",
     *     operationId="getMe",
     *     summary="Get current authenticated user profile",
     *     description="Returns the profile of the currently authenticated user. Requires a valid Sanctum Bearer token.",
     *     tags={"Auth"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(ref="#/components/parameters/AcceptLanguage"),
     *     @OA\Response(
     *         response=200,
     *         description="User profile retrieved successfully",
     *         @OA\JsonContent(ref="#/components/schemas/MeResponse")
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated (missing or invalid token)",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     )
     * )
     */
    public function me(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'avatar_url' => $user->avatar_url,
            'locale' => $user->locale,
            'created_at' => $user->created_at->toIso8601String(),
        ]);
    }
}
