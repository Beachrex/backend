<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class RecommendedTransportType extends Model
{
    use HasFactory;

    protected $table = 'recommended_transport_type';

    protected $fillable = [
        'code',
        'status_id',
        'show_filter',
        'icon',
        'filter_order',
        'input_type',
    ];

    protected $casts = [
        'show_filter' => 'boolean',
    ];

    /**
     * Get all translations for this transport type
     */
    public function translations(): HasMany
    {
        return $this->hasMany(RecommendedTransportTypeTranslation::class);
    }

    /**
     * Get the status of this transport type
     */
    public function status(): BelongsTo
    {
        return $this->belongsTo(Status::class);
    }

    /**
     * Get all beaches with this transport type (through pivot table)
     */
    public function beaches(): BelongsToMany
    {
        return $this->belongsToMany(Beach::class, 'recommended_transport_type_beach')
            ->withPivot('status_id')
            ->withTimestamps();
    }

    /**
     * Scope to get only active transport types
     */
    public function scopeActive($query)
    {
        return $query->where('status_id', 1);
    }

    /**
     * Scope to get transport type by code
     */
    public function scopeByCode($query, string $code)
    {
        return $query->where('code', $code);
    }
    /**
     * Scope to get only items that should be shown in filters
     */
    public function scopeShowInFilter($query)
    {
        return $query->where('show_filter', true);
    }
}


