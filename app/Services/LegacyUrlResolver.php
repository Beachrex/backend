<?php

namespace App\Services;

use App\Http\Middleware\SetLocaleFromHeader;
use App\Models\Beach;
use App\Models\City;
use App\Models\Country;
use App\Models\CountryTranslation;
use App\Models\Island;
use App\Models\IslandTranslation;
use App\Models\Region;
use App\Models\RegionTranslation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Resolves an old beachrex.com URL to the matching page of the new site (TASK-004 step 5b).
 *
 * Old URL shapes (old languages sl, en, hr, de, fr, es, it, ua, ru):
 *   /{lang}/{country}/{region}/{city}-{beaches}/{beach}   beach (most links on Google Maps)
 *   /{lang}/beach/{beach}                                  beach, short form
 *   /{lang}/{country}/{region}/{city}-{beaches}            city
 *   /{lang}/{country}/{region}/island-{name}               island
 *   /{lang}/{country}/{region}                             region
 *   /{lang}/{country}[/{beach-type}-beaches]               country
 *   /{lang}/beach-filter                                   beach map
 *
 * The most specific match wins: beach → city → island → region → country. The result holds the
 * localized slugs for the new URL `/{locale}/{beaches}/{continent}/{country}/[region]/[island]/[city]/[beach]`;
 * the frontend adds the locale and its localized beaches word.
 */
class LegacyUrlResolver
{
    /** Old language prefixes; `sl` became `si` in the new frontend. */
    public const OLD_LANGUAGES = ['sl', 'en', 'hr', 'de', 'fr', 'es', 'it', 'ua', 'ru'];

    /** Map of old nice URL => beach id, built by `php artisan legacy-urls:build`. */
    public const MAP_PATH = 'database/legacy/old-beach-urls.json';

    /** @var array<string,int>|null */
    private ?array $beachMap = null;

    /**
     * @return array{type:string, language:?string, segments:array<int,string>}|null
     *         type is beach|city|island|region|country|map; null when nothing matches.
     */
    public function resolve(string $path, string $locale): ?array
    {
        $segments = $this->segments($path);

        $language = null;
        if ($segments !== [] && $this->isLanguage($segments[0])) {
            $language = array_shift($segments);
            // The new frontend prefixes its own locale; an old language may follow it (/en/sl/...).
            if ($segments !== [] && $this->isLanguage($segments[0])) {
                $language = array_shift($segments);
            }
        }
        if ($segments === []) {
            return null;
        }

        if ($segments[0] === 'beach-filter') {
            return ['type' => 'map', 'language' => $language, 'segments' => []];
        }

        // Beach: the last segment is the beach nice URL (or `/beach/{nice}`).
        if (count($segments) >= 2 && ($beach = $this->findBeach(end($segments)))) {
            return ['type' => 'beach', 'language' => $language, 'segments' => $this->beachSegments($beach, $locale)];
        }

        $country = $this->findCountry($segments[0]);
        $rest = array_slice($segments, 1);

        foreach (array_reverse($rest) as $segment) {
            if ($city = $this->findCity($segment, $country?->code)) {
                return ['type' => 'city', 'language' => $language, 'segments' => $this->citySegments($city, $locale)];
            }
        }
        foreach ($rest as $segment) {
            if ($island = $this->findIsland($segment, $country?->code)) {
                return ['type' => 'island', 'language' => $language, 'segments' => $this->islandSegments($island, $locale)];
            }
        }
        if ($country && isset($rest[0]) && ($region = $this->findRegion($rest[0], $country->code))) {
            return ['type' => 'region', 'language' => $language, 'segments' => $this->regionSegments($region, $locale)];
        }
        if ($country) {
            return ['type' => 'country', 'language' => $language, 'segments' => $this->countrySegments($country, $locale)];
        }

        return null;
    }

    /** @return array<int,string> */
    private function segments(string $path): array
    {
        $path = (string) parse_url('/' . ltrim($path, '/'), PHP_URL_PATH);
        $parts = array_map(fn ($s) => Str::lower(rawurldecode($s)), explode('/', $path));
        return array_values(array_filter($parts, fn ($s) => $s !== ''));
    }

    private function isLanguage(string $segment): bool
    {
        return in_array($segment, self::OLD_LANGUAGES, true)
            || in_array($segment, SetLocaleFromHeader::SUPPORTED_LOCALES, true)
            || $segment === 'si';
    }

    /** @return array<string,int> */
    private function beachMap(): array
    {
        if ($this->beachMap === null) {
            $file = base_path(self::MAP_PATH);
            $this->beachMap = is_file($file) ? (array) json_decode((string) file_get_contents($file), true) : [];
        }
        return $this->beachMap;
    }

