<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Http\Requests\AI\ItineraryRequest;
use App\Jobs\GenerateItineraryPlan;
use App\Models\ItineraryPlan;
use App\Services\AI\Itinerary\ItineraryPlanService;
use App\Services\AI\PlanEnforcementService;
use App\Services\Payment\EntitlementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * AI-3: bought itineraries are generated in the background and kept for the user. The credit is reserved when
 * the plan is queued and goes back if the plan fails or has no AI narrative.
 */
class ItineraryPlanController extends Controller
{
    public function __construct(
        private readonly PlanEnforcementService $planEnforcement,
        private readonly EntitlementService $entitlements,
        private readonly ItineraryPlanService $plans,
    ) {}

    /**
     * @OA\Post(
     *     path="/api/v1/ai/itinerary-plans",
     *     summary="Start a paid itinerary in the background",
     *     description="Reserves one credit for the trip length and queues the plan. Poll GET /api/v1/me/itinerary-plans/{id} until status is done or failed. Failed plans and plans without the AI narrative give the credit back.",
     *     tags={"AI"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="Accept-Language", in="header", required=false, @OA\Schema(type="string", example="sl"), description="Language of the plan"),
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/AiItineraryRequest")),
     *     @OA\Response(response=202, description="Queued", @OA\JsonContent(@OA\Property(property="data", ref="#/components/schemas/ItineraryPlanSummary"))),
     *     @OA\Response(response=401, description="Not signed in"),
     *     @OA\Response(response=402, description="No credit for this trip length"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(ItineraryRequest $request): JsonResponse
    {
        $user = $request->user();
        $days = (int) $request->input('duration_days');

        if ($denied = $this->planEnforcement->checkMultiDayItinerary($user, $days)) {
            return $denied;
        }

        $productType = null;
        if ($days >= 2 && config('ai.payments_enabled')) {
            $productType = $this->entitlements->mapDaysToProductType($days);
            if (! $this->entitlements->decrementCredits($user, $productType)) {
                return $this->planEnforcement->checkMultiDayItinerary($user->fresh(), $days)
                    ?? response()->json(['error' => 'payment_required', 'message' => 'You do not have credits for this itinerary.'], 402);
            }
        }

        $input = $request->validated();
        $input['locale'] = $input['locale'] ?? app()->getLocale();

        $plan = ItineraryPlan::create([
            'user_id' => $user->id,
            'status' => ItineraryPlan::STATUS_QUEUED,
            'locale' => $input['locale'],
            'country_code' => strtoupper($input['destination']['country_code']),
            'duration_days' => $days,
            'request' => $input,
            'product_type' => $productType,
            'credit_charged' => $productType !== null,
        ]);

        GenerateItineraryPlan::dispatch($plan->id);

        return response()->json(['data' => $this->summary($plan->fresh())], 202);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/me/itinerary-plans",
     *     summary="My itinerary plans (newest first, max 50)",
     *     tags={"AI"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Plans", @OA\JsonContent(@OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/ItineraryPlanSummary")))),
     *     @OA\Response(response=401, description="Not signed in")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $plans = ItineraryPlan::where('user_id', $request->user()->id)
            ->latest()->limit(50)
            ->get(['id', 'status', 'country_code', 'duration_days', 'title', 'error', 'credit_charged', 'request', 'created_at', 'finished_at', 'updated_at']);

        return response()->json(['data' => $plans->map(fn ($p) => $this->summary($p))->values()]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/me/itinerary-plans/{id}",
     *     summary="One of my itinerary plans, with the full plan when done",
     *     tags={"AI"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *     @OA\Response(response=200, description="Plan", @OA\JsonContent(@OA\Property(property="data", allOf={
     *         @OA\Schema(ref="#/components/schemas/ItineraryPlanSummary"),
     *         @OA\Schema(@OA\Property(property="itinerary", ref="#/components/schemas/AiItineraryResponse", nullable=true))
     *     }))),
     *     @OA\Response(response=401, description="Not signed in"),
     *     @OA\Response(response=404, description="Not found or not yours")
     * )
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $plan = ItineraryPlan::whereKey($id)->where('user_id', $request->user()->id)->first();
        if (! $plan) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        // A plan the worker never finished (worker down) fails here at the latest, with its credit back.
        if (! $plan->isFinished()) {
            $this->plans->failStale();
            $plan->refresh();
        }

        return response()->json(['data' => $this->summary($plan) + ['itinerary' => $plan->status === ItineraryPlan::STATUS_DONE ? $plan->result : null]]);
    }

    private function summary(ItineraryPlan $plan): array
    {
        return [
            'id' => $plan->id,
            'status' => $plan->status,
            'country_code' => $plan->country_code,
            'region_slug' => $plan->request['destination']['region_slug'] ?? null,
            'duration_days' => $plan->duration_days,
            'title' => $plan->title,
            'error' => $plan->error,
            'credit_used' => $plan->credit_charged,
            'created_at' => $plan->created_at?->toIso8601String(),
            'finished_at' => $plan->finished_at?->toIso8601String(),
        ];
    }
}
