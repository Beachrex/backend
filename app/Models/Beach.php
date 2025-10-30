<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;


class Beach extends Model
{
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
}
