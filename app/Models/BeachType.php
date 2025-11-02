<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BeachType extends Model
{
    use HasFactory;

    protected $table = 'beach_type';

    protected $fillable = [
        'code',
        'status_id',
        'show_filter',
        'icon',
        'filter_order',
        'input_type',
    ];

    protected $casts = [
        'show_filter' => 'boolean',
    ];

    protected $appends = ['name'];

    /**
     * Get all translations for this beach type
     */
    public function translations(): HasMany
    {
        return $this->hasMany(BeachTypeTranslation::class);
    }

    /**
     * Get the name attribute (English translation for admin display)
     */
    public function getNameAttribute(): string
    {
        $translation = $this->translations()->where('locale', 'en')->first();
        return $translation?->name ?? $this->code;
    }

    /**
     * Get the status of this beach type
     */
    public function status(): BelongsTo
    {
        return $this->belongsTo(Status::class);
    }

    /**
     * Scope to get only active beach types
     */
    public function scopeActive($query)
    {
        return $query->where('status_id', 1);
    }

    /**
     * Scope to get only beach types that should be shown in filters
     */
    public function scopeShowInFilter($query)
    {
        return $query->where('show_filter', true);
    }

    /**
     * Get all beaches with this beach type
     */
    public function beaches(): HasMany
    {
        return $this->hasMany(Beach::class);
    }

    /**
     * Scope to get beach type by code
     */
    public function scopeByCode($query, string $code)
    {
        return $query->where('code', $code);
    }
}

