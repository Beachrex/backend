<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserTypeTranslation extends Model
{
    use HasFactory;

    protected $table = 'user_type_translation';

    protected $fillable = [
        'user_type_id',
        'locale',
        'name',
        'slug',
    ];

    /**
     * Get the user type that owns this translation
     */
    public function userType(): BelongsTo
    {
        return $this->belongsTo(UserType::class);
    }
}

