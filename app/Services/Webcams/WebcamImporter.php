<?php

namespace App\Services\Webcams;

use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Copies Windy webcam metadata into `windy_webcams` (TASK-006 §6.1).
 *
 * Per country (and per region when a country has more than the free tier can page through), one
 * checkpoint row per slice in `windy_import_slices`. Idempotent upserts; resumable after a budget stop or
 * a crash; image URLs are never stored. After every slice of a country finishes without truncation,
 * webcams of that country that the run did not see get `missing_since`.
 */
class WebcamImporter
{
    private ?array $regions = null;

    /** @var array<string, array{new:int, updated:int, missing:int}> */
    public array $stats = [];

    public function __construct(private readonly WindyApiClient $api)
    {
    }

    /** Unfinished run for these countries, or a new run key. */
    public function runKey(array $countries, bool $fresh): string
    {
        if (! $fresh) {
            $open = DB::table('windy_import_slices')->whereIn('country_code', $countries)
                ->whereIn('status', ['pending', 'running'])->orderByDesc('id')->value('run_key');
            if ($open) {
                return $open;
            }
        }

        return now()->format('Ymd-His') . '-' . \Illuminate\Support\Str::lower(\Illuminate\Support\Str::random(4));
    }

    /** @return array{finished:bool, requests:int} */
    public function run(string $runKey, array $countries): array
    {
        $before = $this->api->remainingBudget();
        foreach ($countries as $cc) {
            DB::table('windy_import_slices')->insertOrIgnore([
                'run_key' => $runKey, 'country_code' => $cc, 'region_code' => null, 'status' => 'pending',
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        try {
            foreach ($countries as $cc) {
                $this->stats[$cc] ??= ['new' => 0, 'updated' => 0, 'missing' => 0];
                while ($slice = $this->nextSlice($runKey, $cc)) {
                    $this->runSlice($slice);
                }
                $this->markMissing($runKey, $cc);
            }
            $finished = true;
        } catch (BudgetExhausted) {
            $finished = false;
        }

        return ['finished' => $finished, 'requests' => $before - $this->api->remainingBudget()];
    }

    private function nextSlice(string $runKey, string $cc): ?object
    {
        return DB::table('windy_import_slices')->where(['run_key' => $runKey, 'country_code' => $cc])
            ->whereIn('status', ['pending', 'running'])->orderBy('id')->first();
    }

    private function runSlice(object $slice): void
    {
        $slice->next_offset = (int) $slice->next_offset;
        $slice->fetched = (int) $slice->fetched;
        $cfg = config('webcams.import');
        $query = [
            'limit' => WindyApiClient::LIMIT,
            'categories' => $cfg['categories'],
            'categoryOperation' => 'or',
            'include' => ['categories', 'location', 'player'],
            'lang' => 'en',
        ];
        $query += $slice->region_code ? ['regions' => [$slice->region_code]] : ['countries' => [$slice->country_code]];
        $maxRows = WindyApiClient::MAX_OFFSET + WindyApiClient::LIMIT;

        while (true) {
            $page = $this->api->list($query + ['offset' => $slice->next_offset]);
            $total = $page['total'];

            if ($slice->next_offset === 0 && $total > $maxRows && $slice->region_code === null && $this->split($slice)) {
                return;
            }
            $this->upsert($page['webcams'], $slice->country_code);
            $slice->fetched += count($page['webcams']);
            $slice->next_offset += WindyApiClient::LIMIT;
            $more = $page['webcams'] !== [] && $slice->next_offset < $total && $slice->next_offset <= WindyApiClient::MAX_OFFSET;
            $status = $more ? 'running' : ($total > $maxRows ? 'truncated' : 'done');
            DB::table('windy_import_slices')->where('id', $slice->id)->update([
                'next_offset' => $slice->next_offset, 'fetched' => $slice->fetched, 'total' => $total,
                'status' => $status, 'updated_at' => now(),
            ]);
            if (! $more) {
                return;
            }
        }
    }

    /** A country too big for one filter is fetched region by region. False when Windy lists no regions for it. */
    private function split(object $slice): bool
    {
        $this->regions ??= $this->api->regions();
        $prefix = $slice->country_code . '.';
        $codes = array_values(array_filter(array_column($this->regions, 'code'), fn ($c) => str_starts_with((string) $c, $prefix)));
        if ($codes === []) {
            return false; // fetch what the free tier allows; the slice ends as "truncated"
        }
        foreach ($codes as $code) {
            DB::table('windy_import_slices')->insertOrIgnore([
                'run_key' => $slice->run_key, 'country_code' => $slice->country_code, 'region_code' => $code,
                'status' => 'pending', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        DB::table('windy_import_slices')->where('id', $slice->id)->update(['status' => 'split', 'updated_at' => now()]);

        return true;
    }

    private function upsert(array $webcams, string $cc): void
    {
        $now = now();
        foreach ($webcams as $w) {
            $loc = $w['location'] ?? [];
            if (! isset($w['webcamId'], $loc['latitude'], $loc['longitude'])) {
                continue;
            }
            $row = [
                'title' => $w['title'] ?? null,
                'lat' => $loc['latitude'], 'lon' => $loc['longitude'],
                'city' => $loc['city'] ?? null, 'region' => $loc['region'] ?? null, 'region_code' => $loc['region_code'] ?? null,
                'country_code' => $loc['country_code'] ?? $cc,
                'categories' => json_encode(array_values(array_filter(Arr::pluck($w['categories'] ?? [], 'id')))),
                'status' => $w['status'] ?? 'active',
                'last_updated_on' => isset($w['lastUpdatedOn']) ? Carbon::parse($w['lastUpdatedOn']) : null,
                'raw' => json_encode(self::withoutImages($w)),
                'last_seen_at' => $now, 'fetched_at' => $now, 'missing_since' => null, 'updated_at' => $now,
            ];
            $exists = DB::table('windy_webcams')->where('windy_id', $w['webcamId'])->exists();
            if ($exists) {
                DB::table('windy_webcams')->where('windy_id', $w['webcamId'])->update($row);
                $this->stats[$cc]['updated']++;
            } else {
                DB::table('windy_webcams')->insert($row + ['windy_id' => $w['webcamId'], 'first_seen_at' => $now, 'created_at' => $now]);
                $this->stats[$cc]['new']++;
            }
        }
    }

    /** Tokenised image URLs expire; they are never stored (TASK-006 §3). */
    public static function withoutImages(array $webcam): array
    {
        unset($webcam['images']);

        return $webcam;
    }

    private function markMissing(string $runKey, string $cc): void
    {
        $slices = DB::table('windy_import_slices')->where(['run_key' => $runKey, 'country_code' => $cc]);
        if ((clone $slices)->whereIn('status', ['pending', 'running', 'truncated'])->exists()) {
            return; // incomplete or truncated run: absence proves nothing
        }
        $started = (clone $slices)->min('created_at');
        $categories = config('webcams.import.categories');
        // Only webcams the import query covers; e.g. a legacy webcam outside these categories is not "missing".
        $this->stats[$cc]['missing'] = DB::table('windy_webcams')->where('country_code', $cc)->whereNull('missing_since')
            ->whereRaw('jsonb_exists_any(categories, ?::text[])', ['{' . implode(',', $categories) . '}'])
            ->where(fn ($q) => $q->whereNull('last_seen_at')->orWhere('last_seen_at', '<', $started))
            ->update(['missing_since' => now(), 'updated_at' => now()]);
    }
}
