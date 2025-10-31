<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserTypeBeach extends Model
{
    use HasFactory;

    protected $table = 'user_type_beach';

    protected $fillable = [
        'beach_id',
        'user_type_id',
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
     * Get the user type
     */
    public function userType(): BelongsTo
    {
        return $this->belongsTo(UserType::class);
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

