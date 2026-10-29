<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiUsage extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'ai_usage';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'user_id',
        'session_id',
        'tier',
        'event_type',
        'llm_provider',
        'llm_model',
        'tokens_input',
        'tokens_output',
        'tokens_total',
        'cost_usd',
        'latency_ms',
        'cache_hit',
        'beaches_fetched',
        'beaches_returned',
        'context_truncated',
        'locale',
        'user_agent',
        'ip_address',
        'request_id',
    ];

    /**
     * The attributes that should be cast.
     */
    protected static function booted(): void
    {
        // The circuit breaker's daily sums are cached; a new cost makes the next check read the real total.
        static::created(fn () => \App\Services\AI\AiDailySpend::forget());
    }

    protected function casts(): array
    {
        return [
            'tokens_input' => 'integer',
            'tokens_output' => 'integer',
            'tokens_total' => 'integer',
            'cost_usd' => 'decimal:6',
            'latency_ms' => 'integer',
            'cache_hit' => 'boolean',
            'beaches_fetched' => 'integer',
            'beaches_returned' => 'integer',
            'context_truncated' => 'boolean',
        ];
    }

    /**
     * Get the user that owns this usage record.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the session that owns this usage record.
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(AiSession::class, 'session_id');
    }
}

