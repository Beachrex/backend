<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class CulinaryOffer extends Model
{
    use HasFactory;

    protected $table = 'culinary_offer';

    protected $fillable = [
        'code',
        'status_id',
    ];

    /**
     * Get all translations for this culinary offer
     */
    public function translations(): HasMany
    {
        return $this->hasMany(CulinaryOfferTranslation::class);
    }

    /**
     * Get the status of this culinary offer
     */
    public function status(): BelongsTo
    {
        return $this->belongsTo(Status::class);
    }

    /**
     * Get all beaches with this culinary offer (through pivot table)
     */
    public function beaches(): BelongsToMany
    {
        return $this->belongsToMany(Beach::class, 'culinary_offer_beach')
            ->withPivot('status_id')
            ->withTimestamps();
    }

    /**
     * Scope to get only active culinary offers
     */
    public function scopeActive($query)
    {
        return $query->where('status_id', 1);
    }

    /**
     * Scope to get culinary offer by code
     */
    public function scopeByCode($query, string $code)
    {
        return $query->where('code', $code);
    }
}

