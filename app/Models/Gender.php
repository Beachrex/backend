<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Gender extends Model
{
    use HasFactory;

    protected $table = 'gender';

    protected $fillable = [
        'name',
    ];

    protected $appends = ['translated_name'];

    /**
     * Get the English name from translations
     */
    public function getTranslatedNameAttribute(): string
    {
        $translation = $this->translations()->where('locale', 'en')->first();
        return $translation?->name ?? $this->name;
    }

    /**
     * Get all translations for this gender
     */
    public function translations(): HasMany
    {
        return $this->hasMany(GenderTranslation::class);
    }
}

