<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Owner 2026-10-23: beach URLs ended in an English "beach-" slug in every language (`/pl/plaze/.../beach-svetilnik`),
 * while the page says "Svetilnik". The old import built the slug from an English name and replaced the local
 * word for beach ("Plage de la Garoupe" → `beach-de-la-garoupe`); the slug was then copied to all 19 languages.
 *
 * Every beach whose slug starts with "beach-" gets one new slug, built from its local name (the English row's
 * name, which is the Latin-script local name): `svetilnik`, `plage-de-la-garoupe`. The same slug is used in every
 * language, as before. When a name repeats, or the slug would equal another beach's slug or a place slug
 * (city, island, region), the town is appended (`soline-krk`), then the region, then the id.
 *
 * Each old slug is kept in `beach_slug_redirects` (per language), so old links resolve and the frontend answers
 * with a 301 to the new URL. Idempotent: beaches without the "beach-" prefix are never touched, so a second run
 * changes nothing. The deploy clears the cache and re-indexes search afterwards.
 */
return new class extends Migration
{
    public const PREFIX = 'beach-';

    public function up(): void
    {
        $rows = DB::table('beach_translation')->select('id', 'beach_id', 'locale', 'name', 'slug')->get();
        $byBeach = $rows->groupBy('beach_id');

        // Beaches to rename: the English (or first) row's slug starts with the prefix.
        $todo = $byBeach->filter(function ($translations) {
            $en = $translations->firstWhere('locale', 'en') ?? $translations->first();

            return str_starts_with((string) $en->slug, self::PREFIX);
        });
        if ($todo->isEmpty()) {
            return;
        }

        // Slugs that must stay unique: other beaches' slugs and every place slug.
        $taken = [];
        foreach ($byBeach as $beachId => $translations) {
            if (! $todo->has($beachId)) {
                foreach ($translations as $t) {
                    $taken[(string) $t->slug] = true;
                }
            }
        }
        foreach (['city_translation', 'island_translation', 'region_translation', 'country_translation', 'continent_translation'] as $table) {
            if (DB::getSchemaBuilder()->hasTable($table)) {
                foreach (DB::table($table)->pluck('slug') as $slug) {
                    $taken[(string) $slug] = true;
                }
            }
        }

        $places = $this->placeSlugs($todo->keys()->all());

        $bases = [];
        foreach ($todo as $beachId => $translations) {
            $en = $translations->firstWhere('locale', 'en') ?? $translations->first();
            $bases[$beachId] = $this->base((string) $en->name, (string) $en->slug);
        }
        $counts = array_count_values($bases);

        foreach ($todo as $beachId => $translations) {
            $base = $bases[$beachId];
            $candidates = [];
            if ($counts[$base] === 1 && ! ctype_digit($base)) {
                $candidates[] = $base;
            }
            foreach ($places[$beachId] ?? [] as $place) {
                if ($place !== '' && $place !== $base) {
                    $candidates[] = "{$base}-{$place}";
                }
            }
            $candidates[] = "{$base}-{$beachId}";

            $new = null;
            foreach ($candidates as $candidate) {
                if (! isset($taken[$candidate])) {
                    $new = $candidate;
                    break;
                }
            }
            $new ??= "{$base}-{$beachId}";
            $taken[$new] = true;

            foreach ($translations as $t) {
                if (! str_starts_with((string) $t->slug, self::PREFIX) || $t->slug === $new) {
                    continue;
                }
                DB::table('beach_translation')->where('id', $t->id)->update(['slug' => $new, 'updated_at' => now()]);
                // Earlier redirects to the old slug now point at the new one (no chains).
                DB::table('beach_slug_redirects')->where('new_slug', $t->slug)->update(['new_slug' => $new, 'updated_at' => now()]);
                DB::table('beach_slug_redirects')->insertOrIgnore([
                    'old_slug' => $t->slug,
                    'new_slug' => $new,
                    'locale' => $t->locale,
                    'beach_id' => $beachId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    /** Slug of the local name; the old slug without the prefix when the name gives nothing usable. */
    private function base(string $name, string $oldSlug): string
    {
        $slug = Str::slug(Str::ascii(str_replace(['\'', '’', 'ʼ'], '', $name)));
        if ($slug === '' || ! preg_match('/[a-z]/', $slug)) {
            $slug = substr($oldSlug, strlen(self::PREFIX));
        }

        return $slug !== '' ? $slug : 'beach';
    }

    /**
     * English slugs of each beach's town, island and region, in that order (for disambiguation).
     *
     * @param  list<int|string>  $beachIds
     * @return array<int|string, list<string>>
     */
    private function placeSlugs(array $beachIds): array
    {
        $out = [];
        foreach (array_chunk($beachIds, 1000) as $chunk) {
            $beaches = DB::table('beach')->whereIn('id', $chunk)->get(['id', 'city_id', 'island_id', 'region_id']);
            foreach ($beaches as $b) {
                $out[$b->id] = array_values(array_filter([
                    $this->enSlug('city_translation', 'city_id', $b->city_id),
                    $this->enSlug('island_translation', 'island_id', $b->island_id),
                    $this->enSlug('region_translation', 'region_id', $b->region_id),
                ]));
            }
        }

        return $out;
    }

    private function enSlug(string $table, string $fk, mixed $id): ?string
    {
        if (! $id) {
            return null;
        }

        return DB::table($table)->where($fk, $id)->orderByRaw("CASE WHEN locale = 'en' THEN 0 ELSE 1 END")->value('slug');
    }

    public function down(): void
    {
        // URLs only move forward: the redirects keep the old slugs working.
    }
};
