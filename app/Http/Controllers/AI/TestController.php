<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Services\AI\PlanEnforcementService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * AI Test Controller
 * 
 * Temporary controller for testing AI middleware and plan enforcement.
 * This will be replaced by ChatController in Step 5.
 */
class TestController extends Controller
{
    public function __construct(
        private PlanEnforcementService $planEnforcement
    ) {}
    
    /**
     * Test endpoint for AI chat (stub).
     * 
     * This is a minimal stub to test middleware and plan enforcement.
     * Full implementation will be in Step 5.
     */
    public function chat(Request $request): JsonResponse
    {
        $user = $request->user();
        $tier = $request->attributes->get('ai_tier', 'free');
        
        // Simulate intent detection (in real implementation, this comes from LLM)
        $message = $request->input('message', '');
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
        
        // Success response (stub)
        return response()->json([
            'session_id' => $request->header('X-Session-ID', 'test-session'),
            'message' => 'This is a test response. Full AI implementation coming in Step 5.',
            'intent' => $intent,
            'tier' => $tier,
            'timestamp' => now()->toIso8601String(),
        ]);
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

