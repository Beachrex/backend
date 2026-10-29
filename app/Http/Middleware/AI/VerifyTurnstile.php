<?php

namespace App\Http\Middleware\AI;

use App\Services\Security\TurnstileVerifier;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cloudflare Turnstile in front of the AI chat (owner 2026-10-29, ADR "AI chat daily cost cap and Turnstile").
 *
 * - Off when TURNSTILE_SECRET_KEY is empty (local, CI, environments without a widget).
 * - `services.turnstile.required_for` = `web` (default): only browser requests must pass (they send `Origin` or
 *   `Sec-Fetch-*`); the mobile app sends neither and is not challenged. `all`: every client.
 * - One check per chat session: after a successful answer the session is remembered as verified for the session
 *   lifetime (sliding, `ai.session.ttl`), bound to the visitor's IP hash. A new conversation, another IP or an
 *   expired session needs a new token.
 * - Missing token → 403 `turnstile_required`; rejected token → 403 `turnstile_failed`. The website then runs the
 *   challenge and sends the message again with `turnstile_token`.
 */
class VerifyTurnstile
{
    public function __construct(private readonly TurnstileVerifier $verifier) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->verifier->enabled() || ! $this->appliesTo($request)) {
            return $next($request);
        }

        $ipHash = hash('sha256', (string) $request->ip());
        $sessionId = $request->input('session_id') ?? $request->header('X-Session-ID');
        $verified = is_string($sessionId) && $sessionId !== ''
            && hash_equals((string) Cache::get(self::key($sessionId), ''), $ipHash);

        if (! $verified) {
            $token = $request->input('turnstile_token');
            if (! is_string($token) || $token === '') {
                return $this->deny('turnstile_required', 'Please confirm that you are not a robot.');
            }
            if ($this->verifier->verify($token, $request->ip()) === TurnstileVerifier::INVALID) {
                return $this->deny('turnstile_failed', 'The robot check failed. Please try again.');
            }
        }

        $response = $next($request);

        // Remember the conversation as verified (and keep it so while it is active).
        if ($response instanceof JsonResponse && $response->getStatusCode() === 200) {
            $id = $response->getData(true)['session_id'] ?? null;
            if (is_string($id) && $id !== '') {
                Cache::put(self::key($id), $ipHash, now()->addMinutes((int) config('ai.session.ttl', 30)));
            }
        }

        return $response;
    }

    public static function key(string $sessionId): string
    {
        return 'ai:turnstile:verified:'.$sessionId;
    }

    private function appliesTo(Request $request): bool
    {
        if (config('services.turnstile.required_for') === 'all') {
            return true;
        }

        return $request->headers->has('Origin')
            || $request->headers->has('Sec-Fetch-Mode')
            || $request->headers->has('Sec-Fetch-Site');
    }

    private function deny(string $code, string $message): JsonResponse
    {
        return response()->json(['error' => $code, 'message' => $message], 403);
    }
}
