<?php

namespace App\Http\Resources\Concerns;

use Illuminate\Http\Request;

trait HandlesTranslations
{
    /**
     * Locale mappings for frontend compatibility
     */
    private array $localeMappings = [
        'si' => 'sl', // Frontend uses 'si' but database uses 'sl' for Slovenian
    ];

    /**
     * Get the requested locale from Accept-Language header
     */
    protected function getRequestedLocale(Request $request): string
    {
        $acceptLanguage = $request->header('Accept-Language', 'en');

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

        $locale = strtolower($acceptLanguage);

        // Apply locale mapping if needed
        return $this->localeMappings[$locale] ?? $locale;
    }

    /**
     * Get translation for the requested locale with fallback
     */
    protected function getTranslation(Request $request, $translations, string $fallbackLocale = 'en')
    {
        $requestedLocale = $this->getRequestedLocale($request);

        // Try to find translation for requested locale
        $translation = $translations->firstWhere('locale', $requestedLocale);

        // Fallback to specified fallback locale if not found
        if (!$translation && $requestedLocale !== $fallbackLocale) {
            $translation = $translations->firstWhere('locale', $fallbackLocale);
        }

        return $translation;
    }

    /**
     * Get the current locale being used
     */
    protected function getCurrentLocale(Request $request, $translations, string $fallbackLocale = 'en'): string
    {
        $translation = $this->getTranslation($request, $translations, $fallbackLocale);
        return $translation?->locale ?? $fallbackLocale;
    }

    /**
     * Check if translations should be included in response
     * Only include if explicitly requested via query parameter
     */
    protected function shouldIncludeAllTranslations(Request $request): bool
    {
        return $request->query('include_translations') === 'true';
    }
}
