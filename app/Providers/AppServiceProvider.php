<?php

namespace App\Providers;

use App\Events\AI\AiRequestCompleted;
use App\Events\AI\AiRequestFailed;
use App\Listeners\AI\TrackAiMetrics;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // One meter per HTTP request / queue job: the cost of every LLM call, also of calls before a failure.
        $this->app->scoped(\App\Services\AI\AiSpendMeter::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register AI metrics tracking listeners
        Event::listen(AiRequestCompleted::class, [TrackAiMetrics::class, 'handleCompleted']);
        Event::listen(AiRequestFailed::class, [TrackAiMetrics::class, 'handleFailed']);
    }
}
