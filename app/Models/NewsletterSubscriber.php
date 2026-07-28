<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NewsletterSubscriber extends Model
{
    protected $table = 'newsletter_subscribers';

    protected $fillable = [
        'email',
        'status_id',
        'is_synced',
        'consent_accepted',
        'subscribed_at',
        'synced_at',
        'source',
        'ip_address',
        'user_agent',
        'locale',
    ];

    protected $casts = [
        'is_synced' => 'boolean',
        'consent_accepted' => 'boolean',
        'subscribed_at' => 'datetime',
        'synced_at' => 'datetime',
    ];

    /**
     * Normalize email to lowercase on set for consistent uniqueness.
     */
    public function setEmailAttribute(?string $value): void
    {
        $this->attributes['email'] = $value ? strtolower(trim($value)) : $value;
    }

    /**
     * Get the status (active/unsubscribed) of this subscriber.
     */
    public function status(): BelongsTo
    {
        return $this->belongsTo(Status::class);
    }

    /**
     * Scope to get only active (subscribed) subscribers.
     */
    public function scopeActive($query)
    {
        return $query->where('status_id', 1);
    }

    /**
     * Scope to get subscribers not yet synced to the ESP.
     */
    public function scopeNotSynced($query)
    {
        return $query->where('is_synced', false);
    }

    /**
     * Scope to get subscribers that have been synced to the ESP.
     */
    public function scopeSynced($query)
    {
        return $query->where('is_synced', true);
    }

    /**
     * Scope to filter by consent accepted (GDPR).
     */
    public function scopeWithConsent($query)
    {
        return $query->where('consent_accepted', true);
    }
}
