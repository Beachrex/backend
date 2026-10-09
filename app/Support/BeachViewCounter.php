<?php

namespace App\Support;

use App\Models\Beach;

/**
 * Beach view counter (`beach.beach_views`, used for "most visited").
 *
 * One UPDATE by id, without loading the model: no relations, no model events (Scout reindexing), and
 * `updated_at` stays the content's last edit.
 */
final class BeachViewCounter
{
    /** @return bool false when no active beach has this id */
    public static function increment(int $beachId): bool
    {
        return Beach::query()
            ->whereKey($beachId)
            ->where('status_id', 1)
            ->toBase()
            ->increment('beach_views') > 0;
    }
}
