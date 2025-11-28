<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\PostGISService;
use App\Services\BeachFilterService;
use App\Services\CacheService;
use App\Traits\CacheAware;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * @OA\Tag(
 *     name="Spatial",
 *     description="PostGIS spatial operations for beaches"
 * )
 */
class PostGISController extends Controller
{
    use CacheAware;

    protected PostGISService $postGISService;
    protected BeachFilterService $filterService;

    public function __construct(PostGISService $postGISService, BeachFilterService $filterService)
    {
        $this->postGISService = $postGISService;
        $this->filterService = $filterService;
    }

    /**
     * @OA\Get(
     *     path="/api/v1/spatial/beaches/within-radius",
     *     operationId="getBeachesWithinRadius",
     *     summary="Find beaches within radius with optional filters",
     *     description="Find all beaches within a specified radius from a given point. Supports all filter parameters from /beaches endpoint",
     *     tags={"Spatial"},
     *     @OA\Parameter(
     *         name="latitude",
     *         in="query",
     *         required=true,
     *         description="Latitude of the center point (-90 to 90)",
     *         @OA\Schema(type="number", format="float", minimum=-90, maximum=90, example=46.0569)
     *     ),
     *     @OA\Parameter(
     *         name="longitude",
     *         in="query",
     *         required=true,
     *         description="Longitude of the center point (-180 to 180)",
     *         @OA\Schema(type="number", format="float", minimum=-180, maximum=180, example=14.5058)
     *     ),
     *     @OA\Parameter(
     *         name="radius",
     *         in="query",
     *         required=true,
     *         description="Search radius in kilometers (0.1 to 20000)",
     *         @OA\Schema(type="number", format="float", minimum=0.1, maximum=20000, example=50)
     *     ),
     *     @OA\Parameter(
     *         name="limit",
     *         in="query",
     *         required=false,
     *         description="Maximum number of beaches to return (1 to 10000, default: 5000)",
     *         @OA\Schema(type="integer", minimum=1, maximum=10000, example=20)
     *     ),
     *     @OA\Parameter(
     *         name="continent_code",
     *         in="query",
     *         description="Filter by continent code(s) or ID(s). Supports: single value (continent_code=EU), comma-separated (continent_code=EU,AS), or array (continent_code[]=EU&continent_code[]=AS). Can mix codes and IDs.",
     *         required=false,
     *         @OA\Schema(type="string", example="EU,AS")
     *     ),
     *     @OA\Parameter(
     *         name="country",
     *         in="query",
     *         description="Filter by country code(s) or ID(s). Supports: single value (country=HR), comma-separated (country=HR,SI,IT), or array (country[]=HR&country[]=SI). Can mix codes and IDs.",
     *         required=false,
     *         @OA\Schema(type="string", example="HR,SI,IT")
     *     ),
     *     @OA\Parameter(
     *         name="waterbody",
     *         in="query",
     *         description="Filter by waterbody code(s) or ID(s). Supports: single value (waterbody=sea), comma-separated (waterbody=sea,lake), or array (waterbody[]=sea&waterbody[]=lake). Can mix codes and IDs.",
     *         required=false,
     *         @OA\Schema(type="string", example="sea,lake")
     *     ),
     *     @OA\Parameter(
     *         name="beach_type",
     *         in="query",
     *         description="Filter by beach type code(s) or ID(s). Supports: single value (beach_type=sand), comma-separated (beach_type=sand,pebble), or array (beach_type[]=sand&beach_type[]=pebble). Can mix codes and IDs.",
     *         required=false,
     *         @OA\Schema(type="string", example="sand,pebble")
     *     ),
     *     @OA\Parameter(
     *         name="user_type",
     *         in="query",
     *         description="Filter by user type code(s) or ID(s). Supports: single value (user_type=family), comma-separated (user_type=family,couple), or array (user_type[]=family&user_type[]=couple). Can mix codes and IDs.",
     *         required=false,
     *         @OA\Schema(type="string", example="family,couple")
     *     ),
     *     @OA\Parameter(
     *         name="region",
     *         in="query",
     *         description="Filter by region code(s) or ID(s). Supports: single value (region=algarve), comma-separated (region=algarve,lisbon), or array (region[]=algarve&region[]=lisbon). Can mix codes and IDs.",
     *         required=false,
     *         @OA\Schema(type="string", example="algarve")
     *     ),
     *     @OA\Parameter(
     *         name="island",
     *         in="query",
     *         description="Filter by island code(s) or ID(s). Supports: single value (island=madeira), comma-separated (island=madeira,azores), or array (island[]=madeira&island[]=azores). Can mix codes and IDs.",
     *         required=false,
     *         @OA\Schema(type="string", example="madeira")
     *     ),
     *     @OA\Parameter(
     *         name="city",
     *         in="query",
     *         description="Filter by city code(s) or ID(s). Supports: single value (city=sukosan), comma-separated (city=sukosan,zadar), or array (city[]=sukosan&city[]=zadar). Can mix codes and IDs.",
     *         required=false,
     *         @OA\Schema(type="string", example="sukosan")
     *     ),
     *     @OA\Parameter(
     *         name="accessibility",
     *         in="query",
     *         description="Filter by accessibility code(s) or ID(s). Supports: single value (accessibility=easy), comma-separated (accessibility=easy,moderate), or array (accessibility[]=easy&accessibility[]=moderate). Can mix codes and IDs.",
     *         required=false,
     *         @OA\Schema(type="string", example="easy,moderate")
     *     ),
     *     @OA\Parameter(
     *         name="parking",
     *         in="query",
     *         description="Filter by parking availability (boolean)",
     *         required=false,
     *         @OA\Schema(type="boolean", example=true)
     *     ),
     *     @OA\Parameter(
     *         name="parking_capacity",
     *         in="query",
     *         description="Filter by parking capacity code(s) or ID(s). Supports: single value (parking_capacity=large), comma-separated (parking_capacity=large,medium), or array (parking_capacity[]=large&parking_capacity[]=medium). Can mix codes and IDs.",
     *         required=false,
     *         @OA\Schema(type="string", example="large,medium")
     *     ),
     *     @OA\Parameter(
     *         name="beach_inclination",
     *         in="query",
     *         description="Filter by beach inclination code(s) or ID(s). Supports: single value (beach_inclination=gentle), comma-separated (beach_inclination=gentle,steep), or array (beach_inclination[]=gentle&beach_inclination[]=steep). Can mix codes and IDs.",
     *         required=false,
     *         @OA\Schema(type="string", example="gentle,steep")
     *     ),
     *     @OA\Parameter(
     *         name="swimming_depth",
     *         in="query",
     *         description="Filter by swimming depth code(s) or ID(s). Supports: single value (swimming_depth=shallow), comma-separated (swimming_depth=shallow,deep), or array (swimming_depth[]=shallow&swimming_depth[]=deep). Can mix codes and IDs.",
     *         required=false,
     *         @OA\Schema(type="string", example="shallow,deep")
     *     ),
     *     @OA\Parameter(
     *         name="occupancy_rate",
     *         in="query",
     *         description="Filter by occupancy rate code(s) or ID(s). Supports: single value (occupancy_rate=low), comma-separated (occupancy_rate=low,medium), or array (occupancy_rate[]=low&occupancy_rate[]=medium). Can mix codes and IDs.",
     *         required=false,
     *         @OA\Schema(type="string", example="low,medium")
     *     ),
     *     @OA\Parameter(
     *         name="area_description",
     *         in="query",
     *         description="Filter by area description code(s) or ID(s). Supports: single value (area_description=urban), comma-separated (area_description=urban,rural), or array (area_description[]=urban&area_description[]=rural). Can mix codes and IDs.",
     *         required=false,
     *         @OA\Schema(type="string", example="urban,rural")
     *     ),
     *     @OA\Parameter(
     *         name="has_swimming_pool",
     *         in="query",
     *         description="Filter by swimming pool availability (boolean)",
     *         required=false,
     *         @OA\Schema(type="boolean", example=true)
     *     ),
     *     @OA\Parameter(
     *         name="boat_only",
     *         in="query",
     *         description="Filter beaches accessible only by boat (boolean)",
     *         required=false,
     *         @OA\Schema(type="boolean", example=false)
     *     ),
     *     @OA\Parameter(
     *         name="accommodation_around",
     *         in="query",
     *         description="Filter by accommodation around code(s) or ID(s). Supports: single value (accommodation_around=hotel), comma-separated (accommodation_around=hotel,apartment), or array (accommodation_around[]=hotel&accommodation_around[]=apartment). Can mix codes and IDs.",
     *         required=false,
     *         @OA\Schema(type="string", example="hotel,apartment")
     *     ),
     *     @OA\Parameter(
     *         name="culinary_offer",
     *         in="query",
     *         description="Filter by culinary offer code(s) or ID(s). Supports: single value (culinary_offer=restaurant), comma-separated (culinary_offer=restaurant,bar), or array (culinary_offer[]=restaurant&culinary_offer[]=bar). Can mix codes and IDs.",
     *         required=false,
     *         @OA\Schema(type="string", example="restaurant,bar")
     *     ),
     *     @OA\Parameter(
     *         name="location_infrastructure",
     *         in="query",
     *         description="Filter by location infrastructure code(s) or ID(s). Supports: single value (location_infrastructure=shower), comma-separated (location_infrastructure=shower,toilet), or array (location_infrastructure[]=shower&location_infrastructure[]=toilet). Can mix codes and IDs.",
     *         required=false,
     *         @OA\Schema(type="string", example="shower,toilet")
     *     ),
     *     @OA\Parameter(
     *         name="natural_location_condition",
     *         in="query",
     *         description="Filter by natural location condition code(s) or ID(s). Supports: single value (natural_location_condition=shaded), comma-separated (natural_location_condition=shaded,sunny), or array (natural_location_condition[]=shaded&natural_location_condition[]=sunny). Can mix codes and IDs.",
     *         required=false,
     *         @OA\Schema(type="string", example="shaded,sunny")
     *     ),
     *     @OA\Parameter(
     *         name="sporting_activity",
     *         in="query",
     *         description="Filter by sporting activity code(s) or ID(s). Supports: single value (sporting_activity=diving), comma-separated (sporting_activity=diving,surfing), or array (sporting_activity[]=diving&sporting_activity[]=surfing). Can mix codes and IDs.",
     *         required=false,
     *         @OA\Schema(type="string", example="diving,surfing")
     *     ),
     *     @OA\Parameter(
     *         name="underwater_characteristic",
     *         in="query",
     *         description="Filter by underwater characteristic code(s) or ID(s). Supports: single value (underwater_characteristic=coral), comma-separated (underwater_characteristic=coral,rocks), or array (underwater_characteristic[]=coral&underwater_characteristic[]=rocks). Can mix codes and IDs.",
     *         required=false,
     *         @OA\Schema(type="string", example="coral,rocks")
     *     ),
     *     @OA\Parameter(ref="#/components/parameters/AcceptLanguage"),
     *     @OA\Response(
     *         response=200,
     *         description="Beaches found within radius with center coordinates",
     *         @OA\JsonContent(ref="#/components/schemas/BeachRadiusResponseSchema")
     *     )
     * )
     */
    public function beachesWithinRadius(Request $request): JsonResponse
    {
        $request->validate([
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'radius' => 'required|numeric|min:0.1|max:20000',
            'limit' => 'sometimes|integer|min:1|max:10000'
        ]);

        // NOTE: Caching disabled for spatial endpoints due to large data size
        // Caching thousands of beaches with relationships causes memory exhaustion

        $beaches = $this->postGISService->getBeachesWithinRadius(
            $request->latitude,
            $request->longitude,
            $request->radius,
            $request->get('limit', 5000),
            $request  // Pass the request for filtering
        );

        // Calculate centroid of returned beaches
        $centroid = $this->postGISService->calculateCentroid(
            $request->latitude,
            $request->longitude,
            $request->radius,
            $request
        );

        $data = [
            'beaches' => $beaches,
            'centroid' => $centroid
        ];

        return response()->json([
            'data' => $data['beaches'],
            'meta' => [
                'search_center' => ['latitude' => (float) $request->latitude, 'longitude' => (float) $request->longitude],
                'results_center' => [
                    'latitude' => $data['centroid'] && isset($data['centroid']->center_lat) ? (float) $data['centroid']->center_lat : null,
                    'longitude' => $data['centroid'] && isset($data['centroid']->center_lng) ? (float) $data['centroid']->center_lng : null
                ],
                'radius_km' => (float) $request->radius,
                'count' => $data['beaches']->count()
            ]
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/spatial/beaches/nearest",
     *     operationId="getNearestBeaches",
     *     summary="Find nearest beaches",
     *     description="Find the nearest beaches to a given point, ordered by distance",
     *     tags={"Spatial"},
     *     @OA\Parameter(
     *         name="latitude",
     *         in="query",
     *         required=true,
     *         description="Latitude of the reference point (-90 to 90)",
     *         @OA\Schema(type="number", format="float", minimum=-90, maximum=90, example=46.0569)
     *     ),
     *     @OA\Parameter(
     *         name="longitude",
     *         in="query",
     *         required=true,
     *         description="Longitude of the reference point (-180 to 180)",
     *         @OA\Schema(type="number", format="float", minimum=-180, maximum=180, example=14.5058)
     *     ),
     *     @OA\Parameter(
     *         name="limit",
     *         in="query",
     *         required=false,
     *         description="Maximum number of beaches to return (1 to 50, default: 10)",
     *         @OA\Schema(type="integer", minimum=1, maximum=50, example=10)
     *     ),
     *     @OA\Parameter(ref="#/components/parameters/AcceptLanguage"),
     *     @OA\Response(response=200, description="Nearest beaches found")
     * )
     */
    public function nearestBeaches(Request $request): JsonResponse
    {
        $request->validate([
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'limit' => 'sometimes|integer|min:1|max:50'
        ]);

        $beaches = $this->postGISService->getNearestBeaches(
            $request->latitude,
            $request->longitude,
            $request->get('limit', 10)
        );

        return response()->json([
            'data' => $beaches,
            'meta' => [
                'reference_point' => ['latitude' => $request->latitude, 'longitude' => $request->longitude],
                'limit' => $request->get('limit', 10),
                'count' => $beaches->count()
            ]
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/spatial/distance/calculate",
     *     operationId="calculateDistance",
     *     summary="Calculate distance between two points",
     *     description="Calculate the distance between two geographic points using PostGIS",
     *     tags={"Spatial"},
     *     @OA\Parameter(
     *         name="lat1",
     *         in="query",
     *         required=true,
     *         description="Latitude of the first point (-90 to 90)",
     *         @OA\Schema(type="number", format="float", minimum=-90, maximum=90, example=46.0569)
     *     ),
     *     @OA\Parameter(
     *         name="lon1",
     *         in="query",
     *         required=true,
     *         description="Longitude of the first point (-180 to 180)",
     *         @OA\Schema(type="number", format="float", minimum=-180, maximum=180, example=14.5058)
     *     ),
     *     @OA\Parameter(
     *         name="lat2",
     *         in="query",
     *         required=true,
     *         description="Latitude of the second point (-90 to 90)",
     *         @OA\Schema(type="number", format="float", minimum=-90, maximum=90, example=45.8150)
     *     ),
     *     @OA\Parameter(
     *         name="lon2",
     *         in="query",
     *         required=true,
     *         description="Longitude of the second point (-180 to 180)",
     *         @OA\Schema(type="number", format="float", minimum=-180, maximum=180, example=15.9819)
     *     ),
     *     @OA\Parameter(ref="#/components/parameters/AcceptLanguage"),
     *     @OA\Response(response=200, description="Distance calculation result")
     * )
     */
    public function calculateDistance(Request $request): JsonResponse
    {
        $request->validate([
            'lat1' => 'required|numeric|between:-90,90',
            'lon1' => 'required|numeric|between:-180,180',
            'lat2' => 'required|numeric|between:-90,90',
            'lon2' => 'required|numeric|between:-180,180'
        ]);

        $distance = $this->postGISService->calculateDistance(
            $request->lat1,
            $request->lon1,
            $request->lat2,
            $request->lon2
        );

        return response()->json([
            'data' => [
                'distance_km' => round($distance, 2),
                'distance_miles' => round($distance * 0.621371, 2)
            ],
            'meta' => [
                'point1' => ['latitude' => $request->lat1, 'longitude' => $request->lon1],
                'point2' => ['latitude' => $request->lat2, 'longitude' => $request->lon2]
            ]
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/spatial/beaches/bounding-box",
     *     operationId="getBeachesInBoundingBox",
     *     summary="Get beaches within bounding box",
     *     description="Find all beaches within a rectangular bounding box defined by minimum and maximum coordinates",
     *     tags={"Spatial"},
     *     @OA\Parameter(
     *         name="min_lat",
     *         in="query",
     *         required=true,
     *         description="Minimum latitude of the bounding box (-90 to 90)",
     *         @OA\Schema(type="number", format="float", minimum=-90, maximum=90, example=45.0)
     *     ),
     *     @OA\Parameter(
     *         name="min_lon",
     *         in="query",
     *         required=true,
     *         description="Minimum longitude of the bounding box (-180 to 180)",
     *         @OA\Schema(type="number", format="float", minimum=-180, maximum=180, example=13.0)
     *     ),
     *     @OA\Parameter(
     *         name="max_lat",
     *         in="query",
     *         required=true,
     *         description="Maximum latitude of the bounding box (-90 to 90)",
     *         @OA\Schema(type="number", format="float", minimum=-90, maximum=90, example=47.0)
     *     ),
     *     @OA\Parameter(
     *         name="max_lon",
     *         in="query",
     *         required=true,
     *         description="Maximum longitude of the bounding box (-180 to 180)",
     *         @OA\Schema(type="number", format="float", minimum=-180, maximum=180, example=16.0)
     *     ),
     *     @OA\Parameter(
     *         name="continent_code",
     *         in="query",
     *         description="Filter by continent code(s) or ID(s). Supports: single value (continent_code=EU), comma-separated (continent_code=EU,AS), or array (continent_code[]=EU&continent_code[]=AS). Can mix codes and IDs.",
     *         required=false,
     *         @OA\Schema(type="string", example="EU,AS")
     *     ),
     *     @OA\Parameter(
     *         name="country",
     *         in="query",
     *         description="Filter by country code(s) or ID(s). Supports: single value (country=HR), comma-separated (country=HR,SI,IT), or array (country[]=HR&country[]=SI). Can mix codes and IDs.",
     *         required=false,
     *         @OA\Schema(type="string", example="HR,SI,IT")
     *     ),
     *     @OA\Parameter(
     *         name="waterbody",
     *         in="query",
     *         description="Filter by waterbody code(s) or ID(s). Supports: single value (waterbody=sea), comma-separated (waterbody=sea,lake), or array (waterbody[]=sea&waterbody[]=lake). Can mix codes and IDs.",
     *         required=false,
     *         @OA\Schema(type="string", example="sea,lake")
     *     ),
     *     @OA\Parameter(
     *         name="beach_type",
     *         in="query",
     *         description="Filter by beach type code(s) or ID(s). Supports: single value (beach_type=sand), comma-separated (beach_type=sand,pebble), or array (beach_type[]=sand&beach_type[]=pebble). Can mix codes and IDs.",
     *         required=false,
     *         @OA\Schema(type="string", example="sand,pebble")
     *     ),
     *     @OA\Parameter(
     *         name="user_type",
     *         in="query",
     *         description="Filter by user type code(s) or ID(s). Supports: single value (user_type=family), comma-separated (user_type=family,couple), or array (user_type[]=family&user_type[]=couple). Can mix codes and IDs.",
     *         required=false,
     *         @OA\Schema(type="string", example="family,couple")
     *     ),
     *     @OA\Parameter(
     *         name="region",
     *         in="query",
     *         description="Filter by region code(s) or ID(s). Supports: single value (region=algarve), comma-separated (region=algarve,lisbon), or array (region[]=algarve&region[]=lisbon). Can mix codes and IDs.",
     *         required=false,
     *         @OA\Schema(type="string", example="algarve")
     *     ),
     *     @OA\Parameter(
     *         name="island",
     *         in="query",
     *         description="Filter by island code(s) or ID(s). Supports: single value (island=madeira), comma-separated (island=madeira,azores), or array (island[]=madeira&island[]=azores). Can mix codes and IDs.",
     *         required=false,
     *         @OA\Schema(type="string", example="madeira")
     *     ),
     *     @OA\Parameter(
     *         name="city",
     *         in="query",
     *         description="Filter by city code(s) or ID(s). Supports: single value (city=sukosan), comma-separated (city=sukosan,zadar), or array (city[]=sukosan&city[]=zadar). Can mix codes and IDs.",
     *         required=false,
     *         @OA\Schema(type="string", example="sukosan")
     *     ),
     *     @OA\Parameter(
     *         name="accessibility",
     *         in="query",
     *         description="Filter by accessibility code(s) or ID(s). Supports: single value (accessibility=easy), comma-separated (accessibility=easy,moderate), or array (accessibility[]=easy&accessibility[]=moderate). Can mix codes and IDs.",
     *         required=false,
     *         @OA\Schema(type="string", example="easy,moderate")
     *     ),
     *     @OA\Parameter(
     *         name="parking",
     *         in="query",
     *         description="Filter by parking availability (boolean)",
     *         required=false,
     *         @OA\Schema(type="boolean", example=true)
     *     ),
     *     @OA\Parameter(
     *         name="parking_capacity",
     *         in="query",
     *         description="Filter by parking capacity code(s) or ID(s). Supports: single value (parking_capacity=large), comma-separated (parking_capacity=large,medium), or array (parking_capacity[]=large&parking_capacity[]=medium). Can mix codes and IDs.",
     *         required=false,
     *         @OA\Schema(type="string", example="large,medium")
     *     ),
     *     @OA\Parameter(
     *         name="beach_inclination",
     *         in="query",
     *         description="Filter by beach inclination code(s) or ID(s). Supports: single value (beach_inclination=gentle), comma-separated (beach_inclination=gentle,steep), or array (beach_inclination[]=gentle&beach_inclination[]=steep). Can mix codes and IDs.",
     *         required=false,
     *         @OA\Schema(type="string", example="gentle,steep")
     *     ),
     *     @OA\Parameter(
     *         name="swimming_depth",
     *         in="query",
     *         description="Filter by swimming depth code(s) or ID(s). Supports: single value (swimming_depth=shallow), comma-separated (swimming_depth=shallow,deep), or array (swimming_depth[]=shallow&swimming_depth[]=deep). Can mix codes and IDs.",
     *         required=false,
     *         @OA\Schema(type="string", example="shallow,deep")
     *     ),
     *     @OA\Parameter(
     *         name="occupancy_rate",
     *         in="query",
     *         description="Filter by occupancy rate code(s) or ID(s). Supports: single value (occupancy_rate=low), comma-separated (occupancy_rate=low,medium), or array (occupancy_rate[]=low&occupancy_rate[]=medium). Can mix codes and IDs.",
     *         required=false,
     *         @OA\Schema(type="string", example="low,medium")
     *     ),
     *     @OA\Parameter(
     *         name="area_description",
     *         in="query",
     *         description="Filter by area description code(s) or ID(s). Supports: single value (area_description=urban), comma-separated (area_description=urban,rural), or array (area_description[]=urban&area_description[]=rural). Can mix codes and IDs.",
     *         required=false,
     *         @OA\Schema(type="string", example="urban,rural")
     *     ),
     *     @OA\Parameter(
     *         name="has_swimming_pool",
     *         in="query",
     *         description="Filter by swimming pool availability (boolean)",
     *         required=false,
     *         @OA\Schema(type="boolean", example=true)
     *     ),
     *     @OA\Parameter(
     *         name="boat_only",
     *         in="query",
     *         description="Filter beaches accessible only by boat (boolean)",
     *         required=false,
     *         @OA\Schema(type="boolean", example=false)
     *     ),
     *     @OA\Parameter(
     *         name="accommodation_around",
     *         in="query",
     *         description="Filter by accommodation around code(s) or ID(s). Supports: single value (accommodation_around=hotel), comma-separated (accommodation_around=hotel,apartment), or array (accommodation_around[]=hotel&accommodation_around[]=apartment). Can mix codes and IDs.",
     *         required=false,
     *         @OA\Schema(type="string", example="hotel,apartment")
     *     ),
     *     @OA\Parameter(
     *         name="culinary_offer",
     *         in="query",
     *         description="Filter by culinary offer code(s) or ID(s). Supports: single value (culinary_offer=restaurant), comma-separated (culinary_offer=restaurant,bar), or array (culinary_offer[]=restaurant&culinary_offer[]=bar). Can mix codes and IDs.",
     *         required=false,
     *         @OA\Schema(type="string", example="restaurant,bar")
     *     ),
     *     @OA\Parameter(
     *         name="location_infrastructure",
     *         in="query",
     *         description="Filter by location infrastructure code(s) or ID(s). Supports: single value (location_infrastructure=shower), comma-separated (location_infrastructure=shower,toilet), or array (location_infrastructure[]=shower&location_infrastructure[]=toilet). Can mix codes and IDs.",
     *         required=false,
     *         @OA\Schema(type="string", example="shower,toilet")
     *     ),
     *     @OA\Parameter(
     *         name="natural_location_condition",
     *         in="query",
     *         description="Filter by natural location condition code(s) or ID(s). Supports: single value (natural_location_condition=shaded), comma-separated (natural_location_condition=shaded,sunny), or array (natural_location_condition[]=shaded&natural_location_condition[]=sunny). Can mix codes and IDs.",
     *         required=false,
     *         @OA\Schema(type="string", example="shaded,sunny")
     *     ),
     *     @OA\Parameter(
     *         name="sporting_activity",
     *         in="query",
     *         description="Filter by sporting activity code(s) or ID(s). Supports: single value (sporting_activity=diving), comma-separated (sporting_activity=diving,surfing), or array (sporting_activity[]=diving&sporting_activity[]=surfing). Can mix codes and IDs.",
     *         required=false,
     *         @OA\Schema(type="string", example="diving,surfing")
     *     ),
     *     @OA\Parameter(
     *         name="underwater_characteristic",
     *         in="query",
     *         description="Filter by underwater characteristic code(s) or ID(s). Supports: single value (underwater_characteristic=coral), comma-separated (underwater_characteristic=coral,rocks), or array (underwater_characteristic[]=coral&underwater_characteristic[]=rocks). Can mix codes and IDs.",
     *         required=false,
     *         @OA\Schema(type="string", example="coral,rocks")
     *     ),
     *     @OA\Parameter(ref="#/components/parameters/AcceptLanguage"),
     *     @OA\Response(
     *         response=200,
     *         description="Beaches found within bounding box with center coordinates",
     *         @OA\JsonContent(ref="#/components/schemas/BeachBoundingBoxResponseSchema")
     *     )
     * )
     */
    public function beachesInBoundingBox(Request $request): JsonResponse
    {
        $request->validate([
            'min_lat' => 'required|numeric|between:-90,90',
            'min_lon' => 'required|numeric|between:-180,180',
            'max_lat' => 'required|numeric|between:-90,90',
            'max_lon' => 'required|numeric|between:-180,180'
        ]);

        // NOTE: Caching disabled for spatial endpoints due to large data size
        // Caching thousands of beaches with relationships causes memory exhaustion

        $beaches = $this->postGISService->getBeachesInBoundingBox(
            $request->min_lat,
            $request->min_lon,
            $request->max_lat,
            $request->max_lon,
            $request  // Pass the request for filtering
        );

        // Calculate centroid of returned beaches
        $centroid = $this->postGISService->calculateCentroidForBoundingBox(
            $request->min_lat,
            $request->min_lon,
            $request->max_lat,
            $request->max_lon,
            $request
        );

        $data = [
            'beaches' => $beaches,
            'centroid' => $centroid
        ];

        return response()->json([
            'data' => $data['beaches'],
            'meta' => [
                'bounding_box' => [
                    'min_latitude' => $data['centroid'] && isset($data['centroid']->min_lat) && $data['centroid']->min_lat !== null ? (float) $data['centroid']->min_lat : (float) $request->min_lat,
                    'min_longitude' => $data['centroid'] && isset($data['centroid']->min_lng) && $data['centroid']->min_lng !== null ? (float) $data['centroid']->min_lng : (float) $request->min_lon,
                    'max_latitude' => $data['centroid'] && isset($data['centroid']->max_lat) && $data['centroid']->max_lat !== null ? (float) $data['centroid']->max_lat : (float) $request->max_lat,
                    'max_longitude' => $data['centroid'] && isset($data['centroid']->max_lng) && $data['centroid']->max_lng !== null ? (float) $data['centroid']->max_lng : (float) $request->max_lon
                ],
                'center' => [
                    'latitude' => $data['centroid'] && isset($data['centroid']->center_lat) ? (float) $data['centroid']->center_lat : null,
                    'longitude' => $data['centroid'] && isset($data['centroid']->center_lng) ? (float) $data['centroid']->center_lng : null
                ],
                'count' => $data['beaches']->count()
            ]
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/spatial/beaches/density-grid",
     *     operationId="getBeachDensityGrid",
     *     summary="Get beach density grid",
     *     description="Generate a density grid showing beach distribution across geographic regions",
     *     tags={"Spatial"},
     *     @OA\Parameter(
     *         name="grid_size",
     *         in="query",
     *         required=false,
     *         description="Grid cell size in degrees (0.1 to 10, default: 1.0)",
     *         @OA\Schema(type="number", format="float", minimum=0.1, maximum=10, example=1.0)
     *     ),
     *     @OA\Parameter(ref="#/components/parameters/AcceptLanguage"),
     *     @OA\Response(response=200, description="Beach density grid data")
     * )
     */
    public function beachDensityGrid(Request $request): JsonResponse
    {
        $request->validate([
            'grid_size' => 'sometimes|numeric|min:0.1|max:10'
        ]);

        $density = $this->postGISService->getBeachDensityGrid(
            $request->get('grid_size', 1.0)
        );

        return response()->json([
            'data' => $density,
            'meta' => [
                'grid_size_degrees' => $request->get('grid_size', 1.0),
                'grid_size_km_approx' => round($request->get('grid_size', 1.0) * 111, 1),
                'count' => $density->count()
            ]
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/spatial/beaches/statistics",
     *     operationId="getBeachStatistics",
     *     summary="Get beach statistics",
     *     tags={"Spatial"},
     *     @OA\Parameter(ref="#/components/parameters/AcceptLanguage"),
     *     @OA\Response(response=200, description="Beach statistics")
     * )
     */
    public function beachStatistics(): JsonResponse
    {
        $stats = $this->postGISService->getBeachStatistics();

        return response()->json([
            'data' => $stats
        ]);
    }
}
