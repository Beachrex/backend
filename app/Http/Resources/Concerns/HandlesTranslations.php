<?php

namespace App\Http\Resources\Concerns;

use Illuminate\Http\Request;

trait HandlesTranslations
{
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

        return strtolower($acceptLanguage);
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
     * Get localized translation data
     */
    protected function getLocalizedTranslation(Request $request, $translations, string $fallbackLocale = 'en'): array
    {
        $translation = $this->getTranslation($request, $translations, $fallbackLocale);
        $requestedLocale = $this->getRequestedLocale($request);

        return [
            'locale' => $translation?->locale ?? $requestedLocale,
            'name' => $translation?->name ?? null,
            'slug' => $translation?->slug ?? null,
        ];
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
