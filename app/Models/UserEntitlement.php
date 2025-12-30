<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserEntitlement extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'itinerary_3day_credits',
        'itinerary_7day_credits',
        'itinerary_14day_credits',
    ];

    protected $casts = [
        'itinerary_3day_credits' => 'integer',
        'itinerary_7day_credits' => 'integer',
        'itinerary_14day_credits' => 'integer',
    ];

    /**
     * Get the user that owns the entitlement.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get credits for a specific product type.
     */
    public function getCredits(string $productType): int
    {
        $field = $productType . '_credits';
        return $this->$field ?? 0;
    }

    /**
     * Check if user has credits for a specific product type.
     */
    public function hasCredits(string $productType): bool
    {
        return $this->getCredits($productType) > 0;
    }

    /**
     * Increment credits for a specific product type.
     */
    public function incrementCredits(string $productType, int $amount = 1): void
    {
        $field = $productType . '_credits';
        $this->increment($field, $amount);
    }

    /**
     * Decrement credits for a specific product type (atomic).
     */
    public function decrementCredits(string $productType, int $amount = 1): bool
    {
        $field = $productType . '_credits';
        
        // Use atomic decrement to prevent race conditions
        $affected = $this->where('id', $this->id)
            ->where($field, '>=', $amount)
            ->decrement($field, $amount);
        
        if ($affected > 0) {
            $this->refresh();
            return true;
        }
        
        return false;
    }
}

