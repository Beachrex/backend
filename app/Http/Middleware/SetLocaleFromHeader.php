<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

class SetLocaleFromHeader
{
    /**
     * Supported locales for the application
     */
    private array $supportedLocales = ['en', 'sl', 'de', 'hr', 'fr', 'es', 'it', 'ua', 'ru', 'pl', 'cs', 'hu', 'bg', 'sk', 'da', 'pt', 'nl', 'el', 'sq'];

    /**
     * Locale mappings for frontend compatibility
     * Maps frontend locale codes to database locale codes
     */
    private array $localeMappings = [
        'si' => 'sl', // Frontend uses 'si' but database uses 'sl' for Slovenian
        'cz' => 'cs', // Czech Republic uses 'cz' in some contexts but ISO is 'cs'
    ];

    public function handle(Request $request, Closure $next)
    {
        if ($acceptLanguage = $request->header('Accept-Language')) {
            $locale = $this->parseAcceptLanguageHeader($acceptLanguage);

            // Apply locale mapping if needed
            $mappedLocale = $this->localeMappings[$locale] ?? $locale;

            // Only set locale if it's supported
            if (in_array($mappedLocale, $this->supportedLocales)) {
                App::setLocale($mappedLocale);
            }
        }

        return $next($request);
    }

    /**
     * Parse Accept-Language header to extract the primary locale
     */
    private function parseAcceptLanguageHeader(string $acceptLanguage): string
    {
        // Handle complex Accept-Language headers like "en-US,en;q=0.9,sl;q=0.8"
        if (str_contains($acceptLanguage, ',')) {
            $languages = explode(',', $acceptLanguage);
            $acceptLanguage = trim($languages[0]);
        }

        // Remove quality values like ";q=0.9"
        if (str_contains($acceptLanguage, ';')) {
            $acceptLanguage = trim(explode(';', $acceptLanguage)[0]);
        }

        // Handle language-country codes like "en-US" -> "en" or "de-DE" -> "de"
        if (str_contains($acceptLanguage, '-')) {
            $acceptLanguage = explode('-', $acceptLanguage)[0];
        }

        return strtolower($acceptLanguage);
    }
}
