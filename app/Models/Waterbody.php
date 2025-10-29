<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Waterbody extends Model
{
    protected $table = 'waterbody';

    protected $fillable = [
        'code',
        'status_id',
        'show_filter',
    ];

    protected $casts = [
        'show_filter' => 'boolean',
    ];

    /**
     * Get all translations for this waterbody
     */
    public function translations(): HasMany
    {
        return $this->hasMany(WaterbodyTranslation::class);
    }

    /**
     * Get the status of this waterbody
     */
    public function status(): BelongsTo
    {
        return $this->belongsTo(Status::class);
    }

    /**
     * Get all beaches with this waterbody type
     */
    public function beaches(): HasMany
    {
        return $this->hasMany(Beach::class);
    }

    /**
     * Scope to get only waterbodies that should be shown in filters
     */
    public function scopeShowInFilter($query)
    {
        return $query->where('show_filter', true);
    }

    /**
     * Scope to get only active waterbodies
     */
    public function scopeActive($query)
    {
        return $query->where('status_id', 1);
    }

    /**
     * Scope to get waterbody by code
     */
    public function scopeByCode($query, string $code)
    {
        return $query->where('code', $code);
    }
}

