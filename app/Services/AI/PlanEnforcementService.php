<?php

namespace App\Services\AI;

use App\Models\User;
use Illuminate\Http\JsonResponse;

/**
 * Plan Enforcement Service
 * 
 * Enforces tier-based feature access and returns 402 Payment Required
 * when users attempt to access paid features without proper entitlement.
 * 
 * Source: docs/ai/architecture/api-contracts.md Section 3.2
 */
class PlanEnforcementService
{
    /**
     * Determine user's tier.
     * 
     * For MVP: All users are 'free' tier until payment system is implemented.
     * 
     * @param User|null $user
     * @return string Tier name ('free', 'basic', 'premium', 'enterprise')
     */
    public function determineTier(?User $user): string
    {
        if (!$user) {
            return 'free';
        }
        
        // TODO: Once payment system is implemented, check user's actual tier
        // For now, all authenticated users are 'free' tier
        // Future implementation:
        // return $user->ai_tier ?? 'free';
        
        return 'free';
    }
    
    /**
     * Check if user can access a specific feature.
     * 
     * @param User|null $user
     * @param string $feature Feature name (e.g., 'multi_day_itineraries')
     * @return bool
     */
    public function canAccessFeature(?User $user, string $feature): bool
    {
        $tier = $this->determineTier($user);
        $features = config("ai.tiers.{$tier}.features", []);
        
        return $features[$feature] ?? false;
    }
    
    /**
     * Check if user can request multi-day itinerary.
     * Returns 402 Payment Required response if not allowed.
     * 
     * @param User|null $user
     * @param int $days Number of days
     * @return JsonResponse|null Returns JsonResponse if blocked, null if allowed
     */
    public function checkMultiDayItinerary(?User $user, int $days): ?JsonResponse
    {
        // Single-day itineraries are free
        if ($days < 2) {
            return null;
        }
        
        // Check if user can access multi-day itineraries
        if (!$this->canAccessFeature($user, 'multi_day_itineraries')) {
            return $this->paymentRequiredResponse(
                'multi_day_itineraries',
                'Multi-day itineraries require payment',
                [
                    'type' => 'itinerary',
                    'duration_days' => $days,
                    'price' => $this->calculateItineraryPrice($days),
                    'currency' => 'USD',
                ]
            );
        }
        
        // TODO: Once payment system is implemented, check if user has purchased this specific itinerary
        // For now, if user has access to the feature, allow it
        
        return null;
    }
    
    /**
     * Check if user can request accessibility report.
     * Returns 402 Payment Required response if not allowed.
     * 
     * @param User|null $user
     * @return JsonResponse|null Returns JsonResponse if blocked, null if allowed
     */
    public function checkAccessibilityReport(?User $user): ?JsonResponse
    {
        if (!$this->canAccessFeature($user, 'accessibility_reports')) {
            return $this->paymentRequiredResponse(
                'accessibility_reports',
                'Verified accessibility reports require payment',
                [
                    'type' => 'accessibility_report',
                    'price' => config('ai.pricing.accessibility_report'),
                    'currency' => 'USD',
                ]
            );
        }
        
        return null;
    }
    
    /**
     * Check if user can request activity guide.
     * Returns 402 Payment Required response if not allowed.
     * 
     * @param User|null $user
     * @return JsonResponse|null Returns JsonResponse if blocked, null if allowed
     */
    public function checkActivityGuide(?User $user): ?JsonResponse
    {
        if (!$this->canAccessFeature($user, 'activity_guides')) {
            return $this->paymentRequiredResponse(
                'activity_guides',
                'Expert activity guides require payment',
                [
                    'type' => 'activity_guide',
                    'price' => config('ai.pricing.activity_guide'),
                    'currency' => 'USD',
                ]
            );
        }
        
        return null;
    }
    
    /**
     * Check if user can request multi-day weather forecast.
     * Returns 402 Payment Required response if not allowed.
     * 
     * @param User|null $user
     * @return JsonResponse|null Returns JsonResponse if blocked, null if allowed
     */
    public function checkMultiDayWeather(?User $user): ?JsonResponse
    {
        if (!$this->canAccessFeature($user, 'multi_day_weather')) {
            return $this->paymentRequiredResponse(
                'multi_day_weather',
                'Multi-day weather forecasts require payment',
                [
                    'type' => 'multi_day_weather',
                    'included_in' => 'basic_tier',
                ]
            );
        }
        
        return null;
    }
    
    /**
     * Check beach comparison limit.
     * Returns 402 Payment Required response if limit exceeded.
     * 
     * @param User|null $user
     * @param int $beachCount Number of beaches to compare
     * @return JsonResponse|null Returns JsonResponse if blocked, null if allowed
     */
    public function checkBeachComparisonLimit(?User $user, int $beachCount): ?JsonResponse
    {
        $tier = $this->determineTier($user);
        $maxBeaches = config("ai.tiers.{$tier}.features.beach_comparison_max", 3);
        
        if ($beachCount > $maxBeaches) {
            return $this->paymentRequiredResponse(
                'beach_comparison',
                "Free tier allows comparing up to {$maxBeaches} beaches",
                [
                    'type' => 'beach_comparison',
                    'requested_beaches' => $beachCount,
                    'max_beaches_free' => $maxBeaches,
                    'upgrade_for' => 'unlimited comparisons',
                ]
            );
        }
        
        return null;
    }
    
    /**
     * Generate 402 Payment Required response.
     * 
     * @param string $feature Feature name
     * @param string $message Error message
     * @param array $product Product details
     * @return JsonResponse
     */
    private function paymentRequiredResponse(string $feature, string $message, array $product): JsonResponse
    {
        return response()->json([
            'error' => 'payment_required',
            'message' => $message,
            'product' => $product,
            'purchase_url' => 'https://beachrex.com/pricing', // TODO: Generate specific purchase URL
        ], 402);
    }
    
    /**
     * Calculate itinerary price based on duration.
     * 
     * @param int $days Number of days
     * @return float Price in USD
     */
    private function calculateItineraryPrice(int $days): float
    {
        $pricing = config('ai.pricing.itinerary');
        
        // Find closest pricing tier
        if ($days <= 3) {
            return $pricing[3];
        } elseif ($days <= 7) {
            return $pricing[7];
        } else {
            return $pricing[14];
        }
    }
}

