<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\NewsletterSubscriber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="Newsletter",
 *     description="Newsletter subscription management (email collection for marketing, GDPR-compliant)"
 * )
 */
class NewsletterSubscriberController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/v1/newsletter/subscribe",
     *     operationId="subscribeToNewsletter",
     *     summary="Subscribe an email to the newsletter",
     *     description="Validates and stores a newsletter subscription. Idempotent: re-subscribing an existing active email returns success without creating a duplicate. Re-subscribing an unsubscribed email re-activates it. Requires GDPR consent.",
     *     tags={"Newsletter"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email","consent"},
     *             @OA\Property(property="email", type="string", format="email", example="user@example.com", description="Subscriber email address"),
     *             @OA\Property(property="consent", type="boolean", example=true, description="GDPR consent — user must accept to be subscribed"),
     *             @OA\Property(property="source", type="string", example="footer", description="Subscription source (footer, popup, etc.)")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Successfully subscribed",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="You have been subscribed successfully.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Already subscribed (idempotent)",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="You are already subscribed.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error (invalid email or missing consent)",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     )
     * )
     */
    public function subscribe(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email:rfc,dns', 'max:255'],
            'consent' => ['required', 'boolean', 'accepted'],
            'source' => ['nullable', 'string', 'max:50'],
        ]);

        $email = strtolower(trim($validated['email']));

        // Idempotent: if the email already exists, re-activate if it was unsubscribed.
        $existing = NewsletterSubscriber::where('email', $email)->first();

        if ($existing) {
            if ($existing->status_id === 1) {
                // Already active — no change, return success (idempotent).
                return response()->json([
                    'success' => true,
                    'message' => __('You are already subscribed.'),
                ], 200);
            }

            // Was unsubscribed — re-activate.
            $existing->update([
                'status_id' => 1,
                'consent_accepted' => true,
                'is_synced' => false, // needs re-sync to ESP
                'synced_at' => null,
                'source' => $validated['source'] ?? $existing->source,
                'subscribed_at' => now(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'locale' => app()->getLocale(),
            ]);

            return response()->json([
                'success' => true,
                'message' => __('You have been re-subscribed successfully.'),
            ], 200);
        }

        // New subscriber.
        NewsletterSubscriber::create([
            'email' => $email,
            'status_id' => 1,
            'is_synced' => false,
            'consent_accepted' => true,
            'subscribed_at' => now(),
            'source' => $validated['source'] ?? 'footer',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'locale' => app()->getLocale(),
        ]);

        return response()->json([
            'success' => true,
            'message' => __('You have been subscribed successfully.'),
        ], 201);
    }
}
