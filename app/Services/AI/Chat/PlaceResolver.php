<?php

namespace App\Services\AI\Chat;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * TASK-003: resolves a place name from the chat ("Brač", "Bracu", "Makarska", "otok Krk") to an
 * island, city or region, using their translations in every locale. No LLM, no guessing:
 * an unknown name returns null.
 */
class PlaceResolver
{
    /** Generic words dropped before matching ("otok Krk" → "krk", "Isola di Pag" → "pag"). */
    private const GENERIC_WORDS = ['otok', 'otoka', 'otoku', 'island', 'isola', 'di', 'insel', 'ile', 'isla', 'ilha',
        'ostrov', 'wyspa', 'nisi', 'grad', 'mesto', 'city', 'town', 'citta', 'stadt', 'regija', 'region', 'regione'];

    /** Exact matches win in this order: an island name is usually what people mean ("Hvar" island vs. Hvar town). */
    private const PRIORITY = ['island' => 0, 'city' => 1, 'region' => 2];

    /**
     * @return array{type: string, id: int, code: ?string, name: string}|null
     */
    public function resolve(?string $place, string $locale = 'en'): ?array
    {
        $key = self::key((string) $place);
        if (mb_strlen($key) < 3) {
            return null;
        }
        $index = $this->index();

        $hits = $index[$key] ?? [];
        if (! $hits && mb_strlen($key) >= 4) {
            // Inflected forms: "bracu" (na Braču) → "brac", "pagu" → "pag", "makarski" → "makarska".
            // Drop 1–2 trailing letters (keeping a stem of 3+) and accept a known name that differs only after the stem.
            foreach ([1, 2] as $cut) {
                $stem = mb_substr($key, 0, mb_strlen($key) - $cut);
                if (mb_strlen($stem) < 3) {
                    break;
                }
                foreach ($index as $known => $entries) {
                    if (str_starts_with($known, $stem) && mb_strlen($known) - mb_strlen($stem) <= 2) {
                        $hits = [...$hits, ...$entries];
                    }
                }
                if ($hits) {
                    break;
                }
            }
        }
        if (! $hits) {
            return null;
        }

        usort($hits, fn ($a, $b) => [self::PRIORITY[$a['type']], $a['id']] <=> [self::PRIORITY[$b['type']], $b['id']]);
        $best = $hits[0];
        $best['name'] = $this->name($best['type'], $best['id'], $locale) ?? $place;

        return $best;
    }

    /** Lower-case ASCII key without generic words: "Otok Brač" → "brac". */
    public static function key(string $value): string
    {
        $words = array_filter(explode('-', Str::slug(Str::ascii($value))), fn ($w) => $w !== '' && ! in_array($w, self::GENERIC_WORDS, true));

        return implode('-', $words);
    }

    /** @return array<string, list<array{type: string, id: int, code: ?string}>> key → places */
    private function index(): array
    {
        return Cache::remember('ai:chat:place-index:v1', 3600, function () {
            $index = [];
            foreach (['island' => 'island_id', 'city' => 'city_id', 'region' => 'region_id'] as $type => $fk) {
                $rows = DB::table("{$type}_translation as t")
                    ->join($type, "{$type}.id", '=', "t.{$fk}")
                    ->select("t.{$fk} as id", 't.name', 't.slug', "{$type}.code")
                    ->get();
                foreach ($rows as $row) {
                    foreach (array_unique([self::key((string) $row->name), self::key((string) $row->slug)]) as $key) {
                        if (mb_strlen($key) >= 3) {
                            $index[$key][$type.':'.$row->id] = ['type' => $type, 'id' => (int) $row->id, 'code' => $row->code];
                        }
                    }
                }
            }

            return array_map('array_values', $index);
        });
    }

    private function name(string $type, int $id, string $locale): ?string
    {
        $fk = "{$type}_id";

        return DB::table("{$type}_translation")->where($fk, $id)->whereIn('locale', [$locale, 'en'])
            ->orderByRaw('CASE WHEN locale = ? THEN 0 ELSE 1 END', [$locale])->value('name');
    }
}
