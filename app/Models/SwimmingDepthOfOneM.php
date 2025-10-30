<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SwimmingDepthOfOneM extends Model
{
    use HasFactory;

    protected $table = 'swimming_depth_of_one_m';

    protected $fillable = [
        'code',
        'status_id',
    ];

    /**
     * Get all translations for this swimming depth
     */
    public function translations(): HasMany
    {
        return $this->hasMany(SwimmingDepthOfOneMTranslation::class);
    }

    /**
     * Get the status of this swimming depth
     */
    public function status(): BelongsTo
    {
        return $this->belongsTo(Status::class);
    }

    /**
     * Get all beaches with this swimming depth
     */
    public function beaches(): HasMany
    {
        return $this->hasMany(Beach::class);
    }

    /**
     * Scope to get only active swimming depths
     */
    public function scopeActive($query)
    {
        return $query->where('status_id', 1);
    }

    /**
     * Scope to get swimming depth by code
     */
    public function scopeByCode($query, string $code)
    {
        return $query->where('code', $code);
    }
}

