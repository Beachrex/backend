<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccessibilityTranslation extends Model
{
    use HasFactory;

    protected $table = 'accessibility_translation';

    protected $fillable = [
        'accessibility_id',
        'locale',
        'name',
        'slug',
    ];

    /**
     * Get the accessibility that owns this translation
     */
    public function accessibility(): BelongsTo
    {
        return $this->belongsTo(Accessibility::class);
    }
}

