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
        // Continent filter - supports both code and ID
        if ($request->has('continent_code')) {
            $continents = $this->parseFilterValue($request->continent_code);

            // Check if values are numeric (IDs) or strings (codes)
            $hasNumeric = collect($continents)->contains(fn($val) => is_numeric($val));
            $hasString = collect($continents)->contains(fn($val) => !is_numeric($val));

            if ($hasNumeric && $hasString) {
                // Mixed: use whereIn with subquery for IDs and direct codes
                $query->where(function($q) use ($continents) {
                    $codes = collect($continents)->filter(fn($val) => !is_numeric($val))->map(fn($val) => strtoupper($val))->values()->all();
                    $ids = collect($continents)->filter(fn($val) => is_numeric($val))->values()->all();

                    if (!empty($codes)) {
                        $q->orWhereIn('continent_code', $codes);
                    }
                    if (!empty($ids)) {
                        $q->orWhereHas('continent', function($subQ) use ($ids) {
                            $subQ->whereIn('id', $ids);
                        });
                    }
                });
            } elseif ($hasNumeric) {
                // All numeric: filter by continent ID
                $query->whereHas('continent', function($q) use ($continents) {
                    $q->whereIn('id', $continents);
                });
            } else {
                // All strings: filter by continent code (convert to uppercase)
                $continents = array_map('strtoupper', $continents);
                $query->whereIn('continent_code', $continents);
            }
        }

        // Country filter (single or multiple) - supports both code and ID
        if ($request->has('country')) {
            $countries = $this->parseFilterValue($request->country);

            // Check if values are numeric (IDs) or strings (codes)
            $hasNumeric = collect($countries)->contains(fn($val) => is_numeric($val));
            $hasString = collect($countries)->contains(fn($val) => !is_numeric($val));

            if ($hasNumeric && $hasString) {
                // Mixed: use whereIn with subquery for IDs and direct codes
                $query->where(function($q) use ($countries) {
                    $codes = collect($countries)->filter(fn($val) => !is_numeric($val))->map(fn($val) => strtoupper($val))->values()->all();
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
                // All strings: filter by country code (convert to uppercase)
                $countries = array_map('strtoupper', $countries);
                $query->whereIn('country_code', $countries);
            }
        }

        // Region filter (single or multiple)
        if ($request->has('region')) {
            $regions = $this->parseFilterValue($request->region);
            $query->whereIn('region_id', $regions);
        }

        // Island filter (single or multiple)
        if ($request->has('island')) {
            $islands = $this->parseFilterValue($request->island);
            $query->whereIn('island_id', $islands);
        }

        // City filter (single or multiple) - supports both code and ID
        if ($request->has('city')) {
            $cities = $this->parseFilterValue($request->city);

            // Check if values are numeric (IDs) or strings (codes)
            $hasNumeric = collect($cities)->contains(fn($val) => is_numeric($val));
            $hasString = collect($cities)->contains(fn($val) => !is_numeric($val));

            if ($hasNumeric && $hasString) {
                // Mixed: use whereIn with subquery for IDs and direct codes
                $query->where(function($q) use ($cities) {
                    $codes = collect($cities)->filter(fn($val) => !is_numeric($val))->values()->all();
                    $ids = collect($cities)->filter(fn($val) => is_numeric($val))->values()->all();

                    if (!empty($codes)) {
                        $q->orWhereHas('city', function($subQ) use ($codes) {
                            $subQ->whereIn('code', $codes);
                        });
                    }
                    if (!empty($ids)) {
                        $q->orWhereIn('city_id', $ids);
                    }
                });
            } elseif ($hasNumeric) {
                // All numeric: filter by city ID
                $query->whereIn('city_id', $cities);
            } else {
                // All strings: filter by city code
                $query->whereHas('city', function($q) use ($cities) {
                    $q->whereIn('code', $cities);
                });
            }
        }

        // Waterbody filter (single or multiple)
        if ($request->has('waterbody')) {
            $waterbodies = $this->parseFilterValue($request->waterbody);
            $query->whereIn('waterbody_id', $waterbodies);
        }

        // Beach type filter (single or multiple)
        if ($request->has('beach_type')) {
            $beachTypes = $this->parseFilterValue($request->beach_type);
            $query->whereIn('beach_type_id', $beachTypes);
        }

        // User type filter (many-to-many relationship)
        if ($request->has('user_type')) {
            $userTypes = $this->parseFilterValue($request->user_type);
            $query->whereHas('userTypes', function ($q) use ($userTypes) {
                $q->whereIn('user_type.id', $userTypes);
            });
        }

        // Accommodation around filter (many-to-many)
        if ($request->has('accommodation_around')) {
            $accommodations = $this->parseFilterValue($request->accommodation_around);
            $query->whereHas('accommodationArounds', function ($q) use ($accommodations) {
                $q->whereIn('accommodation_around.id', $accommodations);
            });
        }

        // Culinary offer filter (many-to-many)
        if ($request->has('culinary_offer')) {
            $culinaryOffers = $this->parseFilterValue($request->culinary_offer);
            $query->whereHas('culinaryOffers', function ($q) use ($culinaryOffers) {
                $q->whereIn('culinary_offer.id', $culinaryOffers);
            });
        }

        // Location infrastructure filter (many-to-many)
        if ($request->has('location_infrastructure')) {
            $infrastructures = $this->parseFilterValue($request->location_infrastructure);
            $query->whereHas('locationInfrastructures', function ($q) use ($infrastructures) {
                $q->whereIn('location_infrastructure.id', $infrastructures);
            });
        }

        // Natural location condition filter (many-to-many)
        if ($request->has('natural_location_condition')) {
            $conditions = $this->parseFilterValue($request->natural_location_condition);
            $query->whereHas('naturalLocationConditions', function ($q) use ($conditions) {
                $q->whereIn('natural_location_condition.id', $conditions);
            });
        }

        // Sporting activities filter (many-to-many)
        if ($request->has('sporting_activity')) {
            $activities = $this->parseFilterValue($request->sporting_activity);
            $query->whereHas('sportingActivities', function ($q) use ($activities) {
                $q->whereIn('sporting_activities.id', $activities);
            });
        }

        // Underwater characteristics filter (many-to-many)
        if ($request->has('underwater_characteristic')) {
            $characteristics = $this->parseFilterValue($request->underwater_characteristic);
            $query->whereHas('underwaterCharacteristics', function ($q) use ($characteristics) {
                $q->whereIn('underwater_characteristic.id', $characteristics);
            });
        }

        // Accessibility filter
        if ($request->has('accessibility')) {
            $accessibilities = $this->parseFilterValue($request->accessibility);
            $query->whereIn('accessibility_id', $accessibilities);
        }

        // Area description filter
        if ($request->has('area_description')) {
            $areaDescriptions = $this->parseFilterValue($request->area_description);
            $query->whereIn('area_description_id', $areaDescriptions);
        }

        // Parking filter (boolean)
        if ($request->has('parking')) {
            $query->where('parking', $request->boolean('parking'));
        }

        // Parking capacity filter
        if ($request->has('parking_capacity')) {
            $parkingCapacities = $this->parseFilterValue($request->parking_capacity);
            $query->whereIn('parking_capacity_id', $parkingCapacities);
        }

        // Beach inclination filter
        if ($request->has('beach_inclination')) {
            $inclinations = $this->parseFilterValue($request->beach_inclination);
            $query->whereIn('beach_inclination_id', $inclinations);
        }

        // Swimming depth filter
        if ($request->has('swimming_depth')) {
            $depths = $this->parseFilterValue($request->swimming_depth);
            $query->whereIn('swimming_depth_of_one_m_id', $depths);
        }

        // Occupancy rate filter
        if ($request->has('occupancy_rate')) {
            $occupancyRates = $this->parseFilterValue($request->occupancy_rate);
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

        // Continent filter - supports both code and ID
        if ($request->has('continent_code')) {
            $continents = $this->parseFilterValue($request->continent_code);

            // Check if values are numeric (IDs) or strings (codes)
            $hasNumeric = collect($continents)->contains(fn($val) => is_numeric($val));
            $hasString = collect($continents)->contains(fn($val) => !is_numeric($val));

            if ($hasNumeric && $hasString) {
                // Mixed: use OR condition for both IDs and codes
                $codes = collect($continents)->filter(fn($val) => !is_numeric($val))->map(fn($val) => strtoupper($val))->values()->all();
                $ids = collect($continents)->filter(fn($val) => is_numeric($val))->values()->all();

                $subConditions = [];
                if (!empty($codes)) {
                    $placeholders = implode(',', array_fill(0, count($codes), '?'));
                    $subConditions[] = "b.continent_code IN ($placeholders)";
                    $bindings = array_merge($bindings, $codes);
                }
                if (!empty($ids)) {
                    $placeholders = implode(',', array_fill(0, count($ids), '?'));
                    $subConditions[] = "cont.id IN ($placeholders)";
                    $bindings = array_merge($bindings, $ids);
                }
                $conditions[] = '(' . implode(' OR ', $subConditions) . ')';
            } elseif ($hasNumeric) {
                // All numeric: filter by continent ID
                $placeholders = implode(',', array_fill(0, count($continents), '?'));
                $conditions[] = "cont.id IN ($placeholders)";
                $bindings = array_merge($bindings, $continents);
            } else {
                // All strings: filter by continent code (convert to uppercase)
                $continents = array_map('strtoupper', $continents);
                $placeholders = implode(',', array_fill(0, count($continents), '?'));
                $conditions[] = "b.continent_code IN ($placeholders)";
                $bindings = array_merge($bindings, $continents);
            }
        }

        // Country filter - supports both code and ID
        if ($request->has('country')) {
            $countries = $this->parseFilterValue($request->country);

            // Check if values are numeric (IDs) or strings (codes)
            $hasNumeric = collect($countries)->contains(fn($val) => is_numeric($val));
            $hasString = collect($countries)->contains(fn($val) => !is_numeric($val));

            if ($hasNumeric && $hasString) {
                // Mixed: use OR condition for both IDs and codes
                $codes = collect($countries)->filter(fn($val) => !is_numeric($val))->map(fn($val) => strtoupper($val))->values()->all();
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
                // All strings: filter by country code (convert to uppercase)
                $countries = array_map('strtoupper', $countries);
                $placeholders = implode(',', array_fill(0, count($countries), '?'));
                $conditions[] = "b.country_code IN ($placeholders)";
                $bindings = array_merge($bindings, $countries);
            }
        }

        // Region filter
        if ($request->has('region')) {
            $regions = $this->parseFilterValue($request->region);
            $placeholders = implode(',', array_fill(0, count($regions), '?'));
            $conditions[] = "b.region_id IN ($placeholders)";
            $bindings = array_merge($bindings, $regions);
        }

        // Island filter
        if ($request->has('island')) {
            $islands = $this->parseFilterValue($request->island);
            $placeholders = implode(',', array_fill(0, count($islands), '?'));
            $conditions[] = "b.island_id IN ($placeholders)";
            $bindings = array_merge($bindings, $islands);
        }

        // City filter - supports both code and ID
        if ($request->has('city')) {
            $cities = $this->parseFilterValue($request->city);

            // Check if values are numeric (IDs) or strings (codes)
            $hasNumeric = collect($cities)->contains(fn($val) => is_numeric($val));
            $hasString = collect($cities)->contains(fn($val) => !is_numeric($val));

            if ($hasNumeric && $hasString) {
                // Mixed: use OR condition for both IDs and codes
                $codes = collect($cities)->filter(fn($val) => !is_numeric($val))->values()->all();
                $ids = collect($cities)->filter(fn($val) => is_numeric($val))->values()->all();

                $subConditions = [];
                if (!empty($codes)) {
                    $placeholders = implode(',', array_fill(0, count($codes), '?'));
                    $subConditions[] = "city.code IN ($placeholders)";
                    $bindings = array_merge($bindings, $codes);
                }
                if (!empty($ids)) {
                    $placeholders = implode(',', array_fill(0, count($ids), '?'));
                    $subConditions[] = "b.city_id IN ($placeholders)";
                    $bindings = array_merge($bindings, $ids);
                }
                $conditions[] = '(' . implode(' OR ', $subConditions) . ')';
            } elseif ($hasNumeric) {
                // All numeric: filter by city ID
                $placeholders = implode(',', array_fill(0, count($cities), '?'));
                $conditions[] = "b.city_id IN ($placeholders)";
                $bindings = array_merge($bindings, $cities);
            } else {
                // All strings: filter by city code
                $placeholders = implode(',', array_fill(0, count($cities), '?'));
                $conditions[] = "city.code IN ($placeholders)";
                $bindings = array_merge($bindings, $cities);
            }
        }

        // Waterbody filter
        if ($request->has('waterbody')) {
            $waterbodies = $this->parseFilterValue($request->waterbody);
            $placeholders = implode(',', array_fill(0, count($waterbodies), '?'));
            $conditions[] = "b.waterbody_id IN ($placeholders)";
            $bindings = array_merge($bindings, $waterbodies);
        }

        // Beach type filter
        if ($request->has('beach_type')) {
            $beachTypes = $this->parseFilterValue($request->beach_type);
            $placeholders = implode(',', array_fill(0, count($beachTypes), '?'));
            $conditions[] = "b.beach_type_id IN ($placeholders)";
            $bindings = array_merge($bindings, $beachTypes);
        }

        // User type filter (requires JOIN)
        if ($request->has('user_type')) {
            $userTypes = $this->parseFilterValue($request->user_type);
            $placeholders = implode(',', array_fill(0, count($userTypes), '?'));
            $conditions[] = "EXISTS (
                SELECT 1 FROM user_type_beach
                WHERE user_type_beach.beach_id = b.id
                AND user_type_beach.user_type_id IN ($placeholders)
            )";
            $bindings = array_merge($bindings, $userTypes);
        }

        // Accessibility filter
        if ($request->has('accessibility')) {
            $accessibilities = $this->parseFilterValue($request->accessibility);
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

        // Area description filter
        if ($request->has('area_description')) {
            $areaDescriptions = $this->parseFilterValue($request->area_description);
            $placeholders = implode(',', array_fill(0, count($areaDescriptions), '?'));
            $conditions[] = "b.area_description_id IN ($placeholders)";
            $bindings = array_merge($bindings, $areaDescriptions);
        }

        // Parking capacity filter
        if ($request->has('parking_capacity')) {
            $parkingCapacities = $this->parseFilterValue($request->parking_capacity);
            $placeholders = implode(',', array_fill(0, count($parkingCapacities), '?'));
            $conditions[] = "b.parking_capacity_id IN ($placeholders)";
            $bindings = array_merge($bindings, $parkingCapacities);
        }

        // Beach inclination filter
        if ($request->has('beach_inclination')) {
            $inclinations = $this->parseFilterValue($request->beach_inclination);
            $placeholders = implode(',', array_fill(0, count($inclinations), '?'));
            $conditions[] = "b.beach_inclination_id IN ($placeholders)";
            $bindings = array_merge($bindings, $inclinations);
        }

        // Swimming depth filter
        if ($request->has('swimming_depth')) {
            $depths = $this->parseFilterValue($request->swimming_depth);
            $placeholders = implode(',', array_fill(0, count($depths), '?'));
            $conditions[] = "b.swimming_depth_of_one_m_id IN ($placeholders)";
            $bindings = array_merge($bindings, $depths);
        }

        // Occupancy rate filter
        if ($request->has('occupancy_rate')) {
            $occupancyRates = $this->parseFilterValue($request->occupancy_rate);
            $placeholders = implode(',', array_fill(0, count($occupancyRates), '?'));
            $conditions[] = "b.occupancy_rate_id IN ($placeholders)";
            $bindings = array_merge($bindings, $occupancyRates);
        }

        // Accommodation around filter (many-to-many)
        if ($request->has('accommodation_around')) {
            $accommodations = $this->parseFilterValue($request->accommodation_around);
            $placeholders = implode(',', array_fill(0, count($accommodations), '?'));
            $conditions[] = "EXISTS (
                SELECT 1 FROM accommodation_around_beach
                WHERE accommodation_around_beach.beach_id = b.id
                AND accommodation_around_beach.accommodation_around_id IN ($placeholders)
            )";
            $bindings = array_merge($bindings, $accommodations);
        }

        // Culinary offer filter (many-to-many)
        if ($request->has('culinary_offer')) {
            $culinaryOffers = $this->parseFilterValue($request->culinary_offer);
            $placeholders = implode(',', array_fill(0, count($culinaryOffers), '?'));
            $conditions[] = "EXISTS (
                SELECT 1 FROM culinary_offer_beach
                WHERE culinary_offer_beach.beach_id = b.id
                AND culinary_offer_beach.culinary_offer_id IN ($placeholders)
            )";
            $bindings = array_merge($bindings, $culinaryOffers);
        }

        // Location infrastructure filter (many-to-many)
        if ($request->has('location_infrastructure')) {
            $infrastructures = $this->parseFilterValue($request->location_infrastructure);
            $placeholders = implode(',', array_fill(0, count($infrastructures), '?'));
            $conditions[] = "EXISTS (
                SELECT 1 FROM location_infrastructure_beach
                WHERE location_infrastructure_beach.beach_id = b.id
                AND location_infrastructure_beach.location_infrastructure_id IN ($placeholders)
            )";
            $bindings = array_merge($bindings, $infrastructures);
        }

        // Natural location condition filter (many-to-many)
        if ($request->has('natural_location_condition')) {
            $conditions_filter = $this->parseFilterValue($request->natural_location_condition);
            $placeholders = implode(',', array_fill(0, count($conditions_filter), '?'));
            $conditions[] = "EXISTS (
                SELECT 1 FROM natural_location_condition_beach
                WHERE natural_location_condition_beach.beach_id = b.id
                AND natural_location_condition_beach.natural_location_condition_id IN ($placeholders)
            )";
            $bindings = array_merge($bindings, $conditions_filter);
        }

        // Sporting activities filter (many-to-many)
        if ($request->has('sporting_activity')) {
            $activities = $this->parseFilterValue($request->sporting_activity);
            $placeholders = implode(',', array_fill(0, count($activities), '?'));
            $conditions[] = "EXISTS (
                SELECT 1 FROM sporting_activities_beach
                WHERE sporting_activities_beach.beach_id = b.id
                AND sporting_activities_beach.sporting_activities_id IN ($placeholders)
            )";
            $bindings = array_merge($bindings, $activities);
        }

        // Underwater characteristics filter (many-to-many)
        if ($request->has('underwater_characteristic')) {
            $characteristics = $this->parseFilterValue($request->underwater_characteristic);
            $placeholders = implode(',', array_fill(0, count($characteristics), '?'));
            $conditions[] = "EXISTS (
                SELECT 1 FROM underwater_characteristic_beach
                WHERE underwater_characteristic_beach.beach_id = b.id
                AND underwater_characteristic_beach.underwater_characteristic_id IN ($placeholders)
            )";
            $bindings = array_merge($bindings, $characteristics);
        }

        $sql = !empty($conditions) ? ' AND ' . implode(' AND ', $conditions) : '';

        return [
            'sql' => $sql,
            'bindings' => $bindings
        ];
    }

    /**
     * Parse filter value - supports comma-separated strings, arrays, or single values
     *
     * @param mixed $value
     * @return array
     */
    private function parseFilterValue($value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_string($value) && str_contains($value, ',')) {
            return array_map('trim', explode(',', $value));
        }

        return [$value];
    }
}

