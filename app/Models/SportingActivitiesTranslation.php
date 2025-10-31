<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SportingActivitiesTranslation extends Model
{
    use HasFactory;

    protected $table = 'sporting_activities_translation';

    protected $fillable = [
        'sporting_activities_id',
        'locale',
        'name',
        'slug',
    ];

    /**
     * Get the activity that owns this translation
     */
    public function sportingActivities(): BelongsTo
    {
        return $this->belongsTo(SportingActivities::class);
    }
}

