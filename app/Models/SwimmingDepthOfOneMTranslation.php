<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SwimmingDepthOfOneMTranslation extends Model
{
    use HasFactory;

    protected $table = 'swimming_depth_of_one_m_translation';

    protected $fillable = [
        'swimming_depth_of_one_m_id',
        'locale',
        'name',
        'slug',
    ];

    /**
     * Get the swimming depth that owns this translation
     */
    public function swimmingDepthOfOneM(): BelongsTo
    {
        return $this->belongsTo(SwimmingDepthOfOneM::class);
    }
}

