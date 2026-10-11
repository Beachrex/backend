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
        $gallery = $mediaByCollection->get(self::COLLECTION, collect());
        if ($gallery->isNotEmpty()) {
            return $gallery;
        }

        return collect(self::FALLBACK_COLLECTIONS)
            ->flatMap(fn (string $code) => $mediaByCollection->get($code, collect()))
            ->values();
    }
}
