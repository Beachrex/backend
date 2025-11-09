<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class BeachFilterService
{
    /**
     * Apply filters to a beach query builder
     *
     * @param Builder $query
     * @param Request $request
     * @return Builder
     */
    public function applyFilters(Builder $query, Request $request): Builder
    {
        // Country filter (single or multiple) - supports both code and ID
        if ($request->has('country')) {
            $countries = is_array($request->country) ? $request->country : [$request->country];

            // Check if values are numeric (IDs) or strings (codes)
            $hasNumeric = collect($countries)->contains(fn($val) => is_numeric($val));
            $hasString = collect($countries)->contains(fn($val) => !is_numeric($val));

            if ($hasNumeric && $hasString) {
                // Mixed: use whereIn with subquery for IDs and direct codes
                $query->where(function($q) use ($countries) {
                    $codes = collect($countries)->filter(fn($val) => !is_numeric($val))->values()->all();
                    $ids = collect($countries)->filter(fn($val) => is_numeric($val))->values()->all();

                    if (!empty($codes)) {
                        $q->orWhereIn('country_code', $codes);
                    }
                    if (!empty($ids)) {
                        $q->orWhereHas('country', function($subQ) use ($ids) {
                            $subQ->whereIn('id', $ids);
                        });
                    }
                });
            } elseif ($hasNumeric) {
                // All numeric: filter by country ID
                $query->whereHas('country', function($q) use ($countries) {
                    $q->whereIn('id', $countries);
                });
            } else {
                // All strings: filter by country code
                $query->whereIn('country_code', $countries);
            }
        }

        // Region filter (single or multiple)
        if ($request->has('region')) {
            $regions = is_array($request->region) ? $request->region : [$request->region];
            $query->whereIn('region_id', $regions);
        }

        // Island filter (single or multiple)
        if ($request->has('island')) {
            $islands = is_array($request->island) ? $request->island : [$request->island];
            $query->whereIn('island_id', $islands);
        }

        // Waterbody filter (single or multiple)
        if ($request->has('waterbody')) {
            $waterbodies = is_array($request->waterbody) ? $request->waterbody : [$request->waterbody];
            $query->whereIn('waterbody_id', $waterbodies);
        }

        // Beach type filter (single or multiple)
        if ($request->has('beach_type')) {
            $beachTypes = is_array($request->beach_type) ? $request->beach_type : [$request->beach_type];
            $query->whereIn('beach_type_id', $beachTypes);
        }

        // User type filter (many-to-many relationship)
        if ($request->has('user_type')) {
            $userTypes = is_array($request->user_type) ? $request->user_type : [$request->user_type];
            $query->whereHas('userTypes', function ($q) use ($userTypes) {
                $q->whereIn('user_type.id', $userTypes);
            });
        }

        // Accommodation around filter (many-to-many)
        if ($request->has('accommodation_around')) {
            $accommodations = is_array($request->accommodation_around) ? $request->accommodation_around : [$request->accommodation_around];
            $query->whereHas('accommodationArounds', function ($q) use ($accommodations) {
                $q->whereIn('accommodation_around.id', $accommodations);
            });
        }

        // Culinary offer filter (many-to-many)
        if ($request->has('culinary_offer')) {
            $culinaryOffers = is_array($request->culinary_offer) ? $request->culinary_offer : [$request->culinary_offer];
            $query->whereHas('culinaryOffers', function ($q) use ($culinaryOffers) {
                $q->whereIn('culinary_offer.id', $culinaryOffers);
            });
        }

        // Location infrastructure filter (many-to-many)
        if ($request->has('location_infrastructure')) {
            $infrastructures = is_array($request->location_infrastructure) ? $request->location_infrastructure : [$request->location_infrastructure];
            $query->whereHas('locationInfrastructures', function ($q) use ($infrastructures) {
                $q->whereIn('location_infrastructure.id', $infrastructures);
            });
        }

        // Natural location condition filter (many-to-many)
        if ($request->has('natural_location_condition')) {
            $conditions = is_array($request->natural_location_condition) ? $request->natural_location_condition : [$request->natural_location_condition];
            $query->whereHas('naturalLocationConditions', function ($q) use ($conditions) {
                $q->whereIn('natural_location_condition.id', $conditions);
            });
        }

        // Sporting activities filter (many-to-many)
        if ($request->has('sporting_activity')) {
            $activities = is_array($request->sporting_activity) ? $request->sporting_activity : [$request->sporting_activity];
            $query->whereHas('sportingActivities', function ($q) use ($activities) {
                $q->whereIn('sporting_activities.id', $activities);
            });
        }

        // Underwater characteristics filter (many-to-many)
        if ($request->has('underwater_characteristic')) {
            $characteristics = is_array($request->underwater_characteristic) ? $request->underwater_characteristic : [$request->underwater_characteristic];
            $query->whereHas('underwaterCharacteristics', function ($q) use ($characteristics) {
                $q->whereIn('underwater_characteristic.id', $characteristics);
            });
        }

        // Accessibility filter
        if ($request->has('accessibility')) {
            $accessibilities = is_array($request->accessibility) ? $request->accessibility : [$request->accessibility];
            $query->whereIn('accessibility_id', $accessibilities);
        }

        // Area description filter
        if ($request->has('area_description')) {
            $areaDescriptions = is_array($request->area_description) ? $request->area_description : [$request->area_description];
            $query->whereIn('area_description_id', $areaDescriptions);
        }

        // Parking filter (boolean)
        if ($request->has('parking')) {
            $query->where('parking', $request->boolean('parking'));
        }

        // Parking capacity filter
        if ($request->has('parking_capacity')) {
            $parkingCapacities = is_array($request->parking_capacity) ? $request->parking_capacity : [$request->parking_capacity];
            $query->whereIn('parking_capacity_id', $parkingCapacities);
        }

        // Beach inclination filter
        if ($request->has('beach_inclination')) {
            $inclinations = is_array($request->beach_inclination) ? $request->beach_inclination : [$request->beach_inclination];
            $query->whereIn('beach_inclination_id', $inclinations);
        }

        // Swimming depth filter
        if ($request->has('swimming_depth')) {
            $depths = is_array($request->swimming_depth) ? $request->swimming_depth : [$request->swimming_depth];
            $query->whereIn('swimming_depth_of_one_m_id', $depths);
        }

        // Occupancy rate filter
        if ($request->has('occupancy_rate')) {
            $occupancyRates = is_array($request->occupancy_rate) ? $request->occupancy_rate : [$request->occupancy_rate];
            $query->whereIn('occupancy_rate_id', $occupancyRates);
        }

        // Swimming pool filter (boolean)
        if ($request->has('has_swimming_pool')) {
            $query->where('has_swimming_pool', $request->boolean('has_swimming_pool'));
        }

        // Boat only filter (boolean)
        if ($request->has('boat_only')) {
            $query->where('boat_only', $request->boolean('boat_only'));
        }

        return $query;
    }

    /**
     * Build SQL WHERE conditions for raw queries (PostGIS)
     *
     * @param Request $request
     * @return array ['sql' => string, 'bindings' => array]
     */
    public function buildRawFilters(Request $request): array
    {
        $conditions = [];
        $bindings = [];

        // Country filter - supports both code and ID
        if ($request->has('country')) {
            $countries = is_array($request->country) ? $request->country : [$request->country];

            // Check if values are numeric (IDs) or strings (codes)
            $hasNumeric = collect($countries)->contains(fn($val) => is_numeric($val));
            $hasString = collect($countries)->contains(fn($val) => !is_numeric($val));

            if ($hasNumeric && $hasString) {
                // Mixed: use OR condition for both IDs and codes
                $codes = collect($countries)->filter(fn($val) => !is_numeric($val))->values()->all();
                $ids = collect($countries)->filter(fn($val) => is_numeric($val))->values()->all();

                $subConditions = [];
                if (!empty($codes)) {
                    $placeholders = implode(',', array_fill(0, count($codes), '?'));
                    $subConditions[] = "b.country_code IN ($placeholders)";
                    $bindings = array_merge($bindings, $codes);
                }
                if (!empty($ids)) {
                    $placeholders = implode(',', array_fill(0, count($ids), '?'));
                    $subConditions[] = "c.id IN ($placeholders)";
                    $bindings = array_merge($bindings, $ids);
                }
                $conditions[] = '(' . implode(' OR ', $subConditions) . ')';
            } elseif ($hasNumeric) {
                // All numeric: filter by country ID
                $placeholders = implode(',', array_fill(0, count($countries), '?'));
                $conditions[] = "c.id IN ($placeholders)";
                $bindings = array_merge($bindings, $countries);
            } else {
                // All strings: filter by country code
                $placeholders = implode(',', array_fill(0, count($countries), '?'));
                $conditions[] = "b.country_code IN ($placeholders)";
                $bindings = array_merge($bindings, $countries);
            }
        }

        // Region filter
        if ($request->has('region')) {
            $regions = is_array($request->region) ? $request->region : [$request->region];
            $placeholders = implode(',', array_fill(0, count($regions), '?'));
            $conditions[] = "b.region_id IN ($placeholders)";
            $bindings = array_merge($bindings, $regions);
        }

        // Island filter
        if ($request->has('island')) {
            $islands = is_array($request->island) ? $request->island : [$request->island];
            $placeholders = implode(',', array_fill(0, count($islands), '?'));
            $conditions[] = "b.island_id IN ($placeholders)";
            $bindings = array_merge($bindings, $islands);
        }

        // Waterbody filter
        if ($request->has('waterbody')) {
            $waterbodies = is_array($request->waterbody) ? $request->waterbody : [$request->waterbody];
            $placeholders = implode(',', array_fill(0, count($waterbodies), '?'));
            $conditions[] = "b.waterbody_id IN ($placeholders)";
            $bindings = array_merge($bindings, $waterbodies);
        }

        // Beach type filter
        if ($request->has('beach_type')) {
            $beachTypes = is_array($request->beach_type) ? $request->beach_type : [$request->beach_type];
            $placeholders = implode(',', array_fill(0, count($beachTypes), '?'));
            $conditions[] = "b.beach_type_id IN ($placeholders)";
            $bindings = array_merge($bindings, $beachTypes);
        }

        // User type filter (requires JOIN)
        if ($request->has('user_type')) {
            $userTypes = is_array($request->user_type) ? $request->user_type : [$request->user_type];
            $placeholders = implode(',', array_fill(0, count($userTypes), '?'));
            $conditions[] = "EXISTS (
                SELECT 1 FROM beach_user_type
                WHERE beach_user_type.beach_id = b.id
                AND beach_user_type.user_type_id IN ($placeholders)
            )";
            $bindings = array_merge($bindings, $userTypes);
        }

        // Accessibility filter
        if ($request->has('accessibility')) {
            $accessibilities = is_array($request->accessibility) ? $request->accessibility : [$request->accessibility];
            $placeholders = implode(',', array_fill(0, count($accessibilities), '?'));
            $conditions[] = "b.accessibility_id IN ($placeholders)";
            $bindings = array_merge($bindings, $accessibilities);
        }

        // Parking filter
        if ($request->has('parking')) {
            $conditions[] = "b.parking = ?";
            $bindings[] = $request->boolean('parking') ? 1 : 0;
        }

        // Swimming pool filter
        if ($request->has('has_swimming_pool')) {
            $conditions[] = "b.has_swimming_pool = ?";
            $bindings[] = $request->boolean('has_swimming_pool') ? 1 : 0;
        }

        // Boat only filter
        if ($request->has('boat_only')) {
            $conditions[] = "b.boat_only = ?";
            $bindings[] = $request->boolean('boat_only') ? 1 : 0;
        }

        $sql = !empty($conditions) ? ' AND ' . implode(' AND ', $conditions) : '';

        return [
            'sql' => $sql,
            'bindings' => $bindings
        ];
    }
}

