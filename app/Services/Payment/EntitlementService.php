<?php

namespace App\Services\Payment;

use App\Models\User;
use App\Models\UserEntitlement;
use App\Models\Purchase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EntitlementService
{
    /**
     * Grant credits to a user for a specific product type.
     *
     * @param User $user
     * @param string $productType
     * @param int $credits
     * @return UserEntitlement
     */
    public function grantCredits(User $user, string $productType, int $credits = 1): UserEntitlement
    {
        $entitlement = $user->entitlements()->firstOrCreate(
            ['user_id' => $user->id],
            [
                'itinerary_3day_credits' => 0,
                'itinerary_7day_credits' => 0,
                'itinerary_14day_credits' => 0,
            ]
        );

        $entitlement->incrementCredits($productType, $credits);

        Log::info('Credits granted', [
            'user_id' => $user->id,
            'product_type' => $productType,
            'credits' => $credits,
        ]);

        return $entitlement->fresh();
    }

    /**
     * Revoke credits from a user for a specific product type.
     *
     * @param User $user
     * @param string $productType
     * @param int $credits
     * @return UserEntitlement|null
     */
    public function revokeCredits(User $user, string $productType, int $credits = 1): ?UserEntitlement
    {
        $entitlement = $user->entitlements;

        if (!$entitlement) {
            Log::warning('Attempted to revoke credits from user with no entitlements', [
                'user_id' => $user->id,
                'product_type' => $productType,
            ]);
            return null;
        }

        // Decrement credits (can go negative to track debt)
        $field = $productType . '_credits';
        $entitlement->decrement($field, $credits);

        Log::info('Credits revoked', [
            'user_id' => $user->id,
            'product_type' => $productType,
            'credits' => $credits,
            'remaining' => $entitlement->fresh()->$field,
        ]);

        return $entitlement->fresh();
    }

    /**
     * Check if user has credits for a specific product type.
     *
     * @param User $user
     * @param string $productType
     * @return bool
     */
    public function hasCredits(User $user, string $productType): bool
    {
        $entitlement = $user->entitlements;

        if (!$entitlement) {
            return false;
        }

        return $entitlement->hasCredits($productType);
    }

    /**
     * Get credit balance for a specific product type.
     *
     * @param User $user
     * @param string $productType
     * @return int
     */
    public function getCredits(User $user, string $productType): int
    {
        $entitlement = $user->entitlements;

        if (!$entitlement) {
            return 0;
        }

        return $entitlement->getCredits($productType);
    }

    /**
     * Decrement credits atomically (prevents race conditions).
     *
     * @param User $user
     * @param string $productType
     * @param int $credits
     * @return bool True if credits were decremented, false if insufficient credits
     */
    public function decrementCredits(User $user, string $productType, int $credits = 1): bool
    {
        return DB::transaction(function () use ($user, $productType, $credits) {
            $entitlement = $user->entitlements()->lockForUpdate()->first();

            if (!$entitlement) {
                Log::warning('Attempted to decrement credits for user with no entitlements', [
                    'user_id' => $user->id,
                    'product_type' => $productType,
                ]);
                return false;
            }

            $success = $entitlement->decrementCredits($productType, $credits);

            if ($success) {
                Log::info('Credits decremented', [
                    'user_id' => $user->id,
                    'product_type' => $productType,
                    'credits' => $credits,
                    'remaining' => $entitlement->getCredits($productType),
                ]);
            } else {
                Log::warning('Insufficient credits for decrement', [
                    'user_id' => $user->id,
                    'product_type' => $productType,
                    'requested' => $credits,
                    'available' => $entitlement->getCredits($productType),
                ]);
            }

            return $success;
        });
    }

    /**
     * Get all entitlements for a user.
     *
     * @param User $user
     * @return array
     */
    public function getAllEntitlements(User $user): array
    {
        $entitlement = $user->entitlements;

        if (!$entitlement) {
            return [
                'itinerary_3day_credits' => 0,
                'itinerary_7day_credits' => 0,
                'itinerary_14day_credits' => 0,
            ];
        }

        return [
            'itinerary_3day_credits' => $entitlement->itinerary_3day_credits,
            'itinerary_7day_credits' => $entitlement->itinerary_7day_credits,
            'itinerary_14day_credits' => $entitlement->itinerary_14day_credits,
        ];
    }

    /**
     * Map duration days to product type.
     *
     * @param int $days
     * @return string
     */
    public function mapDaysToProductType(int $days): string
    {
        if ($days <= 3) {
            return 'itinerary_3day';
        } elseif ($days <= 7) {
            return 'itinerary_7day';
        } else {
            return 'itinerary_14day';
        }
    }

    /**
     * Get user's purchase history.
     *
     * @param User $user
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getPurchaseHistory(User $user)
    {
        return $user->purchases()
            ->orderBy('created_at', 'desc')
            ->get();
    }
}

