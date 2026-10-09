<?php

namespace App\Http\Controllers\Payment;

use App\Http\Controllers\Controller;
use App\Services\Payment\StripeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use OpenApi\Annotations as OA;

class CheckoutController extends Controller
{
    public function __construct(
        private StripeService $stripeService
    ) {}

    /**
     * AI-3: the itinerary packages with their prices, so the website shows the price the checkout will charge.
     *
     * @OA\Get(
     *     path="/api/v1/payments/products",
     *     summary="Itinerary packages and prices",
     *     tags={"Payments"},
     *     @OA\Response(
     *         response=200,
     *         description="Packages, shortest first",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array", @OA\Items(
     *                 @OA\Property(property="product_type", type="string", example="itinerary_7day"),
     *                 @OA\Property(property="days", type="integer", example=7),
     *                 @OA\Property(property="amount", type="number", format="float", example=39.0),
     *                 @OA\Property(property="currency", type="string", example="EUR"),
     *                 @OA\Property(property="credits", type="integer", example=1)
     *             ))
     *         )
     *     )
     * )
     */
    public function products(): JsonResponse
    {
        $products = [];
        foreach ($this->stripeService->getAvailableProducts() as $type => $product) {
            if (! preg_match('/^itinerary_(\d+)day$/', $type, $m)) {
                continue;
            }
            $products[] = [
                'product_type' => $type,
                'days' => (int) $m[1],
                'amount' => (float) $product['amount'],
                'currency' => strtoupper((string) $product['currency']),
                'credits' => (int) ($product['credits'] ?? 1),
            ];
        }
        usort($products, fn ($a, $b) => $a['days'] <=> $b['days']);

        return response()->json(['data' => $products]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/payments/stripe/checkout",
     *     summary="Create Stripe Checkout session for itinerary purchase",
     *     tags={"Payments"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/CheckoutRequest")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Checkout session created successfully",
     *         @OA\JsonContent(ref="#/components/schemas/CheckoutResponse")
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid product type",
     *         @OA\JsonContent(ref="#/components/schemas/PaymentErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(ref="#/components/schemas/PaymentErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="consent_required: accept_terms and waive_withdrawal must be true",
     *         @OA\JsonContent(ref="#/components/schemas/PaymentErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Payment gateway error",
     *         @OA\JsonContent(ref="#/components/schemas/PaymentErrorResponse")
     *     )
     * )
     */
    public function create(Request $request): JsonResponse
    {
        // Check if payments are enabled
        if (!config('ai.payments_enabled')) {
            return response()->json([
                'error' => 'payments_disabled',
                'message' => 'Payment system is currently disabled',
            ], 503);
        }

        // Check if Stripe is enabled
        if (!config('ai.stripe.enabled')) {
            return response()->json([
                'error' => 'stripe_disabled',
                'message' => 'Stripe integration is currently disabled',
            ], 503);
        }

        // Validate request
        $validator = Validator::make($request->all(), [
            'product_type' => 'required|string|in:itinerary_3day,itinerary_7day,itinerary_14day',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'invalid_product',
                'message' => 'Invalid product type. Must be one of: itinerary_3day, itinerary_7day, itinerary_14day',
                'errors' => $validator->errors(),
            ], 400);
        }

        // AI-4: before paying, the buyer accepts the terms of sale and asks for immediate delivery, acknowledging
        // that the 14-day withdrawal right ends once a plan is created (Directive 2011/83/EU, Art. 16(m)).
        $consent = Validator::make($request->all(), [
            'accept_terms' => 'accepted',
            'waive_withdrawal' => 'accepted',
        ]);
        if ($consent->fails()) {
            return response()->json([
                'error' => 'consent_required',
                'message' => 'Accept the terms of sale and confirm immediate delivery to continue.',
                'errors' => $consent->errors(),
            ], 422);
        }

        $productType = $request->input('product_type');
        $user = $request->user();

        try {
            // Get product configuration
            $product = $this->stripeService->getProductConfig($productType);

            if (!$product) {
                return response()->json([
                    'error' => 'invalid_product',
                    'message' => 'Product configuration not found',
                ], 400);
            }

            // Create Stripe Checkout session
            // Back to the result pages in the buyer's language (website locale: si for Slovenian).
            $webLocale = app()->getLocale() === 'sl' ? 'si' : app()->getLocale();
            $base = rtrim((string) config('app.frontend_url'), '/') . '/' . $webLocale;
            $successUrl = $base . '/payment/success?session_id={CHECKOUT_SESSION_ID}';
            $cancelUrl = $base . '/payment/cancel';

            $session = $this->stripeService->createCheckoutSession(
                $user,
                $productType,
                $successUrl,
                $cancelUrl,
                ['accepted_at' => now()->toIso8601String(), 'waive_withdrawal' => true, 'locale' => $webLocale]
            );

            return response()->json([
                'checkout_url' => $session->url,
                'session_id' => $session->id,
                'product' => [
                    'type' => $productType,
                    'price' => $product['amount'],
                    'currency' => $product['currency'],
                    'credits' => $product['credits'],
                    'name' => $product['name'],
                ],
            ], 200);

        } catch (\Exception $e) {
            \Log::error('Stripe checkout session creation failed', [
                'user_id' => $user->id,
                'product_type' => $productType,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'payment_gateway_error',
                'message' => 'Unable to create checkout session. Please try again later.',
            ], 500);
        }
    }
}

