<?php

namespace App\Http\Controllers\Payment;

use App\Http\Controllers\Controller;
use App\Models\Purchase;
use App\Models\User;
use App\Services\Payment\StripeService;
use App\Services\Payment\EntitlementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
                case 'checkout.session.async_payment_succeeded':
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

        // Bank transfers and similar methods complete the session before the money arrives; their credits
        // come with checkout.session.async_payment_succeeded.
        $paymentStatus = $session->payment_status ?? null;
        if (! in_array($paymentStatus, ['paid', 'no_payment_required'], true)) {
            Log::info('Checkout session not paid yet, waiting', ['session_id' => $session->id, 'payment_status' => $paymentStatus]);

            return;
        }

        $userId = $session->metadata->user_id ?? $session->client_reference_id;
        $productType = $session->metadata->product_type ?? null;

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

        $product = $this->stripeService->getProductConfig($productType);

        if (!$product) {
            Log::error('Product configuration not found', [
                'product_type' => $productType,
                'session_id' => $session->id,
            ]);
            return;
        }
        // Credits come from our own product config, never from request metadata.
        $credits = (int) ($product['credits'] ?? 1);

        // Purchase and credits are written together or not at all. The unique stripe_session_id makes a second
        // delivery of the same payment (a retry, or completed + async_payment_succeeded) a no-op, also when two
        // deliveries race.
        try {
            $purchase = DB::transaction(function () use ($user, $productType, $product, $session, $event, $credits) {
                if (Purchase::where('stripe_session_id', $session->id)->lockForUpdate()->exists()) {
                    return null;
                }
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
                $this->entitlementService->grantCredits($user, $productType, $credits);

                return $purchase;
            });
        } catch (UniqueConstraintViolationException) {
            $purchase = null;
        }

        if (! $purchase) {
            Log::info('Checkout session already processed, skipping', ['session_id' => $session->id, 'event_id' => $event->id]);

            return;
        }

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

        // Credits are revoked only when the whole charge is refunded; a partial refund (goodwill) keeps them.
        if (($charge->refunded ?? false) !== true) {
            Log::info('Partial refund, credits kept', ['payment_intent_id' => $paymentIntentId, 'charge_id' => $charge->id]);

            return;
        }

        // Stripe retries and repeats events: the purchase row is locked and a purchase that is already refunded is
        // skipped, so a refund revokes credits exactly once.
        $result = DB::transaction(function () use ($paymentIntentId) {
            $purchase = Purchase::where('stripe_payment_intent_id', $paymentIntentId)->lockForUpdate()->first();
            if (! $purchase || $purchase->isRefunded()) {
                return [$purchase, 0];
            }
            $purchase->markAsRefunded();
            $product = $this->stripeService->getProductConfig($purchase->product_type);
            $credits = (int) ($product['credits'] ?? 1);
            $this->entitlementService->revokeCredits($purchase->user, $purchase->product_type, $credits);

            return [$purchase, $credits];
        });
        [$purchase, $credits] = $result;

        if (! $purchase) {
            Log::warning('Purchase not found for refunded charge', [
                'payment_intent_id' => $paymentIntentId,
                'charge_id' => $charge->id,
            ]);
            return;
        }

        Log::info($credits ? 'Purchase refunded and credits revoked' : 'Refund already processed, skipping', [
            'purchase_id' => $purchase->id,
            'user_id' => $purchase->user_id,
            'product_type' => $purchase->product_type,
            'credits' => $credits,
        ]);
    }
}

