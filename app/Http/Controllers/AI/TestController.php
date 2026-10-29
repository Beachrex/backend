<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Services\AI\PlanEnforcementService;
use App\Services\AI\LLMService;
use App\Services\AI\Exceptions\LLMException;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

/**
 * AI Test Controller
 *
 * Temporary controller for testing AI middleware and plan enforcement.
 * This will be replaced by ChatController in Step 5.
 */
class TestController extends Controller
{
    public function __construct(
        private PlanEnforcementService $planEnforcement,
        private LLMService $llm
    ) {}

    /**
     * Test endpoint for AI chat.
     *
     * This is a test implementation with real LLM integration.
     * Full implementation will be in Step 5 (ChatController).
     */
    public function chat(Request $request): JsonResponse
    {
        $user = $request->user();
        $tier = $request->attributes->get('ai_tier', 'free');
        $message = $request->input('message', '');

        // Simulate intent detection (in real implementation, this comes from LLM)
        $intent = $this->detectIntent($message);

        // Check feature access based on intent
        $blockResponse = match($intent) {
            'itinerary_request' => $this->planEnforcement->checkMultiDayItinerary($user, 3),
            'accessibility_report' => $this->planEnforcement->checkAccessibilityReport($user),
            'activity_guide' => $this->planEnforcement->checkActivityGuide($user),
            'multi_day_weather' => $this->planEnforcement->checkMultiDayWeather($user),
            default => null,
        };

        // If feature is blocked, return 402 Payment Required
        if ($blockResponse) {
            return $blockResponse;
        }

        // Call LLM for response
        try {
            $systemPrompt = "You are a helpful beach discovery assistant for Beachrex. " .
                "Help users find beaches in Croatia, Slovenia, and Montenegro. " .
                "Keep responses concise and friendly.";

            $response = $this->llm->chat($message, $systemPrompt, [
                'temperature' => 0.7,
                'maxTokens' => 500,
            ]);

            // Public endpoint that calls the LLM: its cost counts toward the free daily limit like the chat.
            \App\Models\AiUsage::create([
                'user_id' => $user?->id,
                'tier' => $tier,
                'event_type' => 'test',
                'llm_provider' => $response->provider,
                'llm_model' => \Illuminate\Support\Str::limit((string) $response->model, 50, ''),
                'tokens_input' => $response->inputTokens,
                'tokens_output' => $response->outputTokens,
                'tokens_total' => $response->totalTokens,
                'cost_usd' => $response->costUsd,
                'ip_address' => hash('sha256', (string) $request->ip()),
            ]);

            return response()->json([
                'session_id' => $request->header('X-Session-ID', 'test-session'),
                'message' => $response->getContent(),
                'intent' => $intent,
                'tier' => $tier,
                'usage' => [
                    'input_tokens' => $response->inputTokens,
                    'output_tokens' => $response->outputTokens,
                    'total_tokens' => $response->totalTokens,
                    'cost_usd' => $response->costUsd,
                ],
                'model' => $response->model,
                'provider' => $response->provider,
                'timestamp' => now()->toIso8601String(),
            ]);

        } catch (LLMException $e) {
            Log::error('LLM error in test endpoint', [
                'error' => $e->getMessage(),
                'provider' => $e->provider,
                'status_code' => $e->statusCode,
            ]);

            return response()->json([
                'error' => 'llm_error',
                'message' => 'Failed to process your request. Please try again.',
                'details' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Simple intent detection (stub).
     *
     * In real implementation, intent is detected by LLM.
     */
    private function detectIntent(string $message): string
    {
        $message = strtolower($message);

        if (str_contains($message, 'itinerary') || str_contains($message, 'trip')) {
            return 'itinerary_request';
        }

        if (str_contains($message, 'accessibility') || str_contains($message, 'wheelchair')) {
            return 'accessibility_report';
        }

        if (str_contains($message, 'activity') || str_contains($message, 'guide')) {
            return 'activity_guide';
        }

        if (str_contains($message, 'forecast') || str_contains($message, 'weather')) {
            return 'multi_day_weather';
        }

        return 'beach_search';
    }
}

