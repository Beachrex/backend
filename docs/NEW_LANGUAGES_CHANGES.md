# New Languages Addition — Change Log

**Date**: 2026-07-28
**Added languages**: Danish (da), Portuguese (pt), Dutch (nl), Greek (el), Albanian (sq)
**Total languages after change**: 19 (was 14)
**Files modified**: 142

---

## Summary

Added 5 new languages to the Beachrex backend. Also resolved a pre-existing inconsistency where `config/ai.php` and `ItineraryRequest.php` contained Romanian (ro) and were missing Ukrainian (ua), Russian (ru), and Bulgarian (bg) — these are now synced with the main system.

**New locale list (19)**: `en, sl, de, hr, fr, es, it, ua, ru, pl, cs, hu, bg, sk, da, pt, nl, el, sq`

---

## Changes by Category

### 1. Middleware & Config (4 files)

| File | Change |
|------|--------|
| `app/Http/Middleware/SetLocaleFromHeader.php` | Added 5 locales to `$supportedLocales` array |
| `config/ai.php` | Synced `locales` array: removed `ro`, added `ua, ru, bg, da, pt, nl, el, sq` (now 19, matching middleware) |
| `app/Swagger/Swagger.php` | Added 5 locales to Accept-Language enum |
| `app/Console/Commands/TranslateBeaches.php` | Added 5 locales to `$locales` + `$languageNames`; updated description to "19 languages" |

### 2. Filament Admin UI (36 files)

All `TranslationsRelationManager.php` files updated with 5 new language options in the locale Select dropdown:
- `da` => Danish, `pt` => Portuguese, `nl` => Dutch, `el` => Greek, `sq` => Albanian

**36 files** in `app/Filament/BrAdmin/Resources/*/RelationManagers/TranslationsRelationManager.php`

### 3. Seeders (30 files)

#### Locale-array updates (4 files)
- `database/seeders/CountrySeeder.php` — Added 5 locales to `$locales` array
- `database/seeders/IslandSeeder.php` — Added 5 locales to `$locales` array
- `database/seeders/SwimmingDepthOfOneMSeeder.php` — Added 5 locales to `$languages` array
- `database/seeders/scripts/generate_country_translations.php` — Added 5 language mappings

#### Inline translation additions (26 files)
Full translations (name + slug) added for all 5 new languages in every translation block:
- `ContinentSeeder.php` — 7 blocks (continents)
- `BeachTypeSeeder.php` — 8 blocks + filterTitles
- `WaterbodySeeder.php` — 4 blocks + filterTitles
- `WaterBodyTypeSeeder.php` — 3 blocks
- `BeachLocationTypeSeeder.php` — 6 blocks
- `AccessibilitySeeder.php` — 3 blocks
- `AreaDescriptionSeeder.php` — 3 blocks
- `BeachInclinationSeeder.php` — 5 blocks
- `BayLengthSeeder.php` — 2 blocks
- `LocationLengthSeeder.php` — 2 blocks
- `OccupancyRateSeeder.php` — 3 blocks
- `NaturalLocationConditionSeeder.php` — 12 blocks
- `UnderwaterCharacteristicSeeder.php` — 5 blocks
- `SportingActivitiesSeeder.php` — 11 blocks
- `CulinaryOfferSeeder.php` — 12 blocks
- `RecommendedTransportTypeSeeder.php` — 6 blocks
- `LocationInfrastructureSeeder.php` — 23 blocks
- `AccommodationAroundSeeder.php` — 11 blocks
- `UserTypeSeeder.php` — 3 blocks + filterTitles
- `GenderSeeder.php` — 2 blocks
- `RoadToParkingTypeSeeder.php` — 5 blocks
- `ParkingCapacitySeeder.php` — 14 blocks
- `ParkingBeachDistanceSeeder.php` — 3 blocks
- `ParkingBeachHeightSeeder.php` — 2 blocks
- `CountryFilterTitleSeeder.php` — 1 block
- `IslandFilterTitleSeeder.php` — 1 block
- `RegionFilterTitleSeeder.php` — 1 block
- `FirstPageContentSeeder.php` — 11+ blocks (pre-existing syntax error on line 759 unrelated to changes)

### 4. Validation (1 file)
- `app/Http/Requests/AI/ItineraryRequest.php` — Synced `in:` validation rule (removed `ro`, added `ua, ru, bg, da, pt, nl, el, sq`)

### 5. Documentation (17+ files)
- `docs/ai/canonical-documentation.md` — Updated locale code list
- All docs with "14 languages/14 locales" → "19 languages/19 locales" (17 files)
- `docs/FIX_DUPLICATE_SLUGS.md` — Updated language list

### 6. Migration Comments (31+ files)
- All `*_translation` migration files — Updated locale comment lists to include new languages

### 7. Swagger Schema Comments (10+ files)
- All schema files with "Language code (...)" comments — Updated to include new languages

---

## Translation Conventions

- **Greek (el)**: Names use Greek script (e.g. `Αφρική`), slugs use Latin transliteration (e.g. `afriki`)
- **Portuguese (pt)**: Diacritics preserved in names (e.g. `África`), removed in slugs (e.g. `africa`)
- **Danish (da)**: Special chars (ø, å) preserved in names, ASCII-folded in slugs
- **Dutch (nl)**: Special chars (ë, ij) preserved in names, simplified in slugs
- **Albanian (sq)**: Special chars (ë, ç) preserved in names, normalized in slugs
- **Fallback**: English (`en`) remains the fallback locale (unchanged)

---

## Pre-existing Issues Found

1. **`FirstPageContentSeeder.php` line 759**: Uses PHP 8.0 nullsafe operator (`?->`) — pre-existing, not introduced by these changes
2. **`config/ai.php` vs middleware inconsistency**: `ai.php` had `ro` (Romanian) and `pt`, `nl` but was missing `ua`, `ru`, `bg`. Now synced to 19 languages.
3. **Local PHP version**: Dev machine has PHP 7.2.34, project requires 8.2+. `php -l` false-positives on typed properties and nullsafe operator.

---

## Post-Deploy Steps Required

1. **Run country translations generator**: `php database/seeders/scripts/generate_country_translations.php` to fetch country names for the 5 new languages from the stefangabos/world_countries repo
2. **Re-run seeders** for the new languages (or run `php artisan db:seed --class=ContinentSeeder` etc.)
3. **Regenerate Swagger docs**: `php artisan l5-swagger:generate`
4. **Push to git** before starting frontend work
5. **Frontend**: Update Next.js frontend with the same 5 languages (separate phase)
