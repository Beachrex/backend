<?php

namespace App\Http\Controllers\Payment;

use App\Http\Controllers\Controller;
use App\Services\Payment\EntitlementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Annotations as OA;

class EntitlementController extends Controller
{
    public function __construct(
        private EntitlementService $entitlementService
    ) {}

    /**
     * @OA\Get(
     *     path="/api/v1/me/entitlements",
     *     summary="Get current user's entitlements and purchase history",
     *     tags={"Payments"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Entitlements retrieved successfully",
     *         @OA\JsonContent(ref="#/components/schemas/EntitlementsResponse")
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(ref="#/components/schemas/PaymentErrorResponse")
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        // Get all entitlements
        $entitlements = $this->entitlementService->getAllEntitlements($user);

        // Get purchase history
        $purchases = $this->entitlementService->getPurchaseHistory($user);

        // Format purchases for response
        $formattedPurchases = $purchases->map(function ($purchase) {
            return [
                'id' => $purchase->id,
                'product_type' => $purchase->product_type,
                'amount' => (float) $purchase->amount,
                'currency' => $purchase->currency,
                'status' => $purchase->status,
                'purchased_at' => $purchase->purchased_at?->toIso8601String(),
                'stripe_session_id' => $purchase->stripe_session_id,
            ];
        });

        return response()->json([
            'entitlements' => $entitlements,
            'purchases' => $formattedPurchases,
        ], 200);
    }
}

