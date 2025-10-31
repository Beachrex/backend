<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UnderwaterCharacteristicBeach extends Model
{
    use HasFactory;

    protected $table = 'underwater_characteristic_beach';

    protected $fillable = [
        'beach_id',
        'underwater_characteristic_id',
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
     * Get the characteristic
     */
    public function underwaterCharacteristic(): BelongsTo
    {
        return $this->belongsTo(UnderwaterCharacteristic::class);
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

