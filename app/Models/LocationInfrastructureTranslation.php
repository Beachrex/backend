<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LocationInfrastructureTranslation extends Model
{
    use HasFactory;

    protected $table = 'location_infrastructure_translation';

    protected $fillable = [
        'location_infrastructure_id',
        'locale',
        'name',
        'slug',
    ];

    /**
     * Get the infrastructure that owns this translation
     */
    public function locationInfrastructure(): BelongsTo
    {
        return $this->belongsTo(LocationInfrastructure::class);
    }
}

