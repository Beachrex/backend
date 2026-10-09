<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

use function Illuminate\Support\defer;

/**
 * "Forgot password" for e-mail/password accounts (owner request 2026-10-09; imported old-site users
 * have a random password and need this to sign in without Google).
 */
class PasswordResetController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/v1/forgot-password",
     *     operationId="forgotPassword",
     *     summary="Send a password reset link by e-mail",
     *     description="Always answers 200 with the same message, whether or not an account exists (no account enumeration). The e-mail is sent after the response, in the request's language, with a link to `{FRONTEND_URL}/{locale}/auth/reset-password?token=…&email=…` (valid 60 minutes, at most one e-mail per address per minute). Rate limit: 5 requests per minute per IP.",
     *     tags={"Auth"},
     *     @OA\Parameter(ref="#/components/parameters/AcceptLanguage"),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email"},
     *             @OA\Property(property="email", type="string", format="email", example="john@example.com")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Request accepted",
     *         @OA\JsonContent(@OA\Property(property="message", type="string", example="If an account exists for this e-mail, we have sent a link to reset the password."))
     *     ),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=429, description="Too many requests")
     * )
     */
    public function forgot(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email|max:255',
        ]);

        $locale = app()->getLocale();

        // After the response: same timing for known and unknown addresses, and a mail server
        // problem is logged instead of failing the request.
        defer(function () use ($validated, $locale) {
            try {
                app()->setLocale($locale);
                Password::broker()->sendResetLink(['email' => $validated['email']]);
            } catch (\Throwable $e) {
                Log::error('Password reset e-mail could not be sent', ['error' => $e->getMessage()]);
            }
        });

        return response()->json([
            'message' => __('If an account exists for this e-mail, we have sent a link to reset the password.'),
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/reset-password",
     *     operationId="resetPassword",
     *     summary="Set a new password with the token from the reset e-mail",
     *     description="On success all API tokens of the user are revoked (other devices are signed out) and the e-mail counts as verified. The user then signs in with POST /api/v1/login. Rate limit: 10 requests per minute per IP.",
     *     tags={"Auth"},
     *     @OA\Parameter(ref="#/components/parameters/AcceptLanguage"),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"token","email","password","password_confirmation"},
     *             @OA\Property(property="token", type="string", example="9f1c…"),
     *             @OA\Property(property="email", type="string", format="email", example="john@example.com"),
     *             @OA\Property(property="password", type="string", format="password", minLength=8, example="new-secret-123"),
     *             @OA\Property(property="password_confirmation", type="string", format="password", example="new-secret-123")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Password changed",
     *         @OA\JsonContent(@OA\Property(property="message", type="string", example="Your password has been changed. You can now sign in."))
     *     ),
     *     @OA\Response(response=422, description="Validation error, or the link is invalid/expired (error on `token`)"),
     *     @OA\Response(response=429, description="Too many requests")
     * )
     */
    public function reset(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => 'required|string',
            'email' => 'required|email|max:255',
            'password' => 'required|string|min:8|max:255|confirmed',
        ]);

        $status = Password::broker()->reset($validated, function (User $user, string $password) {
            $user->forceFill([
                'password' => $password,
                'remember_token' => Str::random(60),
                'email_verified_at' => $user->email_verified_at ?? now(),
            ])->save();

            $user->tokens()->delete();

            event(new PasswordReset($user));
        });

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'token' => [__('This password reset link is invalid or has expired.')],
            ]);
        }

        return response()->json([
            'message' => __('Your password has been changed. You can now sign in.'),
        ]);
    }
}
