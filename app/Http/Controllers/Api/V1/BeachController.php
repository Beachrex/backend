<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\BeachResource;
use App\Models\Beach;
use App\Services\BeachFilterService;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="Beaches",
 *     description="API Endpoints for managing beaches"
 * )
 */
class BeachController extends Controller
{
    protected BeachFilterService $filterService;

    public function __construct(BeachFilterService $filterService)
    {
        $this->filterService = $filterService;
    }

    /**
     * @OA\Get(
     *     path="/api/v1/beaches",
     *     operationId="getBeaches",
     *     summary="List all beaches with optional filters",
     *     tags={"Beaches"},
     *     @OA\Parameter(ref="#/components/parameters/AcceptLanguage"),
     *     @OA\Parameter(
     *         name="limit",
     *         in="query",
     *         description="Maximum number of beaches to return (default: 5000)",
     *         required=false,
     *         @OA\Schema(type="integer", example=50, default=5000)
     *     ),
     *     @OA\Parameter(
     *         name="country",
     *         in="query",
     *         description="Filter by country code(s) or ID(s). Supports: single value (country=HR), comma-separated (country=HR,SI,IT), or array (country[]=HR&country[]=SI). Can mix codes and IDs.",
     *         required=false,
     *         @OA\Schema(type="string", example="HR,SI,IT")
     *     ),
     *     @OA\Parameter(
     *         name="region",
     *         in="query",
     *         description="Filter by region ID(s). Can be single value or array",
     *         required=false,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="island",
     *         in="query",
     *         description="Filter by island ID(s). Can be single value or array",
     *         required=false,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="waterbody",
     *         in="query",
     *         description="Filter by waterbody ID(s). Can be single value or array",
     *         required=false,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="beach_type",
     *         in="query",
     *         description="Filter by beach type ID(s). Supports: single value (beach_type=1), comma-separated (beach_type=1,2,3), or array (beach_type[]=1&beach_type[]=2)",
     *         required=false,
     *         @OA\Schema(type="string", example="1,2,3")
     *     ),
     *     @OA\Parameter(
     *         name="user_type",
     *         in="query",
     *         description="Filter by user type ID(s). Can be single value or array",
     *         required=false,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="parking",
     *         in="query",
     *         description="Filter by parking availability (true/false)",
     *         required=false,
     *         @OA\Schema(type="boolean", example=true)
     *     ),
     *     @OA\Parameter(
     *         name="has_swimming_pool",
     *         in="query",
     *         description="Filter by swimming pool availability (true/false)",
     *         required=false,
     *         @OA\Schema(type="boolean", example=true)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="List of beaches",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(ref="#/components/schemas/BeachSchema")
     *             )
     *         )
     *     )
     * )
     */
    public function index(Request $request)
    {
        $limit = $request->input('limit', 5000);

        $query = Beach::with([
            'translations',
            'status',
            'user',
            // Eager load all feature relationships with their translations
            'waterbody.translations',
            'beachType.translations',
            'areaDescription.translations',
            'accessibility.translations',
            'roadToParkingType.translations',
            'parkingCapacity.translations',
            'parkingBeachDistance.translations',
            'parkingBeachHeight.translations',
            'locationLength.translations',
            'bayLength.translations',
            'beachInclination.translations',
            'swimmingDepthOfOneM.translations',
            'occupancyRate.translations',
            // Eager load many-to-many relationships with their translations
            'accommodationArounds.translations',
            'culinaryOffers.translations',
            'locationInfrastructures.translations',
            'naturalLocationConditions.translations',
            'recommendedTransportTypes.translations',
            'sportingActivities.translations',
            'underwaterCharacteristics.translations',
            'userTypes.translations',
            // Eager load location relationships with translations
            'continent.translations',
            'country.translations',
            'region.translations',
            'island.translations',
            // Eager load media
            'media' => function ($query) {
                $query->where('media_collection_id', 1)
                      ->where('is_primary', true)
                      ->where('status_id', 1);
            }
        ]);

        // Apply filters
        $query = $this->filterService->applyFilters($query, $request);

        $beaches = $query->limit($limit)->get();

        return BeachResource::collection($beaches);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/beaches",
     *     operationId="createBeach",
     *     summary="Create a new beach",
     *     tags={"Beaches"},
     *     @OA\Parameter(ref="#/components/parameters/AcceptLanguage"),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name", "latitude", "longitude", "translations"},
     *             @OA\Property(property="name", type="string", example="Fallback beach name"),
     *             @OA\Property(property="latitude", type="number", format="float", example=45.1234567),
     *             @OA\Property(property="longitude", type="number", format="float", example=13.9876543),
     *             @OA\Property(
     *                 property="translations",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="locale", type="string", example="en"),
     *                     @OA\Property(property="name", type="string", example="Children's Paradise Beach"),
     *                     @OA\Property(property="description", type="string", example="A beach perfect for families."),
     *                     @OA\Property(property="slug", type="string", example="childrens-paradise-beach")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Beach created",
     *         @OA\JsonContent(ref="#/components/schemas/BeachSchema")
     *     ),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'nullable|string|max:255',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'status_id' => 'nullable|exists:status,id',
            'user_id' => 'nullable|exists:users,id',
            'translations' => 'required|array|min:1',
            'translations.*.locale' => 'required|string|size:2',
            'translations.*.name' => 'required|string|max:255',
            'translations.*.description' => 'nullable|string',
            'translations.*.slug' => 'nullable|string|max:255',
        ]);

        $beach = Beach::create([
            'name' => $data['name'] ?? null,
            'latitude' => $data['latitude'],
            'longitude' => $data['longitude'],
            'status_id' => $data['status_id'] ?? 1,
            'user_id' => $data['user_id'] ?? null,
        ]);

        foreach ($data['translations'] as $trans) {
            $beach->translations()->create([
                'locale' => $trans['locale'],
                'name' => $trans['name'],
                'description' => $trans['description'] ?? null,
                'slug' => $trans['slug'] ?? null,
            ]);
        }

        return new BeachResource($beach->load([
            'translations',
            'status',
            'user',
            // Eager load all feature relationships with their translations
            'waterbody.translations',
            'beachType.translations',
            'areaDescription.translations',
            'accessibility.translations',
            'roadToParkingType.translations',
            'parkingCapacity.translations',
            'parkingBeachDistance.translations',
            'parkingBeachHeight.translations',
            'locationLength.translations',
            'bayLength.translations',
            'beachInclination.translations',
            'swimmingDepthOfOneM.translations',
            'occupancyRate.translations',
            // Eager load many-to-many relationships with their translations
            'accommodationArounds.translations',
            'culinaryOffers.translations',
            'locationInfrastructures.translations',
            'naturalLocationConditions.translations',
            'recommendedTransportTypes.translations',
            'sportingActivities.translations',
            'underwaterCharacteristics.translations',
            'userTypes.translations',
            // Eager load location relationships with translations
            'continent.translations',
            'country.translations',
            'island.translations',
        ]));
    }

    /**
     * @OA\Get(
     *     path="/api/v1/beaches/{id}",
     *     operationId="getBeachById",
     *     summary="Get a single beach by ID",
     *     tags={"Beaches"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Beach ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(ref="#/components/parameters/AcceptLanguage"),
     *     @OA\Response(
     *         response=200,
     *         description="Beach found",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="data",
     *                 ref="#/components/schemas/BeachSchema"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Not found"
     *     )
     * )
     */
    public function show($id)
    {
        $beach = Beach::with([
            'translations',
            'status',
            'user',
            // Eager load all feature relationships with their translations
            'waterbody.translations',
            'beachType.translations',
            'areaDescription.translations',
            'accessibility.translations',
            'roadToParkingType.translations',
            'parkingCapacity.translations',
            'parkingBeachDistance.translations',
            'parkingBeachHeight.translations',
            'locationLength.translations',
            'bayLength.translations',
            'beachInclination.translations',
            'swimmingDepthOfOneM.translations',
            'occupancyRate.translations',
            // Eager load many-to-many relationships with their translations
            'accommodationArounds.translations',
            'culinaryOffers.translations',
            'locationInfrastructures.translations',
            'naturalLocationConditions.translations',
            'recommendedTransportTypes.translations',
            'sportingActivities.translations',
            'underwaterCharacteristics.translations',
            'userTypes.translations',
            // Eager load location relationships with translations
            'continent.translations',
            'country.translations',
            'island.translations',
            // Eager load media
            'media' => function ($query) {
                $query->where('media_collection_id', 1)
                      ->where('is_primary', true)
                      ->where('status_id', 1);
            }
        ])->findOrFail($id);

        return new BeachResource($beach);
    }
}
