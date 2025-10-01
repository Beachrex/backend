<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Continent extends Model
{
    protected $table = 'continents';
    
    // Primary key is 'code' instead of 'id'
    protected $primaryKey = 'code';
    
    // Primary key is not auto-incrementing
    public $incrementing = false;
    
    // Primary key is a string
    protected $keyType = 'string';
    
    // No timestamps in continents table
    public $timestamps = false;

    protected $fillable = [
        'code',
        'name',
    ];

    /**
     * Get all beaches for this continent
     */
    public function beaches(): HasMany
    {
        return $this->hasMany(Beach::class, 'continent_code', 'code');
    }

    /**
     * Scope to get only continents that have beaches
     */
    public function scopeWithBeaches($query)
    {
        return $query->whereHas('beaches');
    }
}
