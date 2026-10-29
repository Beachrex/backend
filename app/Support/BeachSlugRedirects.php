<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Old beach slugs (`beach_slug_redirects`): an old URL still finds its beach; the frontend then answers with a
 * 301 to the current slug (owner 2026-10-23: "beach-" slugs replaced by slugs of the local name).
 */
class BeachSlugRedirects
{
    /** Beach id for an old slug: the requested language first, then any language. */
    public static function beachId(string $slug, ?string $locale = null): ?int
    {
        if (! Schema::hasTable('beach_slug_redirects')) {
            return null;
        }

        $id = DB::table('beach_slug_redirects')
            ->where('old_slug', $slug)
            ->orderByRaw('CASE WHEN locale = ? THEN 0 ELSE 1 END', [$locale ?? app()->getLocale()])
            ->value('beach_id');

        return $id !== null ? (int) $id : null;
    }
}
