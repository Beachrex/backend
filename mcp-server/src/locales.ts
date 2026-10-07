/**
 * Locale handling.
 *
 * The API (Accept-Language) uses `sl` for Slovenian and maps `si` -> `sl` itself
 * (app/Http/Middleware/SetLocaleFromHeader.php). The frontend uses `si` in URLs
 * (web-client src/app/config/locales.ts). Everything else is identical.
 */

/** API locale codes, same list as SetLocaleFromHeader::SUPPORTED_LOCALES. */
export const API_LOCALES = [
  'en', 'sl', 'de', 'hr', 'fr', 'es', 'it', 'ua', 'ru', 'pl',
  'cs', 'hu', 'bg', 'sk', 'da', 'pt', 'nl', 'el', 'sq',
] as const;

export type ApiLocale = typeof API_LOCALES[number];

export const DEFAULT_LOCALE: ApiLocale = 'en';

/** Text used in every tool schema for the `locale` param. */
export const LOCALE_DESCRIPTION =
  'Response language. One of: en, sl (Slovenian; "si" is also accepted), de, hr, fr, es, it, ua (Ukrainian), ru, pl, cs, hu, bg, sk, da, pt, nl, el (Greek), sq (Albanian). Default en. Unsupported values fall back to en.';

/** Aliases accepted as input, mapped to the API locale. */
const ALIASES: Record<string, ApiLocale> = {
  si: 'sl',
  cz: 'cs', // same mapping the API does
  uk: 'ua', // ISO 639-1 for Ukrainian
  gr: 'el',
  al: 'sq',
};

/**
 * Normalise any user/AI-provided locale to an API locale.
 * Accepts `si`, upper case and region variants (`de-DE`, `pt_BR`).
 */
export function normalizeLocale(input?: string | null): ApiLocale {
  if (!input) return DEFAULT_LOCALE;
  const base = String(input).trim().toLowerCase().split(/[-_]/)[0];
  const mapped = ALIASES[base] ?? base;
  return (API_LOCALES as readonly string[]).includes(mapped) ? (mapped as ApiLocale) : DEFAULT_LOCALE;
}

/** Locale segment used in frontend URLs (`sl` -> `si`). */
export function frontendLocale(locale: string): string {
  const api = normalizeLocale(locale);
  return api === 'sl' ? 'si' : api;
}

/**
 * Localised "beaches" URL word, keyed by FRONTEND locale.
 * Copied from web-client src/i18n/routing.ts (globalRoutes.beaches).
 */
export const BEACHES_WORD: Record<string, string> = {
  en: 'beaches',
  si: 'plaze',
  de: 'strande',
  hr: 'plaze',
  fr: 'plages',
  es: 'playas',
  it: 'spiagge',
  ua: 'plyazhi',
  ru: 'plyazhi',
  pl: 'plaze',
  cs: 'plaze',
  hu: 'strandok',
  bg: 'plyazhove',
  sk: 'plaze',
  da: 'straande',
  pt: 'praias',
  nl: 'stranden',
  el: 'paralies',
  sq: 'plazhet',
};

export function getBeachesWord(locale: string): string {
  return BEACHES_WORD[frontendLocale(locale)] ?? 'beaches';
}
