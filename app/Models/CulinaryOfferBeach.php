<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CulinaryOfferBeach extends Model
{
    use HasFactory;

    protected $table = 'culinary_offer_beach';

    protected $fillable = [
        'beach_id',
        'culinary_offer_id',
        'status_id',
    ];

    /**
     * Get the beach
     */
    public function beach(): BelongsTo
    {
        return $this->belongsTo(Beach::class);
    }

    /**
     * Get the culinary offer
     */
    public function culinaryOffer(): BelongsTo
    {
        return $this->belongsTo(CulinaryOffer::class);
    }

    /**
     * Get the status
     */
    public function status(): BelongsTo
    {
        return $this->belongsTo(Status::class);
    }

    /**
     * Scope to get only active records
     */
    public function scopeActive($query)
    {
        return $query->where('status_id', 1);
    }
}

