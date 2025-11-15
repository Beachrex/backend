<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\BeachDetailsResource;
use App\Models\Beach;
use App\Models\BeachTranslation;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="Beach Details",
 *     description="API Endpoints for beach detail pages"
 * )
 */
class BeachDetailsController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v1/beach-details/{identifier}",
     *     operationId="getBeachDetails",
     *     summary="Get comprehensive beach details by slug or ID",
     *     description="Returns all beach information including media galleries, characteristics, facilities, and related data. Accepts either beach ID (numeric) or slug (string) in any language.",
     *     tags={"Beach Details"},
     *     @OA\Parameter(
     *         name="identifier",
     *         in="path",
     *         required=true,
     *         description="Beach ID (numeric) or slug (string in any language)",
     *         @OA\Schema(
     *             oneOf={
     *                 @OA\Schema(type="integer", example=123),
     *                 @OA\Schema(type="string", example="paradise-beach")
     *             }
     *         )
     *     ),
     *     @OA\Parameter(ref="#/components/parameters/AcceptLanguage"),
     *     @OA\Response(
     *         response=200,
     *         description="Beach details found",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="data",
     *                 ref="#/components/schemas/BeachDetailsSchema"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Beach not found"
     *     )
     * )
     */
    public function show(Request $request, $identifier)
    {
        // Determine if identifier is ID or slug
        $beach = $this->findBeachByIdentifier($identifier);

        if (!$beach) {
            return response()->json([
                'message' => 'Beach not found'
            ], 404);
        }

        // Increment view counter
        $beach->increment('beach_views');

        return new BeachDetailsResource($beach);
    }

    /**
     * Find beach by ID or slug (in any language)
     */
    private function findBeachByIdentifier($identifier): ?Beach
    {
        // If identifier is numeric, search by ID
        if (is_numeric($identifier)) {
            return $this->loadBeachWithRelations((int) $identifier);
        }

        // Otherwise, search by slug in translations
        $translation = BeachTranslation::where('slug', $identifier)
            ->whereHas('beach', function ($query) {
                $query->where('status_id', 1); // Only active beaches
            })
            ->first();

        if (!$translation) {
            return null;
        }

        return $this->loadBeachWithRelations($translation->beach_id);
    }

    /**
     * Load beach with all necessary relationships
     */
    private function loadBeachWithRelations(int $beachId): ?Beach
    {
        return Beach::with([
            // Basic relationships
            'translations',
            'status',
            'user',

            // Location relationships with translations
            'continent.translations',
            'country.translations',
            'region.translations',
            'city.translations',
            'island.translations',
            'river.translations',
            'lake.translations',

            // Characteristic relationships with translations
            'waterbody.translations',
            'beachType.translations',
            'areaDescription.translations',
            'waterBodyType.translations',

            // Accessibility & Parking with translations
            'accessibility.translations',
            'roadToParkingType.translations',
            'parkingCapacity.translations',
            'parkingBeachDistance.translations',
            'parkingBeachHeight.translations',

            // Dimensions with translations
            'locationLength.translations',
            'bayLength.translations',
            'beachInclination.translations',
            'swimmingDepthOfOneM.translations',
            'occupancyRate.translations',

            // Many-to-many relationships with translations
            'accommodationArounds.translations',
            'culinaryOffers.translations',
            'locationInfrastructures.translations',
            'naturalLocationConditions.translations',
            'recommendedTransportTypes.translations',
            'sportingActivities.translations',
            'underwaterCharacteristics.translations',
            'userTypes.translations',
            'beachLocationTypes.translations',

            // Media collections - load all active media grouped by collection
            'media' => function ($query) {
                $query->where('status_id', 1)
                      ->with(['mediaCollection', 'mediaType'])
                      ->orderBy('is_primary', 'desc')
                      ->orderBy('order')
                      ->orderBy('created_at');
            }
        ])
        ->where('status_id', 1) // Only active beaches
        ->find($beachId);
    }
}

