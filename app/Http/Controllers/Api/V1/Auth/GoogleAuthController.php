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
     *     description="Exchanges the authorization code for a Google access token, fetches user profile, creates or updates the user in the database, and returns a Sanctum Bearer token. The state parameter is validated for CSRF protection.",
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
     *         response=200,
     *         description="Authentication successful, user logged in",
     *         @OA\JsonContent(ref="#/components/schemas/GoogleCallbackResponse")
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Bad request (missing code/state, or Google returned error)",
     *         @OA\JsonContent(ref="#/components/schemas/GoogleCallbackErrorResponse"),
     *         @OA\Examples(
     *             example="missing_code",
     *             summary="Missing authorization code",
     *             value={
     *                 "error": "invalid_code",
     *                 "message": "Authorization code is required"
     *             }
     *         ),
     *         @OA\Examples(
     *             example="google_error",
     *             summary="User denied access",
     *             value={
     *                 "error": "google_api_error",
     *                 "message": "User denied access to Google account"
     *             }
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized (invalid or expired state parameter)",
     *         @OA\JsonContent(ref="#/components/schemas/GoogleCallbackErrorResponse"),
     *         @OA\Examples(
     *             example="invalid_state",
     *             summary="Invalid CSRF state",
     *             value={
     *                 "error": "invalid_state",
     *                 "message": "Invalid or expired state parameter. Please try again."
     *             }
     *         )
     *     ),
     *     @OA\Response(
     *         response=429,
     *         description="Rate limit exceeded",
     *         @OA\JsonContent(ref="#/components/schemas/RateLimitErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error (e.g., failed to create user, Google API error)",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="user_creation_failed"),
     *             @OA\Property(property="message", type="string", example="Failed to create user account. Please try again.")
     *         )
     *     )
     * )
     */
    public function callback(Request $request)
    {
        // Check for Google error
        if ($request->has('error')) {
            return response()->json([
                'error' => 'google_api_error',
                'message' => 'User denied access to Google account',
            ], 400);
        }

        // Validate required parameters
        if (!$request->has('code')) {
            return response()->json([
                'error' => 'invalid_code',
                'message' => 'Authorization code is required',
            ], 400);
        }

        if (!$request->has('state')) {
            return response()->json([
                'error' => 'invalid_state',
                'message' => 'State parameter is required',
            ], 400);
        }

        $state = $request->input('state');

        // Validate state (CSRF protection)
        $cacheKey = "google_oauth_state:{$state}";
        if (!Cache::has($cacheKey)) {
            return response()->json([
                'error' => 'invalid_state',
                'message' => 'Invalid or expired state parameter. Please try again.',
            ], 401);
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

            return response()->json([
                'token' => $token,
                'token_type' => 'Bearer',
                'expires_in' => 0, // Sanctum PAT doesn't expire by default
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'avatar_url' => $user->avatar_url,
                    'locale' => $user->locale,
                    'created_at' => $user->created_at->toIso8601String(),
                ],
            ]);
        } catch (\Laravel\Socialite\Two\InvalidStateException $e) {
            Log::error('Google OAuth invalid state exception', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'invalid_state',
                'message' => 'Invalid state parameter. Please try again.',
            ], 401);
        } catch (\Exception $e) {
            Log::error('Google OAuth callback error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => 'user_creation_failed',
                'message' => 'Failed to authenticate with Google. Please try again.',
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/auth/logout",
     *     operationId="logout",
     *     summary="Logout user and revoke token",
     *     description="Revokes the current Sanctum Bearer token. The user must be authenticated.",
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

