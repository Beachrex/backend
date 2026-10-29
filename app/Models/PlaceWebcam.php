<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** TASK-006: a Windy webcam assigned to a place (beach row), with its score and review state. */
class PlaceWebcam extends Model
{
    public const SOURCE_LEGACY = 'legacy';
    public const SOURCE_MATCHER = 'matcher';
    public const SOURCE_MANUAL = 'manual';

    public const AUTO_APPROVED = 'auto_approved';
    public const PENDING = 'pending';
    public const APPROVED = 'approved';
    public const REJECTED = 'rejected';

    /** Statuses shown on the public site. */
    public const VISIBLE = [self::AUTO_APPROVED, self::APPROVED];

    protected $table = 'place_webcams';

    protected $guarded = ['id'];

    protected $casts = [
        'score_breakdown' => 'array',
        'is_primary' => 'boolean',
        'reviewed_at' => 'datetime',
    ];

    public function place(): BelongsTo
    {
        return $this->belongsTo(Beach::class, 'place_id');
    }

    public function webcam(): BelongsTo
    {
        return $this->belongsTo(WindyWebcam::class, 'windy_id', 'windy_id');
    }

    /** Rows the matcher may replace; reviewed or manual rows are never touched. */
    public function isMatcherOwned(): bool
    {
        return in_array($this->source, [self::SOURCE_MATCHER, self::SOURCE_LEGACY], true)
            && in_array($this->review_status, [self::AUTO_APPROVED, self::PENDING], true)
            && $this->reviewed_at === null;
    }
}
