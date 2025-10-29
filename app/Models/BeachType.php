<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BeachType extends Model
{
    use HasFactory;

    protected $table = 'beach_type';

    protected $fillable = [
        'code',
        'status_id',
        'show_filter',
    ];

    protected $casts = [
        'show_filter' => 'boolean',
    ];

    /**
     * Get all translations for this beach type
     */
    public function translations(): HasMany
    {
        return $this->hasMany(BeachTypeTranslation::class);
    }

    /**
     * Get the status of this beach type
     */
    public function status(): BelongsTo
    {
        return $this->belongsTo(Status::class);
    }

    /**
     * Get all beaches with this beach type
     */
    public function beaches(): HasMany
    {
        return $this->hasMany(Beach::class);
    }

    /**
     * Scope to get only beach types shown in filters
     */
    public function scopeShowInFilter($query)
    {
        return $query->where('show_filter', true);
    }

    /**
     * Scope to get only active beach types
     */
    public function scopeActive($query)
    {
        return $query->where('status_id', 1);
    }

    /**
     * Scope to get beach type by code
     */
    public function scopeByCode($query, string $code)
    {
        return $query->where('code', $code);
    }
}

