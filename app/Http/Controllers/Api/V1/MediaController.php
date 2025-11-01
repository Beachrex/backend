<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\MediaResource;
use App\Models\Media;
use App\Services\MediaService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Exception;

/**
 * @OA\Tag(
 *     name="Media",
 *     description="Media management endpoints"
 * )
 */
class MediaController extends Controller
{
    protected MediaService $mediaService;

    public function __construct(MediaService $mediaService)
    {
        $this->mediaService = $mediaService;
    }

    /**
     * @OA\Post(
     *     path="/api/v1/media/upload",
     *     summary="Upload media file",
     *     tags={"Media"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"file", "mediable_type", "mediable_id", "media_type_code"},
     *                 @OA\Property(property="file", type="string", format="binary", description="File to upload"),
     *                 @OA\Property(property="mediable_type", type="string", example="Beach", description="Model type (Beach, Region, City, etc.)"),
     *                 @OA\Property(property="mediable_id", type="integer", example=1, description="Model ID"),
     *                 @OA\Property(property="media_type_code", type="string", example="image", description="Media type code"),
     *                 @OA\Property(property="collection_code", type="string", example="beach_gallery", description="Collection code (optional)"),
     *                 @OA\Property(property="is_primary", type="boolean", example=false, description="Set as primary media"),
     *                 @OA\Property(property="order", type="integer", example=0, description="Display order"),
     *                 @OA\Property(property="alt_text", type="string", description="Alt text for images"),
     *                 @OA\Property(property="caption", type="string", description="Caption/description")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Media uploaded successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", ref="#/components/schemas/Media"),
     *             @OA\Property(property="message", type="string", example="Media uploaded successfully")
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function upload(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|file',
            'mediable_type' => 'required|string',
            'mediable_id' => 'required|integer',
            'media_type_code' => 'required|string|exists:media_types,code',
            'collection_code' => 'nullable|string|exists:media_collections,code',
            'is_primary' => 'nullable|boolean',
            'order' => 'nullable|integer|min:0',
            'alt_text' => 'nullable|string|max:255',
            'caption' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
                'message' => 'Validation failed'
            ], 422);
        }

        try {
            // Get the model instance
            $modelClass = "App\\Models\\{$request->mediable_type}";
            if (!class_exists($modelClass)) {
                return response()->json([
                    'success' => false,
                    'message' => "Invalid model type: {$request->mediable_type}"
                ], 422);
            }

            $model = $modelClass::findOrFail($request->mediable_id);

            // Prepare metadata
            $metadata = [];
            if ($request->alt_text) {
                $metadata['alt_text'] = $request->alt_text;
            }
            if ($request->caption) {
                $metadata['caption'] = $request->caption;
            }

            // Upload media
            $media = $this->mediaService->upload(
                $request->file('file'),
                $model,
                $request->media_type_code,
                $request->collection_code,
                [
                    'is_primary' => $request->boolean('is_primary', false),
                    'order' => $request->input('order'),
                    'metadata' => $metadata,
                    'user_id' => $request->user()->id,
                ]
            );

            return response()->json([
                'success' => true,
                'data' => new MediaResource($media),
                'message' => 'Media uploaded successfully'
            ], 201);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/media/{id}",
     *     summary="Get media by ID",
     *     tags={"Media"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Media details",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", ref="#/components/schemas/Media")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Media not found")
     * )
     */
    public function show(int $id): JsonResponse
    {
        $media = Media::with(['mediaType', 'mediaCollection', 'user'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => new MediaResource($media)
        ]);
    }

    /**
     * @OA\Put(
     *     path="/api/v1/media/{id}",
     *     summary="Update media metadata",
     *     tags={"Media"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             @OA\Property(property="alt_text", type="string"),
     *             @OA\Property(property="caption", type="string"),
     *             @OA\Property(property="is_primary", type="boolean"),
     *             @OA\Property(property="order", type="integer")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Media updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", ref="#/components/schemas/Media"),
     *             @OA\Property(property="message", type="string")
     *         )
     *     )
     * )
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $media = Media::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'alt_text' => 'nullable|string|max:255',
            'caption' => 'nullable|string|max:1000',
            'is_primary' => 'nullable|boolean',
            'order' => 'nullable|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
                'message' => 'Validation failed'
            ], 422);
        }

        try {
            // Update metadata
            $metadata = [];
            if ($request->has('alt_text')) {
                $metadata['alt_text'] = $request->alt_text;
            }
            if ($request->has('caption')) {
                $metadata['caption'] = $request->caption;
            }

            if (!empty($metadata)) {
                $this->mediaService->updateMetadata($media, $metadata);
            }

            // Update is_primary
            if ($request->has('is_primary') && $request->boolean('is_primary')) {
                $this->mediaService->setPrimary($media);
            }

            // Update order
            if ($request->has('order')) {
                $media->order = $request->order;
                $media->save();
            }

            return response()->json([
                'success' => true,
                'data' => new MediaResource($media->fresh(['mediaType', 'mediaCollection', 'user'])),
                'message' => 'Media updated successfully'
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/media/{id}",
     *     summary="Delete media",
     *     tags={"Media"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Media deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string")
     *         )
     *     )
     * )
     */
    public function destroy(int $id): JsonResponse
    {
        $media = Media::findOrFail($id);

        try {
            $this->mediaService->delete($media);

            return response()->json([
                'success' => true,
                'message' => 'Media deleted successfully'
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/media/reorder",
     *     summary="Reorder media items",
     *     tags={"Media"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             required={"media_ids"},
     *             @OA\Property(
     *                 property="media_ids",
     *                 type="array",
     *                 @OA\Items(type="integer"),
     *                 example={3, 1, 2}
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Media reordered successfully"
     *     )
     * )
     */
    public function reorder(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'media_ids' => 'required|array',
            'media_ids.*' => 'required|integer|exists:media,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
                'message' => 'Validation failed'
            ], 422);
        }

        try {
            $this->mediaService->reorder($request->media_ids);

            return response()->json([
                'success' => true,
                'message' => 'Media reordered successfully'
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }
}

