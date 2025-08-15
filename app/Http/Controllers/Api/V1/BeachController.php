<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\BeachResource;
use App\Models\Beach;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="Beaches",
 *     description="API Endpoints for managing beaches"
 * )
 */
class BeachController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v1/beaches",
     *     summary="List all beaches",
     *     tags={"Beaches"},
     *     @OA\Response(
     *         response=200,
     *         description="List of beaches",
     *         @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/BeachSchema"))
     *     )
     * )
     */
    public function index()
    {
        $beaches = Beach::with(['translations', 'status', 'user'])->get();
        return BeachResource::collection($beaches);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/beaches",
     *     summary="Create a new beach",
     *     tags={"Beaches"},
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

        return new BeachResource($beach->load(['translations', 'status', 'user']));
    }

    /**
     * @OA\Get(
     *     path="/api/v1/beaches/{id}",
     *     summary="Get a single beach by ID",
     *     tags={"Beaches"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Beach ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Beach found",
     *         @OA\JsonContent(ref="#/components/schemas/BeachSchema")
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Not found"
     *     )
     * )
     */
    public function show($id)
    {
        $beach = Beach::with(['translations', 'status', 'user'])->findOrFail($id);

        return new BeachResource($beach);
    }
}