    private function findBeach(string $segment): ?Beach
    {
        $map = $this->beachMap();
        $candidates = [$segment];
        // Other languages replaced the first word: plaza-tisno, strand-tisno, spiaggia-tisno → beach-tisno.
        if (str_contains($segment, '-')) {
            $candidates[] = 'beach-' . Str::after($segment, '-');
        }
        foreach ($candidates as $candidate) {
            if (!isset($map[$candidate])) {
                continue;
            }
            $beach = Beach::with(['translations', 'continent.translations', 'country.translations', 'region.translations', 'island.translations', 'city.translations'])
                ->where('status_id', 1)
                ->find($map[$candidate]);
            if ($beach) {
                return $beach;
            }
        }
        return null;
    }

    private function findCountry(string $segment): ?Country
    {
        $code = CountryTranslation::where('slug', $segment)->value('country_code');
        if (!$code) {
            // Old typos such as "hrvaska" (hrvatska): accept a near match on the slug.
            $best = null;
            foreach (CountryTranslation::query()->get(['country_code', 'slug']) as $t) {
                $distance = levenshtein($segment, (string) $t->slug);
                if ($distance <= 2 && strlen($segment) >= 5 && ($best === null || $distance < $best[0])) {
                    $best = [$distance, $t->country_code];
                }
            }
            $code = $best[1] ?? null;
        }
        return $code ? Country::with(['translations', 'continent.translations'])->where('code', $code)->first() : null;
    }

    /** `{city}-{beaches}` (e.g. tisno-plaze, turanj-strande, cadaques-beaches) → city by its old code. */
    private function findCity(string $segment, ?string $countryCode): ?City
    {
        if (!preg_match('/^(.+)-([a-z]+)$/', $segment, $m)) {
            return null;
        }
        $query = City::with(['translations', 'country.translations', 'country.continent.translations', 'region.translations', 'island.translations'])
            ->where('code', $m[1])
            ->where('status_id', 1);
        if ($countryCode) {
            $query->where('country_code', $countryCode);
        }
        return $query->first();
    }

    private function findIsland(string $segment, ?string $countryCode): ?Island
    {
        $query = Island::with(['translations', 'country.translations', 'country.continent.translations', 'region.translations'])
            ->where(function ($q) use ($segment) {
                $q->where('code', $segment)
                    ->orWhereIn('id', IslandTranslation::where('slug', $segment)->select('island_id'));
            });
        if ($countryCode) {
            $query->where('country_code', $countryCode);
        }
        return $query->first();
    }

    /** Old region segment (e.g. `istra`, `dalmacija`): code, code prefix (`dalmatia-split`) only if unique, or a translated slug. */
    private function findRegion(string $segment, string $countryCode): ?Region
    {
        $base = Region::with(['translations', 'country.translations', 'country.continent.translations'])
            ->where('country_code', $countryCode);

        if ($region = (clone $base)->where('code', $segment)->first()) {
            return $region;
        }
        if ($region = (clone $base)->whereIn('id', RegionTranslation::where('slug', $segment)->select('region_id'))->first()) {
            return $region;
        }
        $prefixed = (clone $base)->where('code', 'like', $segment . '-%')->limit(2)->get();
        return $prefixed->count() === 1 ? $prefixed->first() : null;
    }

    private function slug(?Model $model, string $locale): ?string
    {
        if (!$model) {
            return null;
        }
        $translations = $model->translations;
        return $translations->firstWhere('locale', $locale)?->slug ?? $translations->firstWhere('locale', 'en')?->slug;
    }

    /** @param array<int,?string> $parts @return array<int,string> */
    private function compact(array $parts): array
    {
        return array_values(array_filter($parts, fn ($p) => $p !== null && $p !== ''));
    }

    /** @return array<int,string> */
    private function countrySegments(Country $country, string $locale): array
    {
        return $this->compact([$this->slug($country->continent, $locale), $this->slug($country, $locale)]);
    }

    /** @return array<int,string> */
    private function regionSegments(Region $region, string $locale): array
    {
        return [...$this->countrySegments($region->country, $locale), ...$this->compact([$this->slug($region, $locale)])];
    }

    /** @return array<int,string> */
    private function islandSegments(Island $island, string $locale): array
    {
        return [
            ...$this->countrySegments($island->country, $locale),
            ...$this->compact([$this->slug($island->region, $locale), $this->slug($island, $locale)]),
        ];
    }

    /** @return array<int,string> */
    private function citySegments(City $city, string $locale): array
    {
        return [
            ...$this->countrySegments($city->country, $locale),
            ...$this->compact([$this->slug($city->region, $locale), $this->slug($city->island, $locale), $this->slug($city, $locale)]),
        ];
    }

    /** @return array<int,string> */
    private function beachSegments(Beach $beach, string $locale): array
    {
        return $this->compact([
            $this->slug($beach->continent, $locale),
            $this->slug($beach->country, $locale),
            $this->slug($beach->region, $locale),
            $this->slug($beach->island, $locale),
            $this->slug($beach->city, $locale),
            $this->slug($beach, $locale),
        ]);
    }
}
