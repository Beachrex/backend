<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Island extends Model
{
    use HasFactory;

    protected $table = 'island';

    protected $fillable = [
        'name',
        'slug',
        'status_id',
    ];

    /**
     * Get the status of this island
     */
    public function status(): BelongsTo
    {
        return $this->belongsTo(Status::class);
    }

    /**
     * Get all beaches on this island
     */
    public function beaches(): HasMany
    {
        return $this->hasMany(Beach::class);
    }

    /**
     * Scope to get only active islands
     */
    public function scopeActive($query)
    {
        return $query->where('status_id', 1);
    }
}

