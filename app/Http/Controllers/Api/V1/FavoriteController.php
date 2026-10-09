<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\BeachResource;
use App\Models\Beach;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

/**
 * B-M3 (TASK-005, web H6): saved beaches per user. Adding and removing are idempotent, so the app can retry
 * after a network error without side effects.
 */
class FavoriteController extends Controller
{
    /** Keeps one account from growing an unbounded list (config mobile.favorites_limit). */
    private function limit(): int
    {
        return (int) config('mobile.favorites_limit', 500);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/me/favorites",
     *     operationId="getMyFavorites",
     *     tags={"Favorites"},
     *     summary="My saved beaches (newest first), as beach cards",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="Accept-Language", in="header", required=false, @OA\Schema(type="string", example="sl")),
     *     @OA\Response(response=200, description="Saved beaches", @OA\JsonContent(
     *         @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/BeachSchema")),
     *         @OA\Property(property="meta", type="object", @OA\Property(property="total", type="integer"), @OA\Property(property="limit", type="integer"))
     *     )),
     *     @OA\Response(response=401, description="Not signed in")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $ids = $this->favoriteIds($request->user()->id);
        $beaches = Beach::whereIn('id', $ids)->where('status_id', 1)->with(BeachResource::cardRelations())->get()->keyBy('id');
        $ordered = collect($ids)->map(fn ($id) => $beaches->get($id))->filter()->values();

        return response()->json([
            'data' => BeachResource::collection($ordered)->resolve(),
            'meta' => ['total' => $ordered->count(), 'limit' => $this->limit()],
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/me/favorites/ids",
     *     operationId="getMyFavoriteIds",
     *     tags={"Favorites"},
     *     summary="Ids of my saved beaches (to show the heart state on any list)",
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Beach ids, newest first", @OA\JsonContent(@OA\Property(property="data", type="array", @OA\Items(type="integer")))),
     *     @OA\Response(response=401, description="Not signed in")
     * )
     */
    public function ids(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->favoriteIds($request->user()->id)]);
    }

    /** @return int[] beach ids, newest first */
    private function favoriteIds(int $userId): array
    {
        return DB::table('beach_favorites')->where('user_id', $userId)
            ->orderByDesc('created_at')->orderByDesc('id')->pluck('beach_id')->map(fn ($id) => (int) $id)->all();
    }

    /**
     * @OA\Post(
     *     path="/api/v1/me/favorites",
     *     operationId="addFavorite",
     *     tags={"Favorites"},
     *     summary="Save a beach (idempotent)",
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(required=true, @OA\JsonContent(required={"beach_id"}, @OA\Property(property="beach_id", type="integer", example=614))),
     *     @OA\Response(response=201, description="Saved"),
     *     @OA\Response(response=200, description="Already saved"),
     *     @OA\Response(response=401, description="Not signed in"),
     *     @OA\Response(response=422, description="Unknown or unpublished beach, or the list is full (error: favorites_limit)")
     * )
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(['beach_id' => 'required|integer']);
        $beachId = (int) $data['beach_id'];
        $userId = $request->user()->id;

        if (! Beach::whereKey($beachId)->where('status_id', 1)->exists()) {
            return response()->json(['message' => 'Validation failed.', 'errors' => ['beach_id' => ['Unknown beach.']]], 422);
        }
        $existing = DB::table('beach_favorites')->where('user_id', $userId)->where('beach_id', $beachId)->exists();
        if ($existing) {
            return response()->json(['data' => ['beach_id' => $beachId, 'saved' => true]], 200);
        }
        if (DB::table('beach_favorites')->where('user_id', $userId)->count() >= $this->limit()) {
            return response()->json(['error' => 'favorites_limit', 'message' => 'You can save up to '.$this->limit().' beaches.'], 422);
        }

        try {
            DB::table('beach_favorites')->insert(['user_id' => $userId, 'beach_id' => $beachId, 'created_at' => now()]);
        } catch (UniqueConstraintViolationException) {
            return response()->json(['data' => ['beach_id' => $beachId, 'saved' => true]], 200); // a parallel request saved it
        }

        return response()->json(['data' => ['beach_id' => $beachId, 'saved' => true]], 201);
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/me/favorites/{beachId}",
     *     operationId="removeFavorite",
     *     tags={"Favorites"},
     *     summary="Remove a saved beach (idempotent)",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="beachId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=204, description="Removed (or was not saved)"),
     *     @OA\Response(response=401, description="Not signed in")
     * )
     */
    public function destroy(Request $request, int $beachId): Response
    {
        DB::table('beach_favorites')->where('user_id', $request->user()->id)->where('beach_id', $beachId)->delete();

        return response()->noContent();
    }
}
