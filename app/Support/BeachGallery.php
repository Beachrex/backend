<?php

namespace App\Support;

use Illuminate\Support\Collection;

/**
 * Photos for a beach's gallery.
 *
 * The gallery is the `beach_gallery` collection. Some beaches have photos only in other collections
 * (Njivice, id 844: two parking photos and a 360° panorama), so their page showed no photos at all
 * (owner 2026-10-11). Those beaches use their other photo collections instead, in this order.
 */
class BeachGallery
{
    public const COLLECTION = 'beach_gallery';

    /** Photo collections used when a beach has no gallery photo. Videos and 360° spheres are not photos. */
    public const FALLBACK_COLLECTIONS = [
        'beach_spot_images',
        'infrastructure_images',
        'parking_images',
        'info_images',
    ];

    /**
     * @param  Collection<string, Collection>  $mediaByCollection  media grouped by collection code
     */
    public static function pick(Collection $mediaByCollection): Collection
    {
        $gallery = self::photos($mediaByCollection->get(self::COLLECTION, collect()));
        if ($gallery->isNotEmpty()) {
            return $gallery;
        }

        return collect(self::FALLBACK_COLLECTIONS)
            ->flatMap(fn (string $code) => self::photos($mediaByCollection->get($code, collect())))
            ->values();
    }

    /**
     * Videos of a beach: the `beach_videos` collection plus video files that were uploaded into a photo collection
     * (mobile app, e.g. Plage du Port Vieux: `…-video.mp4` in the gallery showed as a broken photo; owner 2026-10-29).
     */
    public static function videos(Collection $mediaByCollection): Collection
    {
        $misfiled = collect([self::COLLECTION, ...self::FALLBACK_COLLECTIONS])
            ->flatMap(fn (string $code) => $mediaByCollection->get($code, collect())->filter(fn ($m) => self::isVideo($m)));

        return $mediaByCollection->get('beach_videos', collect())->concat($misfiled)->values();
    }

    /** A video file by MIME type or by extension (mobile uploads can carry a wrong MIME type). */
    public static function isVideo(mixed $media): bool
    {
        if (!is_object($media)) {
            return false;
        }
        if (str_starts_with(strtolower((string) ($media->mime_type ?? '')), 'video/')) {
            return true;
        }
        foreach (['url', 'file_path', 'file_name'] as $field) {
            if (preg_match('/\.(mp4|m4v|mov|webm|ogv)$/i', (string) ($media->{$field} ?? ''))) {
                return true;
            }
        }

        return false;
    }

    private static function photos(Collection $items): Collection
    {
        return $items->reject(fn ($m) => self::isVideo($m))->values();
    }
}
