<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use App\Models\User;
use App\Http\Resources\UserResource;

class AuthController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/v1/register",
     *     operationId="registerUser",
     *     summary="Register a new user",
     *     tags={"Auth"},
     *     @OA\Parameter(ref="#/components/parameters/AcceptLanguage"),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name","email","password","password_confirmation"},
     *             @OA\Property(property="name", type="string", example="John Doe"),
     *             @OA\Property(property="email", type="string", format="email", example="john@example.com"),
     *             @OA\Property(property="password", type="string", format="password", example="secret123"),
     *             @OA\Property(property="password_confirmation", type="string", format="password", example="secret123")
     *         )
     *     ),
     *     @OA\Response(
     *              response=201,
     *              description="Successful registration",
     *              @OA\JsonContent(ref="#/components/schemas/RegisterResponse")
     *      ),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        return response()->json([
            'user' => new UserResource($user),
            'token' => $user->createToken('api-token')->plainTextToken,
        ], 201);
    }
    /**
     * @OA\Post(
     *     path="/api/v1/login",
     *     operationId="loginUser",
     *     summary="Login user and get API token",
     *     tags={"Auth"},
     *     @OA\Parameter(ref="#/components/parameters/AcceptLanguage"),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/LoginRequest")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Login successful",
     *         @OA\JsonContent(ref="#/components/schemas/LoginResponse")
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Invalid credentials"
     *     )
     * )
     */
    public function login(Request $request)
    {
        $request->validate(['email' => 'required|email', 'password' => 'required|string']);
        $user = User::where('email', $request->email)->first();

        if (! $user || (int) $user->status_id === 2 || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Invalid credentials.'],
            ]);
        }

        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => new UserResource($user)
        ]);
    }
    /**
     * @OA\Post(
     *     path="/api/v1/logout",
     *     operationId="logoutUser",
     *     summary="Logout the currently authenticated user",
     *     description="**This endpoint requires an Authorization header with a Bearer token.** Example: `Authorization: Bearer YOUR_TOKEN`",
     *     tags={"Auth"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Successful logout",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Logged out")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out']);
    }

    /**
     * Delete my account (B-M2; required by the App Store for apps with sign-up).
     *
     * @OA\Delete(
     *     path="/api/v1/me",
     *     operationId="deleteMyAccount",
     *     summary="Delete my account",
     *     description="Removes personal data (profile, sign-in, tokens, saved beaches, itineraries, AI chat history, credits). Purchases are kept anonymised because accounting law requires it. Confirm with `password`; accounts linked to Google may send `confirm: DELETE` instead. Admin accounts cannot be deleted here.",
     *     tags={"Auth"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         @OA\Property(property="password", type="string", description="Current password (accounts created with e-mail and password)"),
     *         @OA\Property(property="confirm", type="string", example="DELETE", description="Accounts linked to Google only (they may have no password of their own)")
     *     )),
     *     @OA\Response(response=204, description="Deleted"),
     *     @OA\Response(response=401, description="Not signed in"),
     *     @OA\Response(response=403, description="Admin accounts cannot be deleted through the API"),
     *     @OA\Response(response=422, description="Wrong password or missing confirmation")
     * )
     */
    public function destroy(Request $request, \App\Services\Account\AccountDeletionService $deletion)
    {
        $user = $request->user();
        if ($user->is_admin) {
            return response()->json(['message' => 'Admin accounts cannot be deleted through the API.'], 403);
        }

        // Accounts linked to Google may never have chosen a password (random one at sign-up): they can confirm
        // with confirm: "DELETE" instead. Every other account confirms with its password.
        $passwordOk = is_string($request->input('password')) && Hash::check($request->input('password'), $user->password);
        $googleConfirm = $user->google_id && $request->input('confirm') === 'DELETE';
        if (! $passwordOk && ! $googleConfirm) {
            throw ValidationException::withMessages([
                $user->google_id ? 'confirm' : 'password' => [$user->google_id ? 'Send your password or confirm: "DELETE".' : 'The password is not correct.'],
            ]);
        }

        $deletion->delete($user);

        return response()->noContent();
    }
}
