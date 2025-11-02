<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class River extends Model
{
    use HasFactory;

    protected $table = 'river';

    protected $fillable = [
        'code',
        'status_id',
    ];

    protected $appends = ['name'];

    /**
     * Get the English name from translations
     */
    public function getNameAttribute(): string
    {
        $translation = $this->translations()->where('locale', 'en')->first();
        return $translation?->name ?? $this->code;
    }

    /**
     * Get the status of this river
     */
    public function status(): BelongsTo
    {
        return $this->belongsTo(Status::class);
    }

    /**
     * Get all translations for this river
     */
    public function translations(): HasMany
    {
        return $this->hasMany(RiverTranslation::class);
    }

    /**
     * Get all beaches on this river
     */
    public function beaches(): HasMany
    {
        return $this->hasMany(Beach::class);
    }
}

