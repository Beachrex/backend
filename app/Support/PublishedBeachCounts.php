<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;

/**
 * Published beach counts (status_id = 1) on a place's parent levels, the same count each parent's own page shows.
 * The place pages show them in the location pill tabs of the banner (country page: continent; city page:
 * continent, country, region, island). Each resource outputs `beaches_count` only when the count is loaded.
 */
final class PublishedBeachCounts
{
    /**
     * @param  array<int, string>  $relations  dot paths of loaded relations, e.g. ['country', 'country.continent']
     */
    public static function loadOnParents(Model $place, array $relations): void
    {
        foreach ($relations as $path) {
            $parent = data_get($place, $path);
            if ($parent instanceof Model) {
                $parent->loadCount(['beaches' => fn ($q) => $q->where('status_id', 1)]);
            }
        }
    }
}
