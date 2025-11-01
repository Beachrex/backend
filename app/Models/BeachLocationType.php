<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class BeachLocationType extends Model
{
    use HasFactory;

    protected $table = 'beach_location_type';

    protected $fillable = [
        'code',
        'status_id',
    ];

    /**
     * Get all translations for this beach location type
     */
    public function translations(): HasMany
    {
        return $this->hasMany(BeachLocationTypeTranslation::class);
    }

    /**
     * Get the status of this beach location type
     */
    public function status(): BelongsTo
    {
        return $this->belongsTo(Status::class);
    }

    /**
     * Get all beaches with this beach location type
     */
    public function beaches(): BelongsToMany
    {
        return $this->belongsToMany(Beach::class, 'beach_beach_location_type');
    }

    /**
     * Scope to get only active beach location types
     */
    public function scopeActive($query)
    {
        return $query->where('status_id', 1);
    }
}

