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

        // Region filter (single or multiple) - supports both codes and IDs
        if ($request->has('region')) {
            $regions = $this->parseFilterValue($request->region);

            $hasNumeric = collect($regions)->contains(fn($val) => is_numeric($val));
            $hasString = collect($regions)->contains(fn($val) => !is_numeric($val));

            if ($hasNumeric && $hasString) {
                // Mixed codes and IDs
                $codes = collect($regions)->filter(fn($val) => !is_numeric($val))->values()->all();
                $ids = collect($regions)->filter(fn($val) => is_numeric($val))->values()->all();

                $query->where(function ($q) use ($codes, $ids) {
                    if (!empty($codes)) {
                        $q->orWhereHas('region', function ($subQuery) use ($codes) {
                            $subQuery->whereIn('code', $codes);
                        });
                    }
                    if (!empty($ids)) {
                        $q->orWhereIn('region_id', $ids);
                    }
                });
            } elseif ($hasNumeric) {
                // ID-based filtering
                $query->whereIn('region_id', $regions);
            } else {
                // Code-based filtering
                $query->whereHas('region', function ($subQuery) use ($regions) {
                    $subQuery->whereIn('code', $regions);
                });
            }
        }

        // Island filter (single or multiple) - supports both codes and IDs
        if ($request->has('island')) {
            $islands = $this->parseFilterValue($request->island);

            $hasNumeric = collect($islands)->contains(fn($val) => is_numeric($val));
            $hasString = collect($islands)->contains(fn($val) => !is_numeric($val));

            if ($hasNumeric && $hasString) {
                // Mixed codes and IDs
                $codes = collect($islands)->filter(fn($val) => !is_numeric($val))->values()->all();
                $ids = collect($islands)->filter(fn($val) => is_numeric($val))->values()->all();

                $query->where(function ($q) use ($codes, $ids) {
                    if (!empty($codes)) {
                        $q->orWhereHas('island', function ($subQuery) use ($codes) {
                            $subQuery->whereIn('code', $codes);
                        });
                    }
                    if (!empty($ids)) {
                        $q->orWhereIn('island_id', $ids);
                    }
                });
            } elseif ($hasNumeric) {
                // ID-based filtering
                $query->whereIn('island_id', $islands);
            } else {
                // Code-based filtering
                $query->whereHas('island', function ($subQuery) use ($islands) {
                    $subQuery->whereIn('code', $islands);
                });
            }
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

        // Waterbody filter (single or multiple) - supports both code and ID
        if ($request->has('waterbody')) {
            $waterbodies = $this->parseFilterValue($request->waterbody);

            // Check if values are numeric (IDs) or strings (codes)
            $hasNumeric = collect($waterbodies)->contains(fn($val) => is_numeric($val));
            $hasString = collect($waterbodies)->contains(fn($val) => !is_numeric($val));

            if ($hasNumeric && $hasString) {
                // Mixed: use OR condition for both IDs and codes
                $codes = collect($waterbodies)->filter(fn($val) => !is_numeric($val))->values()->all();
                $ids = collect($waterbodies)->filter(fn($val) => is_numeric($val))->values()->all();

                $query->where(function ($q) use ($codes, $ids) {
                    if (!empty($codes)) {
                        $q->orWhereHas('waterbody', function ($subQ) use ($codes) {
                            $subQ->whereIn('code', $codes);
                        });
                    }
                    if (!empty($ids)) {
                        $q->orWhereIn('waterbody_id', $ids);
                    }
                });
            } elseif ($hasNumeric) {
                // All numeric: filter by waterbody ID
                $query->whereIn('waterbody_id', $waterbodies);
            } else {
                // All strings: filter by waterbody code
                $query->whereHas('waterbody', function ($q) use ($waterbodies) {
                    $q->whereIn('code', $waterbodies);
                });
            }
        }

        // Beach type filter (single or multiple) - supports both code and ID
        if ($request->has('beach_type')) {
            $beachTypes = $this->parseFilterValue($request->beach_type);

            $hasNumeric = collect($beachTypes)->contains(fn($val) => is_numeric($val));
            $hasString = collect($beachTypes)->contains(fn($val) => !is_numeric($val));

            if ($hasNumeric && $hasString) {
                $codes = collect($beachTypes)->filter(fn($val) => !is_numeric($val))->values()->all();
                $ids = collect($beachTypes)->filter(fn($val) => is_numeric($val))->values()->all();

                $query->where(function ($q) use ($codes, $ids) {
                    if (!empty($codes)) {
                        $q->orWhereHas('beachType', function ($subQ) use ($codes) {
                            $subQ->whereIn('code', $codes);
                        });
                    }
                    if (!empty($ids)) {
                        $q->orWhereIn('beach_type_id', $ids);
                    }
                });
            } elseif ($hasNumeric) {
                $query->whereIn('beach_type_id', $beachTypes);
            } else {
                $query->whereHas('beachType', function ($q) use ($beachTypes) {
                    $q->whereIn('code', $beachTypes);
                });
            }
        }

        // User type filter (many-to-many relationship) - supports both code and ID
        if ($request->has('user_type')) {
            $userTypes = $this->parseFilterValue($request->user_type);

            $hasNumeric = collect($userTypes)->contains(fn($val) => is_numeric($val));
            $hasString = collect($userTypes)->contains(fn($val) => !is_numeric($val));

            if ($hasNumeric && $hasString) {
                $codes = collect($userTypes)->filter(fn($val) => !is_numeric($val))->values()->all();
                $ids = collect($userTypes)->filter(fn($val) => is_numeric($val))->values()->all();

                $query->whereHas('userTypes', function ($q) use ($codes, $ids) {
                    $q->where(function ($subQ) use ($codes, $ids) {
                        if (!empty($codes)) {
                            $subQ->orWhereIn('user_type.code', $codes);
                        }
                        if (!empty($ids)) {
                            $subQ->orWhereIn('user_type.id', $ids);
                        }
                    });
                });
            } elseif ($hasNumeric) {
                $query->whereHas('userTypes', function ($q) use ($userTypes) {
                    $q->whereIn('user_type.id', $userTypes);
                });
            } else {
                $query->whereHas('userTypes', function ($q) use ($userTypes) {
                    $q->whereIn('user_type.code', $userTypes);
                });
            }
        }

        // Accommodation around filter (many-to-many) - supports both code and ID
        if ($request->has('accommodation_around')) {
            $accommodations = $this->parseFilterValue($request->accommodation_around);

            $hasNumeric = collect($accommodations)->contains(fn($val) => is_numeric($val));
            $hasString = collect($accommodations)->contains(fn($val) => !is_numeric($val));

            if ($hasNumeric && $hasString) {
                $codes = collect($accommodations)->filter(fn($val) => !is_numeric($val))->values()->all();
                $ids = collect($accommodations)->filter(fn($val) => is_numeric($val))->values()->all();

                $query->whereHas('accommodationArounds', function ($q) use ($codes, $ids) {
                    $q->where(function ($subQ) use ($codes, $ids) {
                        if (!empty($codes)) {
                            $subQ->orWhereIn('accommodation_around.code', $codes);
                        }
                        if (!empty($ids)) {
                            $subQ->orWhereIn('accommodation_around.id', $ids);
                        }
                    });
                });
            } elseif ($hasNumeric) {
                $query->whereHas('accommodationArounds', function ($q) use ($accommodations) {
                    $q->whereIn('accommodation_around.id', $accommodations);
                });
            } else {
                $query->whereHas('accommodationArounds', function ($q) use ($accommodations) {
                    $q->whereIn('accommodation_around.code', $accommodations);
                });
            }
        }

        // Culinary offer filter (many-to-many) - supports both code and ID
        if ($request->has('culinary_offer')) {
            $culinaryOffers = $this->parseFilterValue($request->culinary_offer);

            $hasNumeric = collect($culinaryOffers)->contains(fn($val) => is_numeric($val));
            $hasString = collect($culinaryOffers)->contains(fn($val) => !is_numeric($val));

            if ($hasNumeric && $hasString) {
                $codes = collect($culinaryOffers)->filter(fn($val) => !is_numeric($val))->values()->all();
                $ids = collect($culinaryOffers)->filter(fn($val) => is_numeric($val))->values()->all();

                $query->whereHas('culinaryOffers', function ($q) use ($codes, $ids) {
                    $q->where(function ($subQ) use ($codes, $ids) {
                        if (!empty($codes)) {
                            $subQ->orWhereIn('culinary_offer.code', $codes);
                        }
                        if (!empty($ids)) {
                            $subQ->orWhereIn('culinary_offer.id', $ids);
                        }
                    });
                });
            } elseif ($hasNumeric) {
                $query->whereHas('culinaryOffers', function ($q) use ($culinaryOffers) {
                    $q->whereIn('culinary_offer.id', $culinaryOffers);
                });
            } else {
                $query->whereHas('culinaryOffers', function ($q) use ($culinaryOffers) {
                    $q->whereIn('culinary_offer.code', $culinaryOffers);
                });
            }
        }

        // Location infrastructure filter (many-to-many) - supports both code and ID
        if ($request->has('location_infrastructure')) {
            $infrastructures = $this->parseFilterValue($request->location_infrastructure);

            $hasNumeric = collect($infrastructures)->contains(fn($val) => is_numeric($val));
            $hasString = collect($infrastructures)->contains(fn($val) => !is_numeric($val));

            if ($hasNumeric && $hasString) {
                $codes = collect($infrastructures)->filter(fn($val) => !is_numeric($val))->values()->all();
                $ids = collect($infrastructures)->filter(fn($val) => is_numeric($val))->values()->all();

                $query->whereHas('locationInfrastructures', function ($q) use ($codes, $ids) {
                    $q->where(function ($subQ) use ($codes, $ids) {
                        if (!empty($codes)) {
                            $subQ->orWhereIn('location_infrastructure.code', $codes);
                        }
                        if (!empty($ids)) {
                            $subQ->orWhereIn('location_infrastructure.id', $ids);
                        }
                    });
                });
            } elseif ($hasNumeric) {
                $query->whereHas('locationInfrastructures', function ($q) use ($infrastructures) {
                    $q->whereIn('location_infrastructure.id', $infrastructures);
                });
            } else {
                $query->whereHas('locationInfrastructures', function ($q) use ($infrastructures) {
                    $q->whereIn('location_infrastructure.code', $infrastructures);
                });
            }
        }

        // Natural location condition filter (many-to-many) - supports both code and ID
        if ($request->has('natural_location_condition')) {
            $conditions = $this->parseFilterValue($request->natural_location_condition);

            $hasNumeric = collect($conditions)->contains(fn($val) => is_numeric($val));
            $hasString = collect($conditions)->contains(fn($val) => !is_numeric($val));

            if ($hasNumeric && $hasString) {
                $codes = collect($conditions)->filter(fn($val) => !is_numeric($val))->values()->all();
                $ids = collect($conditions)->filter(fn($val) => is_numeric($val))->values()->all();

                $query->whereHas('naturalLocationConditions', function ($q) use ($codes, $ids) {
                    $q->where(function ($subQ) use ($codes, $ids) {
                        if (!empty($codes)) {
                            $subQ->orWhereIn('natural_location_condition.code', $codes);
                        }
                        if (!empty($ids)) {
                            $subQ->orWhereIn('natural_location_condition.id', $ids);
                        }
                    });
                });
            } elseif ($hasNumeric) {
                $query->whereHas('naturalLocationConditions', function ($q) use ($conditions) {
                    $q->whereIn('natural_location_condition.id', $conditions);
                });
            } else {
                $query->whereHas('naturalLocationConditions', function ($q) use ($conditions) {
                    $q->whereIn('natural_location_condition.code', $conditions);
                });
            }
        }

        // Sporting activities filter (many-to-many) - supports both code and ID
        if ($request->has('sporting_activity')) {
            $activities = $this->parseFilterValue($request->sporting_activity);

            $hasNumeric = collect($activities)->contains(fn($val) => is_numeric($val));
            $hasString = collect($activities)->contains(fn($val) => !is_numeric($val));

            if ($hasNumeric && $hasString) {
                $codes = collect($activities)->filter(fn($val) => !is_numeric($val))->values()->all();
                $ids = collect($activities)->filter(fn($val) => is_numeric($val))->values()->all();

                $query->whereHas('sportingActivities', function ($q) use ($codes, $ids) {
                    $q->where(function ($subQ) use ($codes, $ids) {
                        if (!empty($codes)) {
                            $subQ->orWhereIn('sporting_activities.code', $codes);
                        }
                        if (!empty($ids)) {
                            $subQ->orWhereIn('sporting_activities.id', $ids);
                        }
                    });
                });
            } elseif ($hasNumeric) {
                $query->whereHas('sportingActivities', function ($q) use ($activities) {
                    $q->whereIn('sporting_activities.id', $activities);
                });
            } else {
                $query->whereHas('sportingActivities', function ($q) use ($activities) {
                    $q->whereIn('sporting_activities.code', $activities);
                });
            }
        }

        // Underwater characteristics filter (many-to-many) - supports both code and ID
        if ($request->has('underwater_characteristic')) {
            $characteristics = $this->parseFilterValue($request->underwater_characteristic);

            $hasNumeric = collect($characteristics)->contains(fn($val) => is_numeric($val));
            $hasString = collect($characteristics)->contains(fn($val) => !is_numeric($val));

            if ($hasNumeric && $hasString) {
                $codes = collect($characteristics)->filter(fn($val) => !is_numeric($val))->values()->all();
                $ids = collect($characteristics)->filter(fn($val) => is_numeric($val))->values()->all();

                $query->whereHas('underwaterCharacteristics', function ($q) use ($codes, $ids) {
                    $q->where(function ($subQ) use ($codes, $ids) {
                        if (!empty($codes)) {
                            $subQ->orWhereIn('underwater_characteristic.code', $codes);
                        }
                        if (!empty($ids)) {
                            $subQ->orWhereIn('underwater_characteristic.id', $ids);
                        }
                    });
                });
            } elseif ($hasNumeric) {
                $query->whereHas('underwaterCharacteristics', function ($q) use ($characteristics) {
                    $q->whereIn('underwater_characteristic.id', $characteristics);
                });
            } else {
                $query->whereHas('underwaterCharacteristics', function ($q) use ($characteristics) {
                    $q->whereIn('underwater_characteristic.code', $characteristics);
                });
            }
        }

        // Accessibility filter - supports both code and ID
        if ($request->has('accessibility')) {
            $accessibilities = $this->parseFilterValue($request->accessibility);

            $hasNumeric = collect($accessibilities)->contains(fn($val) => is_numeric($val));
            $hasString = collect($accessibilities)->contains(fn($val) => !is_numeric($val));

            if ($hasNumeric && $hasString) {
                $codes = collect($accessibilities)->filter(fn($val) => !is_numeric($val))->values()->all();
                $ids = collect($accessibilities)->filter(fn($val) => is_numeric($val))->values()->all();

                $query->where(function ($q) use ($codes, $ids) {
                    if (!empty($codes)) {
                        $q->orWhereHas('accessibility', function ($subQ) use ($codes) {
                            $subQ->whereIn('code', $codes);
                        });
                    }
                    if (!empty($ids)) {
                        $q->orWhereIn('accessibility_id', $ids);
                    }
                });
            } elseif ($hasNumeric) {
                $query->whereIn('accessibility_id', $accessibilities);
            } else {
                $query->whereHas('accessibility', function ($q) use ($accessibilities) {
                    $q->whereIn('code', $accessibilities);
                });
            }
        }

        // Area description filter - supports both code and ID
        if ($request->has('area_description')) {
            $areaDescriptions = $this->parseFilterValue($request->area_description);

            $hasNumeric = collect($areaDescriptions)->contains(fn($val) => is_numeric($val));
            $hasString = collect($areaDescriptions)->contains(fn($val) => !is_numeric($val));

            if ($hasNumeric && $hasString) {
                $codes = collect($areaDescriptions)->filter(fn($val) => !is_numeric($val))->values()->all();
                $ids = collect($areaDescriptions)->filter(fn($val) => is_numeric($val))->values()->all();

                $query->where(function ($q) use ($codes, $ids) {
                    if (!empty($codes)) {
                        $q->orWhereHas('areaDescription', function ($subQ) use ($codes) {
                            $subQ->whereIn('code', $codes);
                        });
                    }
                    if (!empty($ids)) {
                        $q->orWhereIn('area_description_id', $ids);
                    }
                });
            } elseif ($hasNumeric) {
                $query->whereIn('area_description_id', $areaDescriptions);
            } else {
                $query->whereHas('areaDescription', function ($q) use ($areaDescriptions) {
                    $q->whereIn('code', $areaDescriptions);
                });
            }
        }

        // Parking filter (boolean)
        if ($request->has('parking')) {
            $query->where('parking', $request->boolean('parking'));
        }

        // Parking capacity filter - supports both code and ID
        if ($request->has('parking_capacity')) {
            $parkingCapacities = $this->parseFilterValue($request->parking_capacity);

            $hasNumeric = collect($parkingCapacities)->contains(fn($val) => is_numeric($val));
            $hasString = collect($parkingCapacities)->contains(fn($val) => !is_numeric($val));

            if ($hasNumeric && $hasString) {
                $codes = collect($parkingCapacities)->filter(fn($val) => !is_numeric($val))->values()->all();
                $ids = collect($parkingCapacities)->filter(fn($val) => is_numeric($val))->values()->all();

                $query->where(function ($q) use ($codes, $ids) {
                    if (!empty($codes)) {
                        $q->orWhereHas('parkingCapacity', function ($subQ) use ($codes) {
                            $subQ->whereIn('code', $codes);
                        });
                    }
                    if (!empty($ids)) {
                        $q->orWhereIn('parking_capacity_id', $ids);
                    }
                });
            } elseif ($hasNumeric) {
                $query->whereIn('parking_capacity_id', $parkingCapacities);
            } else {
                $query->whereHas('parkingCapacity', function ($q) use ($parkingCapacities) {
                    $q->whereIn('code', $parkingCapacities);
                });
            }
        }

        // Beach inclination filter - supports both code and ID
        if ($request->has('beach_inclination')) {
            $inclinations = $this->parseFilterValue($request->beach_inclination);

            $hasNumeric = collect($inclinations)->contains(fn($val) => is_numeric($val));
            $hasString = collect($inclinations)->contains(fn($val) => !is_numeric($val));

            if ($hasNumeric && $hasString) {
                $codes = collect($inclinations)->filter(fn($val) => !is_numeric($val))->values()->all();
                $ids = collect($inclinations)->filter(fn($val) => is_numeric($val))->values()->all();

                $query->where(function ($q) use ($codes, $ids) {
                    if (!empty($codes)) {
                        $q->orWhereHas('beachInclination', function ($subQ) use ($codes) {
                            $subQ->whereIn('code', $codes);
                        });
                    }
                    if (!empty($ids)) {
                        $q->orWhereIn('beach_inclination_id', $ids);
                    }
                });
            } elseif ($hasNumeric) {
                $query->whereIn('beach_inclination_id', $inclinations);
            } else {
                $query->whereHas('beachInclination', function ($q) use ($inclinations) {
                    $q->whereIn('code', $inclinations);
                });
            }
        }

        // Swimming depth filter - supports both code and ID
        if ($request->has('swimming_depth')) {
            $depths = $this->parseFilterValue($request->swimming_depth);

            $hasNumeric = collect($depths)->contains(fn($val) => is_numeric($val));
            $hasString = collect($depths)->contains(fn($val) => !is_numeric($val));

            if ($hasNumeric && $hasString) {
                $codes = collect($depths)->filter(fn($val) => !is_numeric($val))->values()->all();
                $ids = collect($depths)->filter(fn($val) => is_numeric($val))->values()->all();

                $query->where(function ($q) use ($codes, $ids) {
                    if (!empty($codes)) {
                        $q->orWhereHas('swimmingDepthOfOneM', function ($subQ) use ($codes) {
                            $subQ->whereIn('code', $codes);
                        });
                    }
                    if (!empty($ids)) {
                        $q->orWhereIn('swimming_depth_of_one_m_id', $ids);
                    }
                });
            } elseif ($hasNumeric) {
                $query->whereIn('swimming_depth_of_one_m_id', $depths);
            } else {
                $query->whereHas('swimmingDepthOfOneM', function ($q) use ($depths) {
                    $q->whereIn('code', $depths);
                });
            }
        }

        // Occupancy rate filter - supports both code and ID
        if ($request->has('occupancy_rate')) {
            $occupancyRates = $this->parseFilterValue($request->occupancy_rate);

            $hasNumeric = collect($occupancyRates)->contains(fn($val) => is_numeric($val));
            $hasString = collect($occupancyRates)->contains(fn($val) => !is_numeric($val));

            if ($hasNumeric && $hasString) {
                $codes = collect($occupancyRates)->filter(fn($val) => !is_numeric($val))->values()->all();
                $ids = collect($occupancyRates)->filter(fn($val) => is_numeric($val))->values()->all();

                $query->where(function ($q) use ($codes, $ids) {
                    if (!empty($codes)) {
                        $q->orWhereHas('occupancyRate', function ($subQ) use ($codes) {
                            $subQ->whereIn('code', $codes);
                        });
                    }
                    if (!empty($ids)) {
                        $q->orWhereIn('occupancy_rate_id', $ids);
                    }
                });
            } elseif ($hasNumeric) {
                $query->whereIn('occupancy_rate_id', $occupancyRates);
            } else {
                $query->whereHas('occupancyRate', function ($q) use ($occupancyRates) {
                    $q->whereIn('code', $occupancyRates);
                });
            }
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

        // Region filter - supports both codes and IDs
        if ($request->has('region')) {
            $regions = $this->parseFilterValue($request->region);

            $hasNumeric = collect($regions)->contains(fn($val) => is_numeric($val));
            $hasString = collect($regions)->contains(fn($val) => !is_numeric($val));

            if ($hasNumeric && $hasString) {
                // Mixed codes and IDs
                $codes = collect($regions)->filter(fn($val) => !is_numeric($val))->values()->all();
                $ids = collect($regions)->filter(fn($val) => is_numeric($val))->values()->all();

                $subConditions = [];
                if (!empty($codes)) {
                    $placeholders = implode(',', array_fill(0, count($codes), '?'));
                    $subConditions[] = "reg.code IN ($placeholders)";
                    $bindings = array_merge($bindings, $codes);
                }
                if (!empty($ids)) {
                    $placeholders = implode(',', array_fill(0, count($ids), '?'));
                    $subConditions[] = "b.region_id IN ($placeholders)";
                    $bindings = array_merge($bindings, $ids);
                }
                $conditions[] = '(' . implode(' OR ', $subConditions) . ')';
            } elseif ($hasNumeric) {
                // ID-based filtering
                $placeholders = implode(',', array_fill(0, count($regions), '?'));
                $conditions[] = "b.region_id IN ($placeholders)";
                $bindings = array_merge($bindings, $regions);
            } else {
                // Code-based filtering
                $placeholders = implode(',', array_fill(0, count($regions), '?'));
                $conditions[] = "reg.code IN ($placeholders)";
                $bindings = array_merge($bindings, $regions);
            }
        }

        // Island filter - supports both codes and IDs
        if ($request->has('island')) {
            $islands = $this->parseFilterValue($request->island);

            $hasNumeric = collect($islands)->contains(fn($val) => is_numeric($val));
            $hasString = collect($islands)->contains(fn($val) => !is_numeric($val));

            if ($hasNumeric && $hasString) {
                // Mixed codes and IDs
                $codes = collect($islands)->filter(fn($val) => !is_numeric($val))->values()->all();
                $ids = collect($islands)->filter(fn($val) => is_numeric($val))->values()->all();

                $subConditions = [];
                if (!empty($codes)) {
                    $placeholders = implode(',', array_fill(0, count($codes), '?'));
                    $subConditions[] = "isl.code IN ($placeholders)";
                    $bindings = array_merge($bindings, $codes);
                }
                if (!empty($ids)) {
                    $placeholders = implode(',', array_fill(0, count($ids), '?'));
                    $subConditions[] = "b.island_id IN ($placeholders)";
                    $bindings = array_merge($bindings, $ids);
                }
                $conditions[] = '(' . implode(' OR ', $subConditions) . ')';
            } elseif ($hasNumeric) {
                // ID-based filtering
                $placeholders = implode(',', array_fill(0, count($islands), '?'));
                $conditions[] = "b.island_id IN ($placeholders)";
                $bindings = array_merge($bindings, $islands);
            } else {
                // Code-based filtering
                $placeholders = implode(',', array_fill(0, count($islands), '?'));
                $conditions[] = "isl.code IN ($placeholders)";
                $bindings = array_merge($bindings, $islands);
            }
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

        // Waterbody filter - supports both code and ID
        if ($request->has('waterbody')) {
            $waterbodies = $this->parseFilterValue($request->waterbody);

            // Check if values are numeric (IDs) or strings (codes)
            $hasNumeric = collect($waterbodies)->contains(fn($val) => is_numeric($val));
            $hasString = collect($waterbodies)->contains(fn($val) => !is_numeric($val));

            if ($hasNumeric && $hasString) {
                // Mixed: use OR condition for both IDs and codes
                $codes = collect($waterbodies)->filter(fn($val) => !is_numeric($val))->values()->all();
                $ids = collect($waterbodies)->filter(fn($val) => is_numeric($val))->values()->all();

                $subConditions = [];
                if (!empty($codes)) {
                    $placeholders = implode(',', array_fill(0, count($codes), '?'));
                    $subConditions[] = "wb.code IN ($placeholders)";
                    $bindings = array_merge($bindings, $codes);
                }
                if (!empty($ids)) {
                    $placeholders = implode(',', array_fill(0, count($ids), '?'));
                    $subConditions[] = "b.waterbody_id IN ($placeholders)";
                    $bindings = array_merge($bindings, $ids);
                }
                $conditions[] = '(' . implode(' OR ', $subConditions) . ')';
            } elseif ($hasNumeric) {
                // All numeric: filter by waterbody ID
                $placeholders = implode(',', array_fill(0, count($waterbodies), '?'));
                $conditions[] = "b.waterbody_id IN ($placeholders)";
                $bindings = array_merge($bindings, $waterbodies);
            } else {
                // All strings: filter by waterbody code
                $placeholders = implode(',', array_fill(0, count($waterbodies), '?'));
                $conditions[] = "wb.code IN ($placeholders)";
                $bindings = array_merge($bindings, $waterbodies);
            }
        }

        // Beach type filter - supports both code and ID
        if ($request->has('beach_type')) {
            $beachTypes = $this->parseFilterValue($request->beach_type);

            $hasNumeric = collect($beachTypes)->contains(fn($val) => is_numeric($val));
            $hasString = collect($beachTypes)->contains(fn($val) => !is_numeric($val));

            if ($hasNumeric && $hasString) {
                $codes = collect($beachTypes)->filter(fn($val) => !is_numeric($val))->values()->all();
                $ids = collect($beachTypes)->filter(fn($val) => is_numeric($val))->values()->all();

                $subConditions = [];
                if (!empty($codes)) {
                    $placeholders = implode(',', array_fill(0, count($codes), '?'));
                    $subConditions[] = "btype.code IN ($placeholders)";
                    $bindings = array_merge($bindings, $codes);
                }
                if (!empty($ids)) {
                    $placeholders = implode(',', array_fill(0, count($ids), '?'));
                    $subConditions[] = "b.beach_type_id IN ($placeholders)";
                    $bindings = array_merge($bindings, $ids);
                }
                $conditions[] = '(' . implode(' OR ', $subConditions) . ')';
            } elseif ($hasNumeric) {
                $placeholders = implode(',', array_fill(0, count($beachTypes), '?'));
                $conditions[] = "b.beach_type_id IN ($placeholders)";
                $bindings = array_merge($bindings, $beachTypes);
            } else {
                $placeholders = implode(',', array_fill(0, count($beachTypes), '?'));
                $conditions[] = "btype.code IN ($placeholders)";
                $bindings = array_merge($bindings, $beachTypes);
            }
        }

        // User type filter (requires JOIN) - supports both code and ID
        if ($request->has('user_type')) {
            $userTypes = $this->parseFilterValue($request->user_type);

            $hasNumeric = collect($userTypes)->contains(fn($val) => is_numeric($val));
            $hasString = collect($userTypes)->contains(fn($val) => !is_numeric($val));

            if ($hasNumeric && $hasString) {
                $codes = collect($userTypes)->filter(fn($val) => !is_numeric($val))->values()->all();
                $ids = collect($userTypes)->filter(fn($val) => is_numeric($val))->values()->all();

                $subConditions = [];
                if (!empty($codes)) {
                    $placeholders = implode(',', array_fill(0, count($codes), '?'));
                    $subConditions[] = "EXISTS (
                        SELECT 1 FROM user_type_beach utb
                        JOIN user_type ut ON utb.user_type_id = ut.id
                        WHERE utb.beach_id = b.id
                        AND ut.code IN ($placeholders)
                    )";
                    $bindings = array_merge($bindings, $codes);
                }
                if (!empty($ids)) {
                    $placeholders = implode(',', array_fill(0, count($ids), '?'));
                    $subConditions[] = "EXISTS (
                        SELECT 1 FROM user_type_beach
                        WHERE user_type_beach.beach_id = b.id
                        AND user_type_beach.user_type_id IN ($placeholders)
                    )";
                    $bindings = array_merge($bindings, $ids);
                }
                $conditions[] = '(' . implode(' OR ', $subConditions) . ')';
            } elseif ($hasNumeric) {
                $placeholders = implode(',', array_fill(0, count($userTypes), '?'));
                $conditions[] = "EXISTS (
                    SELECT 1 FROM user_type_beach
                    WHERE user_type_beach.beach_id = b.id
                    AND user_type_beach.user_type_id IN ($placeholders)
                )";
                $bindings = array_merge($bindings, $userTypes);
            } else {
                $placeholders = implode(',', array_fill(0, count($userTypes), '?'));
                $conditions[] = "EXISTS (
                    SELECT 1 FROM user_type_beach utb
                    JOIN user_type ut ON utb.user_type_id = ut.id
                    WHERE utb.beach_id = b.id
                    AND ut.code IN ($placeholders)
                )";
                $bindings = array_merge($bindings, $userTypes);
            }
        }

        // Accessibility filter - supports both code and ID
        if ($request->has('accessibility')) {
            $accessibilities = $this->parseFilterValue($request->accessibility);

            $hasNumeric = collect($accessibilities)->contains(fn($val) => is_numeric($val));
            $hasString = collect($accessibilities)->contains(fn($val) => !is_numeric($val));

            if ($hasNumeric && $hasString) {
                $codes = collect($accessibilities)->filter(fn($val) => !is_numeric($val))->values()->all();
                $ids = collect($accessibilities)->filter(fn($val) => is_numeric($val))->values()->all();

                $subConditions = [];
                if (!empty($codes)) {
                    $placeholders = implode(',', array_fill(0, count($codes), '?'));
                    $subConditions[] = "acc.code IN ($placeholders)";
                    $bindings = array_merge($bindings, $codes);
                }
                if (!empty($ids)) {
                    $placeholders = implode(',', array_fill(0, count($ids), '?'));
                    $subConditions[] = "b.accessibility_id IN ($placeholders)";
                    $bindings = array_merge($bindings, $ids);
                }
                $conditions[] = '(' . implode(' OR ', $subConditions) . ')';
            } elseif ($hasNumeric) {
                $placeholders = implode(',', array_fill(0, count($accessibilities), '?'));
                $conditions[] = "b.accessibility_id IN ($placeholders)";
                $bindings = array_merge($bindings, $accessibilities);
            } else {
                $placeholders = implode(',', array_fill(0, count($accessibilities), '?'));
                $conditions[] = "acc.code IN ($placeholders)";
                $bindings = array_merge($bindings, $accessibilities);
            }
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

        // Area description filter - supports both code and ID
        if ($request->has('area_description')) {
            $areaDescriptions = $this->parseFilterValue($request->area_description);

            $hasNumeric = collect($areaDescriptions)->contains(fn($val) => is_numeric($val));
            $hasString = collect($areaDescriptions)->contains(fn($val) => !is_numeric($val));

            if ($hasNumeric && $hasString) {
                $codes = collect($areaDescriptions)->filter(fn($val) => !is_numeric($val))->values()->all();
                $ids = collect($areaDescriptions)->filter(fn($val) => is_numeric($val))->values()->all();

                $subConditions = [];
                if (!empty($codes)) {
                    $placeholders = implode(',', array_fill(0, count($codes), '?'));
                    $subConditions[] = "ad.code IN ($placeholders)";
                    $bindings = array_merge($bindings, $codes);
                }
                if (!empty($ids)) {
                    $placeholders = implode(',', array_fill(0, count($ids), '?'));
                    $subConditions[] = "b.area_description_id IN ($placeholders)";
                    $bindings = array_merge($bindings, $ids);
                }
                $conditions[] = '(' . implode(' OR ', $subConditions) . ')';
            } elseif ($hasNumeric) {
                $placeholders = implode(',', array_fill(0, count($areaDescriptions), '?'));
                $conditions[] = "b.area_description_id IN ($placeholders)";
                $bindings = array_merge($bindings, $areaDescriptions);
            } else {
                $placeholders = implode(',', array_fill(0, count($areaDescriptions), '?'));
                $conditions[] = "ad.code IN ($placeholders)";
                $bindings = array_merge($bindings, $areaDescriptions);
            }
        }

        // Parking capacity filter - supports both code and ID
        if ($request->has('parking_capacity')) {
            $parkingCapacities = $this->parseFilterValue($request->parking_capacity);

            $hasNumeric = collect($parkingCapacities)->contains(fn($val) => is_numeric($val));
            $hasString = collect($parkingCapacities)->contains(fn($val) => !is_numeric($val));

            if ($hasNumeric && $hasString) {
                $codes = collect($parkingCapacities)->filter(fn($val) => !is_numeric($val))->values()->all();
                $ids = collect($parkingCapacities)->filter(fn($val) => is_numeric($val))->values()->all();

                $subConditions = [];
                if (!empty($codes)) {
                    $placeholders = implode(',', array_fill(0, count($codes), '?'));
                    $subConditions[] = "pc.code IN ($placeholders)";
                    $bindings = array_merge($bindings, $codes);
                }
                if (!empty($ids)) {
                    $placeholders = implode(',', array_fill(0, count($ids), '?'));
                    $subConditions[] = "b.parking_capacity_id IN ($placeholders)";
                    $bindings = array_merge($bindings, $ids);
                }
                $conditions[] = '(' . implode(' OR ', $subConditions) . ')';
            } elseif ($hasNumeric) {
                $placeholders = implode(',', array_fill(0, count($parkingCapacities), '?'));
                $conditions[] = "b.parking_capacity_id IN ($placeholders)";
                $bindings = array_merge($bindings, $parkingCapacities);
            } else {
                $placeholders = implode(',', array_fill(0, count($parkingCapacities), '?'));
                $conditions[] = "pc.code IN ($placeholders)";
                $bindings = array_merge($bindings, $parkingCapacities);
            }
        }

        // Beach inclination filter - supports both code and ID
        if ($request->has('beach_inclination')) {
            $inclinations = $this->parseFilterValue($request->beach_inclination);

            $hasNumeric = collect($inclinations)->contains(fn($val) => is_numeric($val));
            $hasString = collect($inclinations)->contains(fn($val) => !is_numeric($val));

            if ($hasNumeric && $hasString) {
                $codes = collect($inclinations)->filter(fn($val) => !is_numeric($val))->values()->all();
                $ids = collect($inclinations)->filter(fn($val) => is_numeric($val))->values()->all();

                $subConditions = [];
                if (!empty($codes)) {
                    $placeholders = implode(',', array_fill(0, count($codes), '?'));
                    $subConditions[] = "bi.code IN ($placeholders)";
                    $bindings = array_merge($bindings, $codes);
                }
                if (!empty($ids)) {
                    $placeholders = implode(',', array_fill(0, count($ids), '?'));
                    $subConditions[] = "b.beach_inclination_id IN ($placeholders)";
                    $bindings = array_merge($bindings, $ids);
                }
                $conditions[] = '(' . implode(' OR ', $subConditions) . ')';
            } elseif ($hasNumeric) {
                $placeholders = implode(',', array_fill(0, count($inclinations), '?'));
                $conditions[] = "b.beach_inclination_id IN ($placeholders)";
                $bindings = array_merge($bindings, $inclinations);
            } else {
                $placeholders = implode(',', array_fill(0, count($inclinations), '?'));
                $conditions[] = "bi.code IN ($placeholders)";
                $bindings = array_merge($bindings, $inclinations);
            }
        }

        // Swimming depth filter - supports both code and ID
        if ($request->has('swimming_depth')) {
            $depths = $this->parseFilterValue($request->swimming_depth);

            $hasNumeric = collect($depths)->contains(fn($val) => is_numeric($val));
            $hasString = collect($depths)->contains(fn($val) => !is_numeric($val));

            if ($hasNumeric && $hasString) {
                $codes = collect($depths)->filter(fn($val) => !is_numeric($val))->values()->all();
                $ids = collect($depths)->filter(fn($val) => is_numeric($val))->values()->all();

                $subConditions = [];
                if (!empty($codes)) {
                    $placeholders = implode(',', array_fill(0, count($codes), '?'));
                    $subConditions[] = "sd.code IN ($placeholders)";
                    $bindings = array_merge($bindings, $codes);
                }
                if (!empty($ids)) {
                    $placeholders = implode(',', array_fill(0, count($ids), '?'));
                    $subConditions[] = "b.swimming_depth_of_one_m_id IN ($placeholders)";
                    $bindings = array_merge($bindings, $ids);
                }
                $conditions[] = '(' . implode(' OR ', $subConditions) . ')';
            } elseif ($hasNumeric) {
                $placeholders = implode(',', array_fill(0, count($depths), '?'));
                $conditions[] = "b.swimming_depth_of_one_m_id IN ($placeholders)";
                $bindings = array_merge($bindings, $depths);
            } else {
                $placeholders = implode(',', array_fill(0, count($depths), '?'));
                $conditions[] = "sd.code IN ($placeholders)";
                $bindings = array_merge($bindings, $depths);
            }
        }

        // Occupancy rate filter - supports both code and ID
        if ($request->has('occupancy_rate')) {
            $occupancyRates = $this->parseFilterValue($request->occupancy_rate);

            $hasNumeric = collect($occupancyRates)->contains(fn($val) => is_numeric($val));
            $hasString = collect($occupancyRates)->contains(fn($val) => !is_numeric($val));

            if ($hasNumeric && $hasString) {
                $codes = collect($occupancyRates)->filter(fn($val) => !is_numeric($val))->values()->all();
                $ids = collect($occupancyRates)->filter(fn($val) => is_numeric($val))->values()->all();

                $subConditions = [];
                if (!empty($codes)) {
                    $placeholders = implode(',', array_fill(0, count($codes), '?'));
                    $subConditions[] = "occ.code IN ($placeholders)";
                    $bindings = array_merge($bindings, $codes);
                }
                if (!empty($ids)) {
                    $placeholders = implode(',', array_fill(0, count($ids), '?'));
                    $subConditions[] = "b.occupancy_rate_id IN ($placeholders)";
                    $bindings = array_merge($bindings, $ids);
                }
                $conditions[] = '(' . implode(' OR ', $subConditions) . ')';
            } elseif ($hasNumeric) {
                $placeholders = implode(',', array_fill(0, count($occupancyRates), '?'));
                $conditions[] = "b.occupancy_rate_id IN ($placeholders)";
                $bindings = array_merge($bindings, $occupancyRates);
            } else {
                $placeholders = implode(',', array_fill(0, count($occupancyRates), '?'));
                $conditions[] = "occ.code IN ($placeholders)";
                $bindings = array_merge($bindings, $occupancyRates);
            }
        }

        // Accommodation around filter (many-to-many) - supports both code and ID
        if ($request->has('accommodation_around')) {
            $accommodations = $this->parseFilterValue($request->accommodation_around);

            $hasNumeric = collect($accommodations)->contains(fn($val) => is_numeric($val));
            $hasString = collect($accommodations)->contains(fn($val) => !is_numeric($val));

            if ($hasNumeric && $hasString) {
                $codes = collect($accommodations)->filter(fn($val) => !is_numeric($val))->values()->all();
                $ids = collect($accommodations)->filter(fn($val) => is_numeric($val))->values()->all();

                $subConditions = [];
                if (!empty($codes)) {
                    $placeholders = implode(',', array_fill(0, count($codes), '?'));
                    $subConditions[] = "EXISTS (
                        SELECT 1 FROM accommodation_around_beach aab
                        JOIN accommodation_around aa ON aab.accommodation_around_id = aa.id
                        WHERE aab.beach_id = b.id
                        AND aa.code IN ($placeholders)
                    )";
                    $bindings = array_merge($bindings, $codes);
                }
                if (!empty($ids)) {
                    $placeholders = implode(',', array_fill(0, count($ids), '?'));
                    $subConditions[] = "EXISTS (
                        SELECT 1 FROM accommodation_around_beach
                        WHERE accommodation_around_beach.beach_id = b.id
                        AND accommodation_around_beach.accommodation_around_id IN ($placeholders)
                    )";
                    $bindings = array_merge($bindings, $ids);
                }
                $conditions[] = '(' . implode(' OR ', $subConditions) . ')';
            } elseif ($hasNumeric) {
                $placeholders = implode(',', array_fill(0, count($accommodations), '?'));
                $conditions[] = "EXISTS (
                    SELECT 1 FROM accommodation_around_beach
                    WHERE accommodation_around_beach.beach_id = b.id
                    AND accommodation_around_beach.accommodation_around_id IN ($placeholders)
                )";
                $bindings = array_merge($bindings, $accommodations);
            } else {
                $placeholders = implode(',', array_fill(0, count($accommodations), '?'));
                $conditions[] = "EXISTS (
                    SELECT 1 FROM accommodation_around_beach aab
                    JOIN accommodation_around aa ON aab.accommodation_around_id = aa.id
                    WHERE aab.beach_id = b.id
                    AND aa.code IN ($placeholders)
                )";
                $bindings = array_merge($bindings, $accommodations);
            }
        }

        // Culinary offer filter (many-to-many) - supports both code and ID
        if ($request->has('culinary_offer')) {
            $culinaryOffers = $this->parseFilterValue($request->culinary_offer);

            $hasNumeric = collect($culinaryOffers)->contains(fn($val) => is_numeric($val));
            $hasString = collect($culinaryOffers)->contains(fn($val) => !is_numeric($val));

            if ($hasNumeric && $hasString) {
                $codes = collect($culinaryOffers)->filter(fn($val) => !is_numeric($val))->values()->all();
                $ids = collect($culinaryOffers)->filter(fn($val) => is_numeric($val))->values()->all();

                $subConditions = [];
                if (!empty($codes)) {
                    $placeholders = implode(',', array_fill(0, count($codes), '?'));
                    $subConditions[] = "EXISTS (
                        SELECT 1 FROM culinary_offer_beach cob
                        JOIN culinary_offer co ON cob.culinary_offer_id = co.id
                        WHERE cob.beach_id = b.id
                        AND co.code IN ($placeholders)
                    )";
                    $bindings = array_merge($bindings, $codes);
                }
                if (!empty($ids)) {
                    $placeholders = implode(',', array_fill(0, count($ids), '?'));
                    $subConditions[] = "EXISTS (
                        SELECT 1 FROM culinary_offer_beach
                        WHERE culinary_offer_beach.beach_id = b.id
                        AND culinary_offer_beach.culinary_offer_id IN ($placeholders)
                    )";
                    $bindings = array_merge($bindings, $ids);
                }
                $conditions[] = '(' . implode(' OR ', $subConditions) . ')';
            } elseif ($hasNumeric) {
                $placeholders = implode(',', array_fill(0, count($culinaryOffers), '?'));
                $conditions[] = "EXISTS (
                    SELECT 1 FROM culinary_offer_beach
                    WHERE culinary_offer_beach.beach_id = b.id
                    AND culinary_offer_beach.culinary_offer_id IN ($placeholders)
                )";
                $bindings = array_merge($bindings, $culinaryOffers);
            } else {
                $placeholders = implode(',', array_fill(0, count($culinaryOffers), '?'));
                $conditions[] = "EXISTS (
                    SELECT 1 FROM culinary_offer_beach cob
                    JOIN culinary_offer co ON cob.culinary_offer_id = co.id
                    WHERE cob.beach_id = b.id
                    AND co.code IN ($placeholders)
                )";
                $bindings = array_merge($bindings, $culinaryOffers);
            }
        }

        // Location infrastructure filter (many-to-many) - supports both code and ID
        if ($request->has('location_infrastructure')) {
            $infrastructures = $this->parseFilterValue($request->location_infrastructure);

            $hasNumeric = collect($infrastructures)->contains(fn($val) => is_numeric($val));
            $hasString = collect($infrastructures)->contains(fn($val) => !is_numeric($val));

            if ($hasNumeric && $hasString) {
                $codes = collect($infrastructures)->filter(fn($val) => !is_numeric($val))->values()->all();
                $ids = collect($infrastructures)->filter(fn($val) => is_numeric($val))->values()->all();

                $subConditions = [];
                if (!empty($codes)) {
                    $placeholders = implode(',', array_fill(0, count($codes), '?'));
                    $subConditions[] = "EXISTS (
                        SELECT 1 FROM location_infrastructure_beach lib
                        JOIN location_infrastructure li ON lib.location_infrastructure_id = li.id
                        WHERE lib.beach_id = b.id
                        AND li.code IN ($placeholders)
                    )";
                    $bindings = array_merge($bindings, $codes);
                }
                if (!empty($ids)) {
                    $placeholders = implode(',', array_fill(0, count($ids), '?'));
                    $subConditions[] = "EXISTS (
                        SELECT 1 FROM location_infrastructure_beach
                        WHERE location_infrastructure_beach.beach_id = b.id
                        AND location_infrastructure_beach.location_infrastructure_id IN ($placeholders)
                    )";
                    $bindings = array_merge($bindings, $ids);
                }
                $conditions[] = '(' . implode(' OR ', $subConditions) . ')';
            } elseif ($hasNumeric) {
                $placeholders = implode(',', array_fill(0, count($infrastructures), '?'));
                $conditions[] = "EXISTS (
                    SELECT 1 FROM location_infrastructure_beach
                    WHERE location_infrastructure_beach.beach_id = b.id
                    AND location_infrastructure_beach.location_infrastructure_id IN ($placeholders)
                )";
                $bindings = array_merge($bindings, $infrastructures);
            } else {
                $placeholders = implode(',', array_fill(0, count($infrastructures), '?'));
                $conditions[] = "EXISTS (
                    SELECT 1 FROM location_infrastructure_beach lib
                    JOIN location_infrastructure li ON lib.location_infrastructure_id = li.id
                    WHERE lib.beach_id = b.id
                    AND li.code IN ($placeholders)
                )";
                $bindings = array_merge($bindings, $infrastructures);
            }
        }

        // Natural location condition filter (many-to-many) - supports both code and ID
        if ($request->has('natural_location_condition')) {
            $conditions_filter = $this->parseFilterValue($request->natural_location_condition);

            $hasNumeric = collect($conditions_filter)->contains(fn($val) => is_numeric($val));
            $hasString = collect($conditions_filter)->contains(fn($val) => !is_numeric($val));

            if ($hasNumeric && $hasString) {
                $codes = collect($conditions_filter)->filter(fn($val) => !is_numeric($val))->values()->all();
                $ids = collect($conditions_filter)->filter(fn($val) => is_numeric($val))->values()->all();

                $subConditions = [];
                if (!empty($codes)) {
                    $placeholders = implode(',', array_fill(0, count($codes), '?'));
                    $subConditions[] = "EXISTS (
                        SELECT 1 FROM natural_location_condition_beach nlcb
                        JOIN natural_location_condition nlc ON nlcb.natural_location_condition_id = nlc.id
                        WHERE nlcb.beach_id = b.id
                        AND nlc.code IN ($placeholders)
                    )";
                    $bindings = array_merge($bindings, $codes);
                }
                if (!empty($ids)) {
                    $placeholders = implode(',', array_fill(0, count($ids), '?'));
                    $subConditions[] = "EXISTS (
                        SELECT 1 FROM natural_location_condition_beach
                        WHERE natural_location_condition_beach.beach_id = b.id
                        AND natural_location_condition_beach.natural_location_condition_id IN ($placeholders)
                    )";
                    $bindings = array_merge($bindings, $ids);
                }
                $conditions[] = '(' . implode(' OR ', $subConditions) . ')';
            } elseif ($hasNumeric) {
                $placeholders = implode(',', array_fill(0, count($conditions_filter), '?'));
                $conditions[] = "EXISTS (
                    SELECT 1 FROM natural_location_condition_beach
                    WHERE natural_location_condition_beach.beach_id = b.id
                    AND natural_location_condition_beach.natural_location_condition_id IN ($placeholders)
                )";
                $bindings = array_merge($bindings, $conditions_filter);
            } else {
                $placeholders = implode(',', array_fill(0, count($conditions_filter), '?'));
                $conditions[] = "EXISTS (
                    SELECT 1 FROM natural_location_condition_beach nlcb
                    JOIN natural_location_condition nlc ON nlcb.natural_location_condition_id = nlc.id
                    WHERE nlcb.beach_id = b.id
                    AND nlc.code IN ($placeholders)
                )";
                $bindings = array_merge($bindings, $conditions_filter);
            }
        }

        // Sporting activities filter (many-to-many) - supports both code and ID
        if ($request->has('sporting_activity')) {
            $activities = $this->parseFilterValue($request->sporting_activity);

            $hasNumeric = collect($activities)->contains(fn($val) => is_numeric($val));
            $hasString = collect($activities)->contains(fn($val) => !is_numeric($val));

            if ($hasNumeric && $hasString) {
                $codes = collect($activities)->filter(fn($val) => !is_numeric($val))->values()->all();
                $ids = collect($activities)->filter(fn($val) => is_numeric($val))->values()->all();

                $subConditions = [];
                if (!empty($codes)) {
                    $placeholders = implode(',', array_fill(0, count($codes), '?'));
                    $subConditions[] = "EXISTS (
                        SELECT 1 FROM sporting_activities_beach sab
                        JOIN sporting_activities sa ON sab.sporting_activities_id = sa.id
                        WHERE sab.beach_id = b.id
                        AND sa.code IN ($placeholders)
                    )";
                    $bindings = array_merge($bindings, $codes);
                }
                if (!empty($ids)) {
                    $placeholders = implode(',', array_fill(0, count($ids), '?'));
                    $subConditions[] = "EXISTS (
                        SELECT 1 FROM sporting_activities_beach
                        WHERE sporting_activities_beach.beach_id = b.id
                        AND sporting_activities_beach.sporting_activities_id IN ($placeholders)
                    )";
                    $bindings = array_merge($bindings, $ids);
                }
                $conditions[] = '(' . implode(' OR ', $subConditions) . ')';
            } elseif ($hasNumeric) {
                $placeholders = implode(',', array_fill(0, count($activities), '?'));
                $conditions[] = "EXISTS (
                    SELECT 1 FROM sporting_activities_beach
                    WHERE sporting_activities_beach.beach_id = b.id
                    AND sporting_activities_beach.sporting_activities_id IN ($placeholders)
                )";
                $bindings = array_merge($bindings, $activities);
            } else {
                $placeholders = implode(',', array_fill(0, count($activities), '?'));
                $conditions[] = "EXISTS (
                    SELECT 1 FROM sporting_activities_beach sab
                    JOIN sporting_activities sa ON sab.sporting_activities_id = sa.id
                    WHERE sab.beach_id = b.id
                    AND sa.code IN ($placeholders)
                )";
                $bindings = array_merge($bindings, $activities);
            }
        }

        // Underwater characteristics filter (many-to-many) - supports both code and ID
        if ($request->has('underwater_characteristic')) {
            $characteristics = $this->parseFilterValue($request->underwater_characteristic);

            $hasNumeric = collect($characteristics)->contains(fn($val) => is_numeric($val));
            $hasString = collect($characteristics)->contains(fn($val) => !is_numeric($val));

            if ($hasNumeric && $hasString) {
                $codes = collect($characteristics)->filter(fn($val) => !is_numeric($val))->values()->all();
                $ids = collect($characteristics)->filter(fn($val) => is_numeric($val))->values()->all();

                $subConditions = [];
                if (!empty($codes)) {
                    $placeholders = implode(',', array_fill(0, count($codes), '?'));
                    $subConditions[] = "EXISTS (
                        SELECT 1 FROM underwater_characteristic_beach ucb
                        JOIN underwater_characteristic uc ON ucb.underwater_characteristic_id = uc.id
                        WHERE ucb.beach_id = b.id
                        AND uc.code IN ($placeholders)
                    )";
                    $bindings = array_merge($bindings, $codes);
                }
                if (!empty($ids)) {
                    $placeholders = implode(',', array_fill(0, count($ids), '?'));
                    $subConditions[] = "EXISTS (
                        SELECT 1 FROM underwater_characteristic_beach
                        WHERE underwater_characteristic_beach.beach_id = b.id
                        AND underwater_characteristic_beach.underwater_characteristic_id IN ($placeholders)
                    )";
                    $bindings = array_merge($bindings, $ids);
                }
                $conditions[] = '(' . implode(' OR ', $subConditions) . ')';
            } elseif ($hasNumeric) {
                $placeholders = implode(',', array_fill(0, count($characteristics), '?'));
                $conditions[] = "EXISTS (
                    SELECT 1 FROM underwater_characteristic_beach
                    WHERE underwater_characteristic_beach.beach_id = b.id
                    AND underwater_characteristic_beach.underwater_characteristic_id IN ($placeholders)
                )";
                $bindings = array_merge($bindings, $characteristics);
            } else {
                $placeholders = implode(',', array_fill(0, count($characteristics), '?'));
                $conditions[] = "EXISTS (
                    SELECT 1 FROM underwater_characteristic_beach ucb
                    JOIN underwater_characteristic uc ON ucb.underwater_characteristic_id = uc.id
                    WHERE ucb.beach_id = b.id
                    AND uc.code IN ($placeholders)
                )";
                $bindings = array_merge($bindings, $characteristics);
            }
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

