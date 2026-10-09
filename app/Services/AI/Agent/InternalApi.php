<?php

namespace App\Services\AI\Agent;

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;

/**
 * Calls our own public API inside the current PHP process (no HTTP round trip), so the chat agent's tools
 * return exactly what the website and the MCP server get. The outer request and the app locale are
 * restored afterwards (the API's SetLocaleFromHeader middleware changes the locale).
 */
class InternalApi
{
    /** @return array{status: int, body: array} */
    public function get(string $path, array $query, string $locale): array
    {
        $app = app();
        $outerRequest = $app->bound('request') ? $app['request'] : null;
        $outerLocale = $app->getLocale();

        $request = Request::create('/api/v1/'.ltrim($path, '/'), 'GET', array_filter($query, fn ($v) => $v !== null && $v !== '' && $v !== []), [], [], [
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_ACCEPT_LANGUAGE' => $locale,
            // Not a visitor: beach-details must not count a view (see BeachDetailsController).
            'HTTP_X_BEACHREX_CLIENT' => 'ai-chat',
        ]);

        try {
            $response = $app->make(Kernel::class)->handle($request);
            $body = json_decode((string) $response->getContent(), true);

            return ['status' => $response->getStatusCode(), 'body' => is_array($body) ? $body : []];
        } finally {
            if ($outerRequest) {
                $app->instance('request', $outerRequest);
            }
            $app->setLocale($outerLocale);
        }
    }
}
