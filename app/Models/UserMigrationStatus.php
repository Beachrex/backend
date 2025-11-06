<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserMigrationStatus extends Model
{
    protected $table = 'user_migration_status';

    protected $fillable = [
        'old_user_id',
        'new_user_id',
        'status',
        'error_message',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'new_user_id');
    }
}
