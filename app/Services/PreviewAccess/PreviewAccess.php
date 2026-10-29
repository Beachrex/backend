<?php

namespace App\Services\PreviewAccess;

use App\Models\User;
use App\Notifications\PreviewAccessLinkNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * One-time e-mail links that open the closed dev/uat websites (owner 2026-10-29,
 * ADR "Closed dev/uat websites behind an e-mail magic link").
 *
 * Only a SHA-256 hash of each token is stored. The website verifies the token once (server side) and then
 * keeps its own signed cookie; this class never decides who sees the website, only who gets a link.
 */
class PreviewAccess
{
    /** Hosts of the live website: the gate never runs there (keep in sync with web-client environment.ts). */
    public const PRODUCTION_HOSTS = ['beachrex.com', 'www.beachrex.com'];

    public static function enabled(): bool
    {
        if (! config('preview_access.enabled')) {
            return false;
        }
        $host = strtolower((string) parse_url((string) config('app.frontend_url'), PHP_URL_HOST));

        return $host !== '' && ! in_array($host, self::PRODUCTION_HOSTS, true);
    }

    /**
     * A website path to return to after the link: relative, same site, no scheme, no backslashes or
     * control characters (no open redirect). Null when it is not one.
     */
    public static function safeReturnPath(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return '/';
        }
        if (strlen($path) > 512 || $path[0] !== '/' || str_starts_with($path, '//')
            || str_contains($path, '\\') || preg_match('/[\x00-\x1F\x7F]/', $path)) {
            return null;
        }
        $parts = parse_url('https://example.invalid' . $path);
        if ($parts === false || ($parts['host'] ?? '') !== 'example.invalid') {
            return null;
        }

        return $path;
    }

    public function isAllowed(string $email): bool
    {
        $email = strtolower(trim($email));
        if (in_array($email, (array) config('preview_access.allowed_emails', []), true)) {
            return true;
        }

        return User::query()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->where('is_admin', true)
            ->exists();
    }

    /**
     * Sends a link when the address may enter and is not over its e-mail limit. Silent otherwise (the API
     * answers the same either way). Runs after the response, so the timing does not tell either.
     */
    public function sendLink(string $email, string $returnPath, string $locale): void
    {
        $email = strtolower(trim($email));
        if (! $this->isAllowed($email)) {
            return;
        }

        $key = 'preview-access:' . hash('sha256', $email);
        if (RateLimiter::tooManyAttempts($key . ':minute', 1)
            || RateLimiter::tooManyAttempts($key . ':hour', (int) config('preview_access.emails_per_hour', 5))) {
            return;
        }
        RateLimiter::hit($key . ':minute', 60);
        RateLimiter::hit($key . ':hour', 3600);

        $token = Str::random(64);
        $ttl = (int) config('preview_access.ttl_minutes', 15);

        DB::table('preview_access_tokens')->where('created_at', '<', now()->subDay())->delete();
        DB::table('preview_access_tokens')->insert([
            'email' => $email,
            'token_hash' => hash('sha256', $token),
            'return_path' => $returnPath,
            'expires_at' => now()->addMinutes($ttl),
            'created_at' => now(),
        ]);

        try {
            Notification::route('mail', $email)
                ->notify((new PreviewAccessLinkNotification($token, $ttl))->locale($locale));
        } catch (\Throwable $e) {
            Log::error('Preview access e-mail could not be sent', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Uses up a token. Returns the e-mail and the return path, or null when the token is unknown, used or
     * expired. Single use is atomic (one UPDATE … WHERE used_at IS NULL).
     *
     * @return array{email: string, return_path: string}|null
     */
    public function consume(string $token): ?array
    {
        $hash = hash('sha256', $token);
        $used = DB::table('preview_access_tokens')
            ->where('token_hash', $hash)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->update(['used_at' => now()]);
        if ($used !== 1) {
            return null;
        }
        $row = DB::table('preview_access_tokens')->where('token_hash', $hash)->first();

        return ['email' => (string) $row->email, 'return_path' => (string) $row->return_path];
    }
}
