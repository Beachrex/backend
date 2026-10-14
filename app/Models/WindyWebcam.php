<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** TASK-006: local copy of one Windy webcam's metadata. Image URLs are never stored. */
class WindyWebcam extends Model
{
    protected $table = 'windy_webcams';
    protected $primaryKey = 'windy_id';
    public $incrementing = false;
    protected $keyType = 'int';

    protected $guarded = ['location'];

    protected $casts = [
        'categories' => 'array',
        'raw' => 'array',
        'lat' => 'float',
        'lon' => 'float',
        'last_updated_on' => 'datetime',
        'first_seen_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'fetched_at' => 'datetime',
        'missing_since' => 'datetime',
        'embed_checked_at' => 'datetime',
    ];

    public function placeWebcams(): HasMany
    {
        return $this->hasMany(PlaceWebcam::class, 'windy_id', 'windy_id');
    }

    /** Usable on the site: active on Windy, still returned by the API, and embeddable. */
    public function isUsable(): bool
    {
        return $this->status === 'active' && $this->missing_since === null
            && (! config('webcams.matching.require_embed_ok') || $this->embed_status === 'ok');
    }

    public function publicUrl(): string
    {
        return str_replace('{id}', (string) $this->windy_id, config('webcams.public_url'));
    }
}
