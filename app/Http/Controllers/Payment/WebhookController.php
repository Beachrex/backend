<?php

namespace App\Http\Controllers\Payment;

use App\Http\Controllers\Controller;
use App\Models\Purchase;
use App\Models\User;
use App\Services\Payment\StripeService;
use App\Services\Payment\EntitlementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\SignatureVerificationException;

class WebhookController extends Controller
{
    public function __construct(
        private StripeService $stripeService,
        private EntitlementService $entitlementService
    ) {}

    /**
     * Handle Stripe webhook events.
     *
     * @OA\Post(
     *     path="/api/v1/payments/stripe/webhook",
     *     summary="Receive Stripe webhook events",
     *     tags={"Payments"},
     *     @OA\RequestBody(
     *         required=true,
     *         description="Stripe webhook payload",
     *         @OA\JsonContent(type="object")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Webhook received successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="received", type="boolean", example=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid signature or webhook data",
     *         @OA\JsonContent(ref="#/components/schemas/PaymentErrorResponse")
     *     )
     * )
     */
    public function handle(Request $request): JsonResponse
    {
        $payload = $request->getContent();
        $signature = $request->header('Stripe-Signature');

        if (!$signature) {
            Log::warning('Stripe webhook received without signature');
            return response()->json([
                'error' => 'invalid_signature',
                'message' => 'Webhook signature missing',
            ], 400);
        }

        try {
            // Verify webhook signature
            $event = $this->stripeService->verifyWebhookSignature($payload, $signature);

            Log::info('Stripe webhook received', [
                'event_id' => $event->id,
                'event_type' => $event->type,
            ]);

            // Check for duplicate events (idempotency)
            if (Purchase::where('stripe_event_id', $event->id)->exists()) {
                Log::info('Duplicate webhook event, skipping', ['event_id' => $event->id]);
                return response()->json(['received' => true], 200);
            }

            // Handle different event types
            switch ($event->type) {
                case 'checkout.session.completed':
                    $this->handleCheckoutSessionCompleted($event);
                    break;

                case 'checkout.session.expired':
                    $this->handleCheckoutSessionExpired($event);
                    break;

                case 'charge.refunded':
                    $this->handleChargeRefunded($event);
                    break;

                default:
                    Log::info('Unhandled webhook event type', ['type' => $event->type]);
            }

            return response()->json(['received' => true], 200);

        } catch (SignatureVerificationException $e) {
            Log::error('Stripe webhook signature verification failed', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'invalid_signature',
                'message' => 'Webhook signature verification failed',
            ], 400);

        } catch (\Exception $e) {
            Log::error('Stripe webhook processing failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => 'webhook_processing_error',
                'message' => 'Failed to process webhook',
            ], 500);
        }
    }

    /**
     * Handle checkout.session.completed event.
     */
    private function handleCheckoutSessionCompleted($event): void
    {
        $session = $event->data->object;

        // Extract metadata
        $userId = $session->metadata->user_id ?? $session->client_reference_id;
        $productType = $session->metadata->product_type ?? null;
        $credits = $session->metadata->credits ?? 1;

        if (!$userId || !$productType) {
            Log::error('Missing required metadata in checkout session', [
                'session_id' => $session->id,
                'metadata' => $session->metadata,
            ]);
            return;
        }

        $user = User::find($userId);

        if (!$user) {
            Log::error('User not found for checkout session', [
                'user_id' => $userId,
                'session_id' => $session->id,
            ]);
            return;
        }

        // Get product configuration
        $product = $this->stripeService->getProductConfig($productType);

        if (!$product) {
            Log::error('Product configuration not found', [
                'product_type' => $productType,
                'session_id' => $session->id,
            ]);
            return;
        }

        // Create purchase record
        $purchase = Purchase::create([
            'user_id' => $user->id,
            'product_type' => $productType,
            'amount' => $product['amount'],
            'currency' => $product['currency'],
            'status' => Purchase::STATUS_COMPLETED,
            'stripe_session_id' => $session->id,
            'stripe_payment_intent_id' => $session->payment_intent ?? null,
            'stripe_event_id' => $event->id,
            'metadata' => [
                'session' => $session->toArray(),
            ],
            'purchased_at' => now(),
        ]);

        // Grant credits to user
        $this->entitlementService->grantCredits($user, $productType, $credits);

        Log::info('Purchase completed and credits granted', [
            'purchase_id' => $purchase->id,
            'user_id' => $user->id,
            'product_type' => $productType,
            'credits' => $credits,
        ]);
    }

    /**
     * Handle checkout.session.expired event.
     */
    private function handleCheckoutSessionExpired($event): void
    {
        $session = $event->data->object;

        Log::info('Checkout session expired', [
            'session_id' => $session->id,
            'event_id' => $event->id,
        ]);

        // Optional: Create purchase record with 'failed' status
        // For now, we just log it
    }

    /**
     * Handle charge.refunded event.
     */
    private function handleChargeRefunded($event): void
    {
        $charge = $event->data->object;
        $paymentIntentId = $charge->payment_intent;

        // Find purchase by payment intent ID
        $purchase = Purchase::where('stripe_payment_intent_id', $paymentIntentId)->first();

        if (!$purchase) {
            Log::warning('Purchase not found for refunded charge', [
                'payment_intent_id' => $paymentIntentId,
                'charge_id' => $charge->id,
            ]);
            return;
        }

        // Mark purchase as refunded
        $purchase->markAsRefunded();

        // Revoke credits from user
        $user = $purchase->user;
        $productType = $purchase->product_type;
        $product = $this->stripeService->getProductConfig($productType);
        $credits = $product['credits'] ?? 1;

        $this->entitlementService->revokeCredits($user, $productType, $credits);

        Log::info('Purchase refunded and credits revoked', [
            'purchase_id' => $purchase->id,
            'user_id' => $user->id,
            'product_type' => $productType,
            'credits' => $credits,
        ]);
    }
}

