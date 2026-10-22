<?php

namespace App\Models\Traits;

use Illuminate\Database\Eloquent\Model;

/**
 * Search (owner 2026-10-22): "grčija", "nemčija", "hrvaška" found only the country, no beaches. The search index
 * held the parent places in English only (`country->name` is the English translation), so a beach in Greece
 * matched "Greece" but not "Grčija", "Griechenland" or "Ελλάδα". `place_names` carries the names of the
 * parent places (country, region, island, city) in every language.
 */
trait IndexesPlaceNames
{
    /** Relations whose names go into `place_names`; each must have `translations` with a `name`. */
    abstract protected function placeNameRelations(): array;

    /** All translated names of the parent places, unique, space-separated. */
    protected function placeNames(): string
    {
        $relations = $this->placeNameRelations();
        $this->loadMissing(array_map(fn ($r) => "{$r}.translations", $relations));

        $names = [];
        foreach ($relations as $relation) {
            $place = $this->getRelation($relation);
            if (! $place instanceof Model) {
                continue;
            }
            foreach ($place->translations as $translation) {
                $name = trim((string) $translation->name);
                if ($name !== '') {
                    $names[mb_strtolower($name)] = $name;
                }
            }
        }

        return implode(' ', array_values($names));
    }

    /** Relations to eager-load for `scout:import` (used by each model's makeAllSearchableUsing). */
    protected function placeNameEagerLoads(): array
    {
        return array_merge(['translations'], array_map(fn ($r) => "{$r}.translations", $this->placeNameRelations()));
    }
}
