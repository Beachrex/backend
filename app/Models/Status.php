<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Status extends Model
{
    public $timestamps = false; // because the `status` table has no timestamps

    protected $table = 'status';

    protected $fillable = ['name'];

    public function beaches(): HasMany
    {
        return $this->hasMany(Beach::class, 'status_id');
    }
}
