<?php

namespace App\Services\Security;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Server-side check of a Cloudflare Turnstile token (https://developers.cloudflare.com/turnstile/get-started/server-side-validation/).
 * ADR 2026-10-29 "AI chat daily cost cap and Turnstile".
 */
class TurnstileVerifier
{
    public const OK = 'ok';
    public const INVALID = 'invalid';
    /** Cloudflare could not be reached or answered with an error: the request is let through (logged). */
    public const UNAVAILABLE = 'unavailable';

    /** The action the website's widget sets; a token made for another action is rejected. */
    public const ACTION = 'ai_chat';

    public function enabled(): bool
    {
        if (filled(config('services.turnstile.secret_key'))) {
            return true;
        }
        if (Cache::add('turnstile:disabled-logged:'.now()->format('Y-m-d'), 1, 60 * 60 * 26)) {
            Log::info('Turnstile verification is off (TURNSTILE_SECRET_KEY is not set).');
        }

        return false;
    }

    public function verify(string $token, ?string $remoteIp): string
    {
        try {
            $response = Http::asForm()
                ->timeout((int) config('services.turnstile.timeout', 5))
                ->post((string) config('services.turnstile.verify_url'), array_filter([
                    'secret' => (string) config('services.turnstile.secret_key'),
                    'response' => $token,
                    'remoteip' => $remoteIp,
                ]));
        } catch (\Throwable $e) {
            Log::warning('Turnstile siteverify unreachable; request allowed', ['error' => $e->getMessage()]);

            return self::UNAVAILABLE;
        }

        if (! $response->successful()) {
            Log::warning('Turnstile siteverify error; request allowed', ['status' => $response->status()]);

            return self::UNAVAILABLE;
        }

        $body = (array) $response->json();
        if (($body['success'] ?? false) !== true) {
            $codes = (array) ($body['error-codes'] ?? []);
            // A wrong or missing secret is our configuration, not the visitor: let them through and say so loudly.
            if (array_intersect($codes, ['missing-input-secret', 'invalid-input-secret']) !== []) {
                Log::error('Turnstile secret rejected by Cloudflare; check TURNSTILE_SECRET_KEY', ['error_codes' => $codes]);

                return self::UNAVAILABLE;
            }

            return self::INVALID;
        }

        $action = $body['action'] ?? null;
        if ($action !== null && $action !== '' && $action !== self::ACTION) {
            return self::INVALID;
        }

        return self::OK;
    }
}
