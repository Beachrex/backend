<?php

namespace App\Models;

use App\Traits\Cacheable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Cache;


class Beach extends Model
{
    use Cacheable;
    protected $table = 'beach';

    protected $fillable = [
        'user_id',
        'name',
        'latitude',
        'longitude',
        'status_id',
        'continent_code',
        'country_code',
        'waterbody_id',
        'beach_type_id',
        'area_description_id',
        'accessibility_id',
        'road_to_parking_type_id',
        'parking',
        'parking_capacity_id',
        'parking_beach_distance_id',
        'parking_beach_height_id',
        'location_length_id',
        'bay_length_id',
        'beach_inclination_id',
        'swimming_depth_of_one_m_id',
        'occupancy_rate_id',
    ];

    public function translations(): HasMany
    {
        return $this->hasMany(BeachTranslation::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(Status::class);
    }

    public function continent(): BelongsTo
    {
        return $this->belongsTo(Continent::class, 'continent_code', 'code');
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'country_code', 'code');
    }

    public function waterbody(): BelongsTo
    {
        return $this->belongsTo(Waterbody::class);
    }

    public function beachType(): BelongsTo
    {
        return $this->belongsTo(BeachType::class);
    }

    public function areaDescription(): BelongsTo
    {
        return $this->belongsTo(AreaDescription::class);
    }

    public function accessibility(): BelongsTo
    {
        return $this->belongsTo(Accessibility::class);
    }

    public function roadToParkingType(): BelongsTo
    {
        return $this->belongsTo(RoadToParkingType::class);
    }

    public function parkingCapacity(): BelongsTo
    {
        return $this->belongsTo(ParkingCapacity::class);
    }

    public function parkingBeachDistance(): BelongsTo
    {
        return $this->belongsTo(ParkingBeachDistance::class);
    }

    public function parkingBeachHeight(): BelongsTo
    {
        return $this->belongsTo(ParkingBeachHeight::class);
    }

    public function locationLength(): BelongsTo
    {
        return $this->belongsTo(LocationLength::class);
    }

    public function bayLength(): BelongsTo
    {
        return $this->belongsTo(BayLength::class);
    }

    public function beachInclination(): BelongsTo
    {
        return $this->belongsTo(BeachInclination::class);
    }

    public function swimmingDepthOfOneM(): BelongsTo
    {
        return $this->belongsTo(SwimmingDepthOfOneM::class);
    }

    public function occupancyRate(): BelongsTo
    {
        return $this->belongsTo(OccupancyRate::class);
    }

    public function accommodationArounds(): BelongsToMany
    {
        return $this->belongsToMany(AccommodationAround::class, 'accommodation_around_beach')
            ->withPivot('status_id')
            ->withTimestamps();
    }

    public function culinaryOffers(): BelongsToMany
    {
        return $this->belongsToMany(CulinaryOffer::class, 'culinary_offer_beach')
            ->withPivot('status_id')
            ->withTimestamps();
    }

    public function locationInfrastructures(): BelongsToMany
    {
        return $this->belongsToMany(LocationInfrastructure::class, 'location_infrastructure_beach')
            ->withPivot('status_id')
            ->withTimestamps();
    }

    public function naturalLocationConditions(): BelongsToMany
    {
        return $this->belongsToMany(NaturalLocationCondition::class, 'natural_location_condition_beach')
            ->withPivot('status_id')
            ->withTimestamps();
    }

    public function recommendedTransportTypes(): BelongsToMany
    {
        return $this->belongsToMany(RecommendedTransportType::class, 'recommended_transport_type_beach')
            ->withPivot('status_id')
            ->withTimestamps();
    }

    public function sportingActivities(): BelongsToMany
    {
        return $this->belongsToMany(SportingActivities::class, 'sporting_activities_beach')
            ->withPivot('status_id')
            ->withTimestamps();
    }

    public function underwaterCharacteristics(): BelongsToMany
    {
        return $this->belongsToMany(UnderwaterCharacteristic::class, 'underwater_characteristic_beach')
            ->withPivot('status_id')
            ->withTimestamps();
    }

    public function userTypes(): BelongsToMany
    {
        return $this->belongsToMany(UserType::class, 'user_type_beach')
            ->withPivot('status_id')
            ->withTimestamps();
    }

    /**
     * Clear beach-specific caches
     * When a beach is created/updated/deleted, clear all related caches
     */
    public function clearModelCache(): void
    {
        // Clear reference data cache for this model
        $this->cacheService()->clearReferenceCache('Beach');

        // Clear continent caches
        if ($this->continent_code) {
            Cache::forget("continent:show:{$this->continent_code}");
        }
        Cache::forget('continents:index');

        // Clear country caches
        if ($this->country_code) {
            Cache::forget("country:show:{$this->country_code}");
        }
        Cache::forget('countries:index:all');

        // Clear waterbody caches
        if ($this->waterbody_id) {
            Cache::forget("waterbody:show:{$this->waterbody_id}");
        }
        Cache::forget('waterbodies:index:all');
        Cache::forget('waterbodies:index:filtered');

        // Clear all beach type caches
        Cache::forget('beach_types:index:all');
        Cache::forget('beach_types:index:filtered');

        // Clear beach count caches
        $this->cacheService()->clearBeachCountCache();
    }
}
