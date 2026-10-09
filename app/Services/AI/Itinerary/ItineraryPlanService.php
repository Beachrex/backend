<?php

namespace App\Services\AI\Itinerary;

use App\Models\ItineraryPlan;
use App\Services\Payment\EntitlementService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * AI-3: state changes of a background itinerary. The reserved credit goes back at most once (row lock +
 * credit_charged flag), whatever path ends the plan: AI fallback, too few beaches, error, worker timeout,
 * or a plan stuck in the queue.
 */
class ItineraryPlanService
{
    public function __construct(private readonly EntitlementService $entitlements) {}

    public function releaseCredit(ItineraryPlan $plan): void
    {
        DB::transaction(function () use ($plan) {
            $locked = ItineraryPlan::whereKey($plan->id)->lockForUpdate()->first();
            if (! $locked || ! $locked->credit_charged || ! $locked->product_type) {
                return;
            }
            $this->entitlements->grantCredits($locked->user, $locked->product_type, 1);
            $locked->update(['credit_charged' => false]);
        });
        $plan->refresh();
    }

    public function fail(ItineraryPlan $plan, string $error): void
    {
        if ($plan->isFinished()) {
            return;
        }
        $plan->update(['status' => ItineraryPlan::STATUS_FAILED, 'error' => $error, 'finished_at' => now()]);
        $this->releaseCredit($plan);
    }

    /** Plans still queued/running long after they should have finished (worker down or killed). */
    public function failStale(): int
    {
        $stale = ItineraryPlan::query()
            ->where(fn ($q) => $q
                ->where(fn ($r) => $r->where('status', ItineraryPlan::STATUS_RUNNING)
                    ->where('updated_at', '<', now()->subMinutes(ItineraryPlan::STALE_RUNNING_MINUTES)))
                ->orWhere(fn ($r) => $r->where('status', ItineraryPlan::STATUS_QUEUED)
                    ->where('created_at', '<', now()->subMinutes(ItineraryPlan::STALE_QUEUED_MINUTES))))
            ->get();
        foreach ($stale as $plan) {
            Log::warning('Itinerary plan timed out; credit returned', ['plan_id' => $plan->id, 'status' => $plan->status]);
            $this->fail($plan, 'timeout');
        }

        return $stale->count();
    }
}
