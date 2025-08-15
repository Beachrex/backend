<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;


class Beach extends Model
{
    protected $table = 'beach';

    protected $fillable = [
        'user_id',
        'name',
        'latitude',
        'longitude',
        'status_id',
    ];

    public function translations(): HasMany
    {
        return $this->hasMany(BeachTranslation::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(Status::class);
    }
}
