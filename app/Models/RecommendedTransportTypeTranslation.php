<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecommendedTransportTypeTranslation extends Model
{
    use HasFactory;

    protected $table = 'recommended_transport_type_translation';

    protected $fillable = [
        'recommended_transport_type_id',
        'locale',
        'name',
        'slug',
    ];

    /**
     * Get the transport type that owns this translation
     */
    public function recommendedTransportType(): BelongsTo
    {
        return $this->belongsTo(RecommendedTransportType::class);
    }
}

