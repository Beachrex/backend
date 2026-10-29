<?php

namespace App\Support;

use App\Models\Beach;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * The krpano 360° tour of a beach (`img/{beaches|beaches_mobile}/{folder}/vtour/tour.html`).
 *
 * Owner report 2026-10-29: a beach can have the 360° panorama photo but no generated tour (Plage du Port Vieux,
 * 3247). Then the API returns no `tour_url`; the website shows the panorama in its own 360° viewer instead.
 * Whether the tour exists is stored in `beach.has_vtour` (checked once, re-checked by `media:check-vtours`).
 */
class SphereTour
{
    public const DISK = 'spaces';

    /** Path used in the API (relative to the storage's public root). */
    public static function path(Beach $beach): ?string
    {
        if (!$beach->folder_name) {
            return null;
        }
        $base = $beach->mobile_upload ? 'beaches_mobile' : 'beaches';

        return "img/{$base}/{$beach->folder_name}/vtour/tour.html";
    }

    /** Asks storage. Null when storage cannot be asked (missing credentials, network) — never guess "missing". */
    public static function existsInStorage(Beach $beach): ?bool
    {
        $path = self::path($beach);
        if ($path === null) {
            return false;
        }
        if (!config('filesystems.disks.' . self::DISK . '.key')) {
            return null; // no storage credentials in this environment (tests use Storage::fake)
        }
        try {
            $disk = Storage::disk(self::DISK);
            if ($disk->exists('public/' . $path)) {
                return true;
            }
            // The disk does not throw on errors (`throw => false`), so "not found" could also mean "storage not
            // reachable". Only call the tour missing when the panorama photo next to it is found.
            $panorama = $beach->media()
                ->whereHas('mediaCollection', fn ($q) => $q->where('code', 'beach_360_sphere'))
                ->value('file_path');

            return $panorama && $disk->exists('public/' . ltrim($panorama, '/')) ? false : null;
        } catch (\Throwable $e) {
            Log::warning('SphereTour: storage check failed', ['beach_id' => $beach->id, 'error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Tour path for the API, or null when the tour is known to be missing. `$checkUnknown`: ask storage once when
     * the beach was never checked and store the answer (beach page only; lists never call storage).
     */
    public static function url(Beach $beach, bool $checkUnknown = false): ?string
    {
        $path = self::path($beach);
        if ($path === null || $beach->has_vtour === false) {
            return null;
        }
        if ($beach->has_vtour === null && $checkUnknown) {
            $exists = self::existsInStorage($beach);
            if ($exists !== null) {
                self::remember($beach, $exists);
                if (!$exists) {
                    return null;
                }
            }
        }

        return $path;
    }

    /** Stores the result without model events (no cache flush, no search re-index). */
    public static function remember(Beach $beach, bool $exists): void
    {
        DB::table('beach')->where('id', $beach->id)->update(['has_vtour' => $exists]);
        $beach->has_vtour = $exists;
        $beach->syncOriginalAttribute('has_vtour');
    }
}
