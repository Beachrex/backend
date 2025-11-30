<?php

namespace App\Models;

use App\Models\Traits\HasMedia;
use App\Traits\Cacheable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Facades\Cache;
use Laravel\Scout\Searchable;


class Beach extends Model
{
    use HasFactory, Cacheable, HasMedia, Searchable;
    protected $table = 'beach';

    protected $fillable = [
        'user_id',
        'name',
        'folder_name',
        'mobile_upload',
        'latitude',
        'longitude',
        'status_id',
        'continent_code',
        'country_code',
        'region_id',
        'city_id',
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
        'water_body_type_id',
        'island_id',
        'river_id',
        'lake_id',
        'video_url',
        'webcam',
        'path_to_beach_map_url',
        'beach_weather',
        'water_temperature',
        'boat_only',
        'beach_views',
        'indoor_pools_count',
        'outdoor_pools_count',
        'indoor_area_m2',
        'outdoor_area_m2',
        'has_swimming_pool',
        'has_indoor_swimming_lanes',
        'has_outdoor_swimming_lanes',
        'has_indoor_children_pool',
        'has_outdoor_children_pool',
        'has_indoor_diving_board',
        'has_outdoor_diving_board',
        'indoor_water_slides_count',
        'outdoor_water_slides_count',
        'has_thermal_pool',
        'has_indoor_climbing_wall',
        'has_outdoor_climbing_wall',
        'has_indoor_lazy_river',
        'has_outdoor_lazy_river',
        'has_indoor_wave_pool',
        'has_outdoor_wave_pool',
        'has_indoor_whirlpool',
        'has_outdoor_whirlpool',
        'has_indoor_surfing',
        'has_outdoor_surfing',
        'has_sauna',
        'has_wellness',
    ];

    protected $casts = [
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'parking' => 'integer',
        'mobile_upload' => 'boolean',
        'boat_only' => 'boolean',
        'beach_views' => 'integer',
        'indoor_area_m2' => 'decimal:2',
        'outdoor_area_m2' => 'decimal:2',
        'has_swimming_pool' => 'boolean',
        'has_indoor_swimming_lanes' => 'boolean',
        'has_outdoor_swimming_lanes' => 'boolean',
        'has_indoor_children_pool' => 'boolean',
        'has_outdoor_children_pool' => 'boolean',
        'has_indoor_diving_board' => 'boolean',
        'has_outdoor_diving_board' => 'boolean',
        'has_thermal_pool' => 'boolean',
        'has_indoor_climbing_wall' => 'boolean',
        'has_outdoor_climbing_wall' => 'boolean',
        'has_indoor_lazy_river' => 'boolean',
        'has_outdoor_lazy_river' => 'boolean',
        'has_indoor_wave_pool' => 'boolean',
        'has_outdoor_wave_pool' => 'boolean',
        'has_indoor_whirlpool' => 'boolean',
        'has_outdoor_whirlpool' => 'boolean',
        'has_indoor_surfing' => 'boolean',
        'has_outdoor_surfing' => 'boolean',
        'has_sauna' => 'boolean',
        'has_wellness' => 'boolean',
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

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
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

    public function beachLocationTypes(): BelongsToMany
    {
        return $this->belongsToMany(BeachLocationType::class, 'beach_beach_location_type');
    }

    public function waterBodyType(): BelongsTo
    {
        return $this->belongsTo(WaterBodyType::class);
    }

    public function island(): BelongsTo
    {
        return $this->belongsTo(Island::class);
    }

    public function river(): BelongsTo
    {
        return $this->belongsTo(River::class);
    }

    public function lake(): BelongsTo
    {
        return $this->belongsTo(Lake::class);
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
     * Get primary media as a relationship (for eager loading)
     */
    public function primaryMediaRelation(): MorphOne
    {
        return $this->morphOne(Media::class, 'mediable')
            ->where('status_id', 1)
            ->where('is_primary', true)
            ->orderBy('order')
            ->orderBy('created_at');
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

    /**
     * Get the indexable data array for the model (for Meilisearch)
     *
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        // Load relationships if not already loaded
        $this->loadMissing(['country', 'region', 'city', 'island', 'translations']);

        // Get English translation for description
        $enTranslation = $this->translations->where('locale', 'en')->first();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $enTranslation?->slug ?? $this->name,
            'description' => $enTranslation?->description ?? '',
            'country_code' => $this->country_code,
            'country_name' => $this->country?->name ?? '',
            'region_name' => $this->region?->name ?? '',
            'city_name' => $this->city?->name ?? '',
            'island_name' => $this->island?->name ?? '',
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'main_image' => $this->main_image,
            'type' => 'beach',
        ];
    }

    /**
     * Get the index name for the model
     *
     * @return string
     */
    public function searchableAs(): string
    {
        return 'beaches';
    }

    /**
     * Determine if the model should be searchable
     *
     * @return bool
     */
    public function shouldBeSearchable(): bool
    {
        // Only index active beaches
        return $this->status_id === 1;
    }
}
