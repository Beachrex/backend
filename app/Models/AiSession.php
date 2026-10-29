<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiSession extends Model
{
    use HasFactory, HasUuids;

    /**
     * The table associated with the model.
     */
    protected $table = 'ai_sessions';

    /**
     * Indicates if the IDs are auto-incrementing.
     */
    public $incrementing = false;

    /**
     * The data type of the auto-incrementing ID.
     */
    protected $keyType = 'string';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'user_id',
        'tier',
        'locale',
        'messages_count',
        'tokens_used',
        'ip_address',
        'user_agent',
        'last_activity_at',
        'expires_at',
        'engine',
        'page_context',
    ];

    /**
     * The attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'messages_count' => 'integer',
            'tokens_used' => 'integer',
            'last_activity_at' => 'datetime',
            'expires_at' => 'datetime',
            'page_context' => 'array',
        ];
    }

    /**
     * Get the user that owns this session (null for anonymous sessions).
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get all messages in this session.
     */
    public function messages(): HasMany
    {
        return $this->hasMany(AiMessage::class, 'session_id');
    }

    /**
     * Get all usage records for this session.
     */
    public function usageRecords(): HasMany
    {
        return $this->hasMany(AiUsage::class, 'session_id');
    }

    /**
     * Check if session is expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    /**
     * Check if session is anonymous (no user_id).
     */
    public function isAnonymous(): bool
    {
        return $this->user_id === null;
    }

    /**
     * Update last activity timestamp and extend expiration.
     */
    public function touch($attribute = null): bool
    {
        $this->last_activity_at = now();
        $this->expires_at = now()->addMinutes(30); // 30-minute session timeout
        
        return parent::touch($attribute);
    }
}

