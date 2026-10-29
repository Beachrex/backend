<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\PreviewAccess\PreviewAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

use function Illuminate\Support\defer;

/**
 * E-mail magic links for the closed dev/uat websites (owner 2026-10-29, ADR "Closed dev/uat websites behind
 * an e-mail magic link"). Both endpoints answer 404 when the gate is off for this environment (always on prod).
 */
class PreviewAccessController extends Controller
{
    public function __construct(private readonly PreviewAccess $previewAccess)
    {
    }

    /**
     * @OA\Post(
     *     path="/api/v1/preview-access/request",
     *     operationId="requestPreviewAccess",
     *     summary="Send a one-time link that opens the closed dev/uat website",
     *     description="Only when the preview gate is on for this environment (`PREVIEW_GATE_ENABLED=true`, never on production); otherwise 404. Always answers 200 with the same message, whether or not the address may enter (admins of this environment and `PREVIEW_ALLOWED_EMAILS`). The e-mail is sent after the response, in the request's language, with a link to `{FRONTEND_URL}/preview-access/callback?token=…` (valid 15 minutes, one use). At most one e-mail per address per minute and 5 per hour (further requests are silently skipped). Rate limit: 5 requests per minute per IP.",
     *     tags={"Preview access"},
     *     @OA\Parameter(ref="#/components/parameters/AcceptLanguage"),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email"},
     *             @OA\Property(property="email", type="string", format="email", example="john@example.com"),
     *             @OA\Property(property="return_path", type="string", maxLength=512, example="/si/plaze", description="Website path to open after the link: relative, starting with a single `/` (default `/`)")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Request accepted",
     *         @OA\JsonContent(@OA\Property(property="message", type="string", example="If this e-mail may open the test website, we have sent a link."))
     *     ),
     *     @OA\Response(response=404, description="The preview gate is off for this environment"),
     *     @OA\Response(response=422, description="Validation error (invalid e-mail, or `return_path` is not a relative path)"),
     *     @OA\Response(response=429, description="Too many requests")
     * )
     */
    public function request(Request $request): JsonResponse
    {
        abort_unless(PreviewAccess::enabled(), 404);

        $validated = $request->validate([
            'email' => 'required|email|max:255',
            'return_path' => 'nullable|string|max:512',
        ]);
        $returnPath = PreviewAccess::safeReturnPath($validated['return_path'] ?? null);
        if ($returnPath === null) {
            throw ValidationException::withMessages(['return_path' => ['The return path must be a relative path.']]);
        }

        $email = $validated['email'];
        $locale = app()->getLocale();
        $previewAccess = $this->previewAccess;

        // After the response: same timing for allowed and other addresses.
        defer(fn () => $previewAccess->sendLink($email, $returnPath, $locale));

        return response()->json([
            'message' => __('If this e-mail may open the test website, we have sent a link.'),
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/preview-access/verify",
     *     operationId="verifyPreviewAccess",
     *     summary="Use up a one-time preview link (called by the website server)",
     *     description="Only when the preview gate is on for this environment; otherwise 404. A token works once and for 15 minutes. On success the website sets its own signed cookie. Rate limit: 20 requests per minute per IP (the callers are the website servers).",
     *     tags={"Preview access"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"token"},
     *             @OA\Property(property="token", type="string", example="Xy12…")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Token accepted",
     *         @OA\JsonContent(
     *             @OA\Property(property="ok", type="boolean", example=true),
     *             @OA\Property(property="email", type="string", format="email", example="john@example.com"),
     *             @OA\Property(property="return_path", type="string", example="/si/plaze")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Unknown, used or expired token",
     *         @OA\JsonContent(
     *             @OA\Property(property="ok", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="This link is invalid, already used or expired.")
     *         )
     *     ),
     *     @OA\Response(response=404, description="The preview gate is off for this environment"),
     *     @OA\Response(response=429, description="Too many requests")
     * )
     */
    public function verify(Request $request): JsonResponse
    {
        abort_unless(PreviewAccess::enabled(), 404);

        $validated = $request->validate([
            'token' => 'required|string|max:128',
        ]);

        $result = $this->previewAccess->consume($validated['token']);
        if ($result === null) {
            return response()->json([
                'ok' => false,
                'message' => __('This link is invalid, already used or expired.'),
            ], 422);
        }

        return response()->json(['ok' => true] + $result);
    }
}
