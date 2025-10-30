<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BeachInclination extends Model
{
    use HasFactory;

    protected $table = 'beach_inclination';

    protected $fillable = [
        'code',
        'status_id',
    ];

    /**
     * Get all translations for this beach inclination
     */
    public function translations(): HasMany
    {
        return $this->hasMany(BeachInclinationTranslation::class);
    }

    /**
     * Get the status of this beach inclination
     */
    public function status(): BelongsTo
    {
        return $this->belongsTo(Status::class);
    }

    /**
     * Get all beaches with this beach inclination
     */
    public function beaches(): HasMany
    {
        return $this->hasMany(Beach::class);
    }

    /**
     * Scope to get only active beach inclinations
     */
    public function scopeActive($query)
    {
        return $query->where('status_id', 1);
    }

    /**
     * Scope to get beach inclination by code
     */
    public function scopeByCode($query, string $code)
    {
        return $query->where('code', $code);
    }
}

