<?php

namespace App\Jobs;

use App\Models\ItineraryPlan;
use App\Services\AI\Itinerary\ItineraryPlanService;
use App\Services\AI\Itinerary\NotEnoughBeachesException;
use App\Services\AI\ItineraryService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Str;
use Throwable;

/**
 * AI-3: generates one itinerary in the worker_<env> container (no 60 s PHP-FPM limit). One attempt only: a
 * retry would call the LLM again; a failed plan gives its credit back instead.
 */
class GenerateItineraryPlan implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    public int $timeout = 240;

    public function __construct(public readonly string $planId)
    {
        $this->onConnection('itineraries');
        $this->onQueue('itineraries');
    }

    public function handle(ItineraryService $itineraries, ItineraryPlanService $plans): void
    {
        $plan = ItineraryPlan::find($this->planId);
        if (! $plan || $plan->status !== ItineraryPlan::STATUS_QUEUED) {
            return;
        }
        $plan->update(['status' => ItineraryPlan::STATUS_RUNNING, 'started_at' => now()]);
        App::setLocale($plan->locale);

        try {
            $result = $itineraries->generateFromInput($plan->request, $plan->user, (string) Str::uuid());
        } catch (NotEnoughBeachesException) {
            $plans->fail($plan, 'not_enough_beaches');

            return;
        } catch (Throwable $e) {
            report($e);
            $plans->fail($plan, 'error');

            return;
        }

        // A plan without the AI narrative is still shown, but not charged.
        if (($result['metadata']['narrative'] ?? 'llm') === 'fallback') {
            $plans->releaseCredit($plan);
        }
        $result['metadata']['credit_used'] = $plan->credit_charged;
        $result['itinerary_id'] = $plan->id;

        $plan->update([
            'status' => ItineraryPlan::STATUS_DONE,
            'title' => mb_substr((string) ($result['title'] ?? ''), 0, 255) ?: null,
            'result' => $result,
            'finished_at' => now(),
        ]);
    }

    /** Worker timeout or crash: the plan fails and its credit goes back. */
    public function failed(?Throwable $e): void
    {
        if ($plan = ItineraryPlan::find($this->planId)) {
            app(ItineraryPlanService::class)->fail($plan, 'error');
        }
    }
}
