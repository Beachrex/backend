<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Accessibility extends Model
{
    use HasFactory;

    protected $table = 'accessibility';

    protected $fillable = [
        'code',
        'status_id',
    ];

    /**
     * Get all translations for this accessibility
     */
    public function translations(): HasMany
    {
        return $this->hasMany(AccessibilityTranslation::class);
    }

    /**
     * Get the status of this accessibility
     */
    public function status(): BelongsTo
    {
        return $this->belongsTo(Status::class);
    }

    /**
     * Get all beaches with this accessibility
     */
    public function beaches(): HasMany
    {
        return $this->hasMany(Beach::class);
    }

    /**
     * Scope to get only active accessibility options
     */
    public function scopeActive($query)
    {
        return $query->where('status_id', 1);
    }

    /**
     * Scope to get accessibility by code
     */
    public function scopeByCode($query, string $code)
    {
        return $query->where('code', $code);
    }
}

