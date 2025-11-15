<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\BeachDetailsResource;
use App\Models\Beach;
use App\Models\BeachTranslation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

        // Get next and previous beaches using PostGIS
        $locale = app()->getLocale();
        $navigation = $this->getNavigationBeaches($beach, $locale);

        $resource = new BeachDetailsResource($beach);
        $resourceArray = $resource->toArray($request);

        // Add navigation to response
        $resourceArray['navigation'] = $navigation;

        return response()->json(['data' => $resourceArray]);
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

    /**
     * Get next and previous beaches based on spatial proximity using PostGIS
     */
    private function getNavigationBeaches(Beach $currentBeach, string $locale): array
    {
        // Return null navigation if beach doesn't have coordinates
        if (!$currentBeach->latitude || !$currentBeach->longitude) {
            return [
                'previous' => null,
                'next' => null,
            ];
        }

        // Get the two nearest beaches (excluding current beach)
        // Using PostGIS ST_Distance for accurate spatial calculations
        $nearestBeaches = DB::select("
            SELECT
                b.id,
                b.folder_name,
                b.mobile_upload,
                COALESCE(bt.name, bt_en.name) as name,
                COALESCE(bt.slug, bt_en.slug) as slug,
                b.latitude,
                b.longitude,
                COALESCE(
                    (SELECT m.file_name
                     FROM media m
                     INNER JOIN media_types mt ON m.media_type_id = mt.id
                     INNER JOIN media_collections mc ON m.media_collection_id = mc.id
                     WHERE m.mediable_id = b.id
                       AND m.mediable_type = 'App\\Models\\Beach'
                       AND mt.code = 'image'
                       AND mc.code = 'beach_gallery'
                       AND m.status_id = 1
                       AND m.is_primary = TRUE
                     LIMIT 1),
                    (SELECT m.file_name
                     FROM media m
                     INNER JOIN media_types mt ON m.media_type_id = mt.id
                     INNER JOIN media_collections mc ON m.media_collection_id = mc.id
                     WHERE m.mediable_id = b.id
                       AND m.mediable_type = 'App\\Models\\Beach'
                       AND mt.code = 'image'
                       AND mc.code = 'beach_gallery'
                       AND m.status_id = 1
                     ORDER BY m.order, m.created_at
                     LIMIT 1)
                ) as main_image,
                ST_Distance(
                    ST_Transform(b.location, 3857),
                    ST_Transform(ST_SetSRID(ST_MakePoint(?, ?), 4326), 3857)
                ) / 1000.0 as distance_km
            FROM beach b
            LEFT JOIN beach_translation bt ON b.id = bt.beach_id AND bt.locale = ?
            LEFT JOIN beach_translation bt_en ON b.id = bt_en.beach_id AND bt_en.locale = 'en'
            WHERE b.id != ?
              AND b.status_id = 1
              AND b.location IS NOT NULL
              AND b.latitude IS NOT NULL
              AND b.longitude IS NOT NULL
            ORDER BY ST_Distance(
                ST_Transform(b.location, 3857),
                ST_Transform(ST_SetSRID(ST_MakePoint(?, ?), 4326), 3857)
            )
            LIMIT 2
        ", [
            $currentBeach->longitude,
            $currentBeach->latitude,
            $locale,
            $currentBeach->id,
            $currentBeach->longitude,
            $currentBeach->latitude,
        ]);

        // Format the results
        $previous = isset($nearestBeaches[0]) ? [
            'id' => $nearestBeaches[0]->id,
            'name' => $nearestBeaches[0]->name,
            'slug' => $nearestBeaches[0]->slug,
            'latitude' => $nearestBeaches[0]->latitude,
            'longitude' => $nearestBeaches[0]->longitude,
            'distance_km' => round($nearestBeaches[0]->distance_km, 2),
            'main_image' => $this->formatMainImage($nearestBeaches[0]),
        ] : null;

        $next = isset($nearestBeaches[1]) ? [
            'id' => $nearestBeaches[1]->id,
            'name' => $nearestBeaches[1]->name,
            'slug' => $nearestBeaches[1]->slug,
            'latitude' => $nearestBeaches[1]->latitude,
            'longitude' => $nearestBeaches[1]->longitude,
            'distance_km' => round($nearestBeaches[1]->distance_km, 2),
            'main_image' => $this->formatMainImage($nearestBeaches[1]),
        ] : null;

        return [
            'previous' => $previous,
            'next' => $next,
        ];
    }

    /**
     * Format main image path for navigation beaches
     */
    private function formatMainImage($beach): ?string
    {
        if (!$beach->main_image || !$beach->folder_name) {
            return null;
        }

        // Determine the path based on mobile_upload flag
        $path = $beach->mobile_upload ? 'img/beaches_mobile/' : 'img/beaches/';

        return $path . $beach->folder_name . '/' . $beach->main_image;
    }
}

