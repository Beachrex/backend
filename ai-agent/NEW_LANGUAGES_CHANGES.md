# New Languages Addition — Frontend Change Log

**Date**: 2026-07-28
**Added languages**: Danish (da), Portuguese (pt), Dutch (nl), Greek (el), Albanian (sq)
**Total languages after change**: 19 (was 14)
**Files modified**: 28 + 5 new = 33

---

## Summary

Added 5 new languages to the Beachrex Next.js frontend. The backend was already updated and pushed in the previous phase. This phase covers: locale configuration, localized routing, translation messages, middleware, Nominatim mapping, sitemaps, and documentation.

**New locale list (19)**: `en, si, de, hr, fr, es, it, ua, ru, pl, cs, hu, bg, sk, da, pt, nl, el, sq`

---

## Changes by Category

### 1. Locale Configuration (1 file)

| File | Change |
|------|--------|
| `src/app/config/locales.ts` | Added 5 locale codes to `locales` array; added 5 display names to `languageNames` (Dansk, Português, Nederlands, Ελληνικά, Shqip) |

### 2. Routing (1 file)

| File | Change |
|------|--------|
| `src/i18n/routing.ts` | Added 5 entries to each of 5 `globalRoutes` maps (beaches, beachMap, beachType, userType, about); added 5 entries to each of 8 `pathnames` maps |

**Route slug translations applied:**

| Route | da | pt | nl | el | sq |
|-------|----|----|----|----|-----|
| beaches | straande | praias | stranden | paralies | plazhet |
| beach-map | strandkort | mapa-praias | strandkaart | chartis-paralion | harta-plazheve |
| beach-type | strandtype | tipo-praia | strandtype | typos-paralias | lloji-plazhit |
| user-type | brugertype | tipo-usuario | gebruikerstype | typos-christi | lloji-perdoruesit |
| about | om-os | sobre | over-ons | schetika | rreth-nesh |

### 3. Middleware (1 file)

| File | Change |
|------|--------|
| `src/middleware.ts` | Added 5 entries to `beachesRoutes` map (localized "beaches" word for URL rewriting) |

### 4. Message Translation Files (5 new files)

| File | Lines | Status |
|------|-------|--------|
| `messages/da.json` | 752 | ✓ Valid JSON, all 38 namespaces, 456 keys match en.json |
| `messages/pt.json` | 748 | ✓ Valid JSON, all 38 namespaces, 456 keys match en.json |
| `messages/nl.json` | 747 | ✓ Valid JSON, all 38 namespaces, 456 keys match en.json |
| `messages/el.json` | 740 | ✓ Valid JSON, all 38 namespaces, 456 keys match en.json |
| `messages/sq.json` | 749 | ✓ Valid JSON, all 38 namespaces, 456 keys match en.json |

All files verified with: `python3 -c "import json; json.load(open('messages/{lang}.json'))"` — valid JSON, structural keys match en.json exactly.

### 5. Nominatim Search Mapping (1 file)

| File | Change |
|------|--------|
| `src/components/Map/MapSearch.tsx` | Added 5 entries to `LOCALE_TO_LANGUAGE` map (da→en,da; pt→en,pt; nl→en,nl; el→en,el; sq→en,sq) |

### 6. Request Config (1 file)

| File | Change |
|------|--------|
| `src/i18n/request.ts` | Fixed type cast from hardcoded `'en' \| 'si'` to `Locale` type (dynamic); added `Locale` import |

### 7. BurgerMenu (1 file)

| File | Change |
|------|--------|
| `src/components/Header/BurgerMenu.tsx` | Refactored from hardcoded `si`/`en` ternary to `menuMap` with dynamic fallback to English. New locales use English menu until localized menu JSON files are created. Added commented import placeholders for future menu_{da,pt,nl,el,sq}.json |

### 8. Sitemaps (6 files)

| File | Change |
|------|--------|
| `src/app/sitemap/static.xml/route.ts` | Added 5 locales to all 6 `routeTranslations` maps (beaches, beachMap, beachType, userType, about); updated header comments (14→19) |
| `src/app/sitemap/countries.xml/route.ts` | Added 5 entries to `beachesWord` map |
| `src/app/sitemap/beaches.xml/route.ts` | Added 5 entries to `beachesWord` map |
| `src/app/sitemap/cities.xml/route.ts` | Added 5 entries to `beachesWord` map |
| `src/app/sitemap/islands.xml/route.ts` | Added 5 entries to `beachesWord` map |
| `src/app/sitemap/regions.xml/route.ts` | Added 5 entries to `beachesWord` map |

### 9. Documentation (11+ files)

| File | Change |
|------|--------|
| `ai-agent/LOCALIZATION_GUIDE.md` | Updated locale list (14→19), expanded route words table with 5 new languages |
| All `ai-agent/*.md` files | "14 languages/14 locales" → "19 languages/19 locales" (ROUTING_GUIDE, SEO_DEEP_AUDIT_REPORT, SEO_QUICK_CHECKLIST, CHANGELOG, SEO_ACTION_PLAN) |

---

## Verification

- **TypeScript**: `npx tsc --noEmit` → **0 errors**
- **JSON validity**: All 5 message files validated with Python json.load()
- **Key structure**: All 5 files have exactly 38 top-level namespaces and 456 keys matching en.json
- **Git diff**: 28 files changed, 698 insertions(+), 149 deletions(-)

---

## Translation Conventions

- **Greek (el)**: Names in Greek script (Ελληνικά, Αφρική), route slugs in Latin transliteration (paralies, chartis-paralion)
- **Portuguese (pt)**: European Portuguese; diacritics in names, removed in slugs
- **Danish (da)**: Special chars (ø, å) in names, ASCII-folded in slugs (straande, om-os)
- **Dutch (nl)**: Special chars (ë, ij) in names, simplified in slugs
- **Albanian (sq)**: Special chars (ë, ç) in names, normalized in slugs
- **Route slugs**: All lowercase, hyphenated, ASCII-only (consistent with existing locales)

---

## Pending / Future Work

1. **Menu JSON files**: Create `public/menu_{da,pt,nl,el,sq}.json` with localized labels and hrefs. Currently falling back to English menu.
2. **Country translations**: Run the backend `generate_country_translations.php` script to fetch country names for the 5 new languages, then re-seed.
3. **Translation review**: Have native speakers review the AI-generated translations for naturalness and accuracy.
4. **Flag images**: No new flag SVGs needed (flags are by country code, not locale code).
