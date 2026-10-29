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

    /** Same rule as isUsable(), as a query. */
    public function scopeUsable($query)
    {
        $query->where('status', 'active')->whereNull('missing_since');
        if (config('webcams.matching.require_embed_ok')) {
            $query->where('embed_status', 'ok');
        }

        return $query;
    }

    /** SQL condition on alias `ww` for raw queries (PostGIS lists). */
    public static function usableSql(string $alias = 'ww'): string
    {
        return "{$alias}.status = 'active' AND {$alias}.missing_since IS NULL"
            . (config('webcams.matching.require_embed_ok') ? " AND {$alias}.embed_status = 'ok'" : '');
    }

    /** The iframe src the website and the admin preview use (same as Windy's player.js builds). */
    public function playerUrl(): string
    {
        return config('webcams.player_base_url') . '?' . http_build_query([
            'webcamId' => $this->windy_id, 'playerType' => 'day', 'loop' => 0, 'autoPlay' => 0, 'interactive' => 1,
        ]);
    }

    public function publicUrl(): string
    {
        return str_replace('{id}', (string) $this->windy_id, config('webcams.public_url'));
    }
}
