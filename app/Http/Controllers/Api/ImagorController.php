<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ImagorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="Imagor",
 *     description="Image processing and transformation endpoints using Imagor"
 * )
 */
class ImagorController extends Controller
{
    private ImagorService $imagor;

    public function __construct(ImagorService $imagor)
    {
        $this->imagor = $imagor;
    }

    /**
     * @OA\Post(
     *     path="/api/imagor/url",
     *     summary="Generate Imagor URL for an image",
     *     tags={"Imagor"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"image_path"},
     *             @OA\Property(property="image_path", type="string", example="img/beaches/hr-102-o-pag/image.jpg"),
     *             @OA\Property(property="width", type="integer", example=800),
     *             @OA\Property(property="height", type="integer", example=600),
     *             @OA\Property(property="fit_in", type="boolean", example=true),
     *             @OA\Property(property="smart", type="boolean", example=true),
     *             @OA\Property(
     *                 property="filters",
     *                 type="object",
     *                 example={"quality": 90, "blur": 5}
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Imagor URL generated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="url", type="string", example="http://localhost:8001/abc123.../800x600/smart/img/beaches/hr-102-o-pag/image.jpg")
     *         )
     *     ),
     *     @OA\Response(response=400, description="Invalid request"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function generateUrl(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'image_path' => 'required|string',
            'width' => 'nullable|integer|min:1|max:5000',
            'height' => 'nullable|integer|min:1|max:5000',
            'fit_in' => 'nullable|boolean',
            'smart' => 'nullable|boolean',
            'filters' => 'nullable|array',
        ]);

        try {
            $options = array_filter([
                'width' => $validated['width'] ?? null,
                'height' => $validated['height'] ?? null,
                'fit_in' => $validated['fit_in'] ?? null,
                'smart' => $validated['smart'] ?? null,
                'filters' => $validated['filters'] ?? null,
            ], fn($value) => $value !== null);

            $url = $this->imagor->url($validated['image_path'], $options);

            return response()->json([
                'url' => $url,
                'options' => $options,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to generate Imagor URL',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/imagor/presets",
     *     summary="Get available Imagor presets",
     *     tags={"Imagor"},
     *     @OA\Response(
     *         response=200,
     *         description="List of available presets",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="presets",
     *                 type="object",
     *                 example={
     *                     "thumbnail": {"width": 150, "height": 150, "fit_in": true, "smart": true},
     *                     "small": {"width": 400, "height": 300, "fit_in": true},
     *                     "medium": {"width": 800, "height": 600, "fit_in": true},
     *                     "large": {"width": 1200, "height": 900, "fit_in": true},
     *                     "hero": {"width": 1920, "height": 600, "smart": true}
     *                 }
     *             )
     *         )
     *     )
     * )
     */
    public function presets(): JsonResponse
    {
        return response()->json([
            'presets' => config('imagor.presets', []),
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/imagor/preset/{preset}",
     *     summary="Generate Imagor URL using a preset",
     *     tags={"Imagor"},
     *     @OA\Parameter(
     *         name="preset",
     *         in="path",
     *         required=true,
     *         description="Preset name (thumbnail, small, medium, large, hero)",
     *         @OA\Schema(type="string", enum={"thumbnail", "small", "medium", "large", "hero"})
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"image_path"},
     *             @OA\Property(property="image_path", type="string", example="img/beaches/hr-102-o-pag/image.jpg")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Imagor URL generated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="url", type="string"),
     *             @OA\Property(property="preset", type="string")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Preset not found"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function generatePresetUrl(Request $request, string $preset): JsonResponse
    {
        $validated = $request->validate([
            'image_path' => 'required|string',
        ]);

        try {
            $url = match ($preset) {
                'thumbnail' => $this->imagor->thumbnail($validated['image_path']),
                'small' => $this->imagor->small($validated['image_path']),
                'medium' => $this->imagor->medium($validated['image_path']),
                'large' => $this->imagor->large($validated['image_path']),
                'hero' => $this->imagor->hero($validated['image_path']),
                default => throw new \InvalidArgumentException("Unknown preset: {$preset}"),
            };

            return response()->json([
                'url' => $url,
                'preset' => $preset,
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to generate Imagor URL',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/imagor/batch",
     *     summary="Generate multiple Imagor URLs at once",
     *     tags={"Imagor"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"images"},
     *             @OA\Property(
     *                 property="images",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="image_path", type="string"),
     *                     @OA\Property(property="preset", type="string")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Batch URLs generated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="urls",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="image_path", type="string"),
     *                     @OA\Property(property="url", type="string"),
     *                     @OA\Property(property="preset", type="string")
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function batchGenerate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'images' => 'required|array|max:100',
            'images.*.image_path' => 'required|string',
            'images.*.preset' => 'nullable|string|in:thumbnail,small,medium,large,hero',
        ]);

        $results = [];

        foreach ($validated['images'] as $image) {
            try {
                $preset = $image['preset'] ?? 'medium';
                
                $url = match ($preset) {
                    'thumbnail' => $this->imagor->thumbnail($image['image_path']),
                    'small' => $this->imagor->small($image['image_path']),
                    'medium' => $this->imagor->medium($image['image_path']),
                    'large' => $this->imagor->large($image['image_path']),
                    'hero' => $this->imagor->hero($image['image_path']),
                    default => $this->imagor->medium($image['image_path']),
                };

                $results[] = [
                    'image_path' => $image['image_path'],
                    'url' => $url,
                    'preset' => $preset,
                ];
            } catch (\Exception $e) {
                $results[] = [
                    'image_path' => $image['image_path'],
                    'error' => $e->getMessage(),
                ];
            }
        }

        return response()->json([
            'urls' => $results,
        ]);
    }
}

