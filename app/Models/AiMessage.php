<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiMessage extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'ai_messages';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'session_id',
        'role',
        'content',
        'intent',
        'context',
        'tokens_input',
        'tokens_output',
        'tokens_total',
    ];

    /**
     * The attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'context' => 'array',
            'tokens_input' => 'integer',
            'tokens_output' => 'integer',
            'tokens_total' => 'integer',
        ];
    }

    /**
     * Get the session that owns this message.
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(AiSession::class, 'session_id');
    }

    /**
     * Check if this is a user message.
     */
    public function isUserMessage(): bool
    {
        return $this->role === 'user';
    }

    /**
     * Check if this is an assistant message.
     */
    public function isAssistantMessage(): bool
    {
        return $this->role === 'assistant';
    }

    /**
     * Check if this is a system message.
     */
    public function isSystemMessage(): bool
    {
        return $this->role === 'system';
    }
}

